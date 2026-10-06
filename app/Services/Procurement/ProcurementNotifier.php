<?php

namespace App\Services\Procurement;

use App\Models\Procurement\AnnualProcurementPlan;
use App\Models\Procurement\BudgetAllocation;
use App\Models\Procurement\DivisionPpmp;
use App\Models\Procurement\Ppmp;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bell notifications for the procurement workflow (PPMP, Division PPMP, APP, budget).
 *
 * Sent only after the action's transaction commits, never to the person who acted,
 * and a failure to send (e.g. the broadcast server is down) is logged, never thrown:
 * a submit or approval must not fail because of a notification.
 */
class ProcurementNotifier
{
    public function ppmpSubmitted(Ppmp $ppmp, User $actor): void
    {
        $amendment = (bool) $ppmp->amended_from_id;
        $approverId = $ppmp->office->approverId();

        $this->send($approverId ? [User::find($approverId)] : [], $actor,
            $amendment ? 'Amendment submitted for approval' : 'PPMP submitted for approval',
            "{$ppmp->ppmp_no} from {$this->officeName($ppmp->office)} was submitted by {$actor->fullname}.",
            route('procurement.ppmp.show', $ppmp), 'fas fa-paper-plane');
    }

    public function ppmpReturned(Ppmp $ppmp, User $actor, string $remarks): void
    {
        $this->send($this->preparers($ppmp), $actor, 'PPMP returned',
            "{$ppmp->ppmp_no} was returned by {$actor->fullname}: {$remarks}",
            route('procurement.ppmp.show', $ppmp), 'fas fa-undo');
    }

    /** Each preparer of the section PPMPs approved into this Division PPMP number. */
    public function divisionApproved(DivisionPpmp $division, Collection $ppmps, User $actor): void
    {
        foreach ($ppmps as $ppmp) {
            $this->send($this->preparers($ppmp), $actor, 'PPMP approved',
                "{$ppmp->ppmp_no} was approved in {$this->officeName($division->office)} PPMP No. {$division->ppmp_number}.",
                route('procurement.ppmp.show', $ppmp), 'fas fa-check-circle');
        }
    }

    public function appSubmitted(AnnualProcurementPlan $app, User $actor): void
    {
        $this->send([$this->appService()->signatory($app->region, 'recommended')], $actor, 'APP for your recommendation',
            "{$app->title()} was submitted by the BAC Secretariat.", route('procurement.app.show', $app), 'fas fa-calendar-check');
    }

    public function appRecommended(AnnualProcurementPlan $app, User $actor): void
    {
        $this->send([$this->appService()->signatory($app->region, 'approved')], $actor, 'APP for your approval',
            "{$app->title()} was recommended by {$actor->fullname}.", route('procurement.app.show', $app), 'fas fa-calendar-check');
    }

    public function appApproved(AnnualProcurementPlan $app, User $actor): void
    {
        $this->send($this->secretariat($app), $actor, 'APP approved',
            "{$app->title()} was approved by {$actor->fullname}.", route('procurement.app.show', $app), 'fas fa-check-circle');
    }

    public function appReturned(AnnualProcurementPlan $app, User $actor, string $remarks): void
    {
        $this->send($this->secretariat($app), $actor, 'APP returned',
            "{$app->title()} was returned by {$actor->fullname}: {$remarks}", route('procurement.app.show', $app), 'fas fa-undo');
    }

    /** Heads of the department's offices, when its budget is changed (realignment). */
    public function budgetChanged(BudgetAllocation $allocation, ?string $old, User $actor, ?string $reason): void
    {
        $department = $allocation->department;
        $heads = User::whereIn('id', \App\Models\Procurement\Office::whereIn('id', $department->departmentOfficeIds())->whereNotNull('head_user_id')->pluck('head_user_id'))->get();

        $this->send($heads, $actor, "{$department->shortName()} budget changed",
            "{$allocation->fund_group->label()} FY {$allocation->fiscal_year}: ₱" . Money::format($old) . ' → ₱' . Money::format($allocation->amount)
                . ($reason ? " ({$reason})" : '') . '.',
            route('procurement.ppmp.index'), 'fas fa-coins');
    }

    /** Who prepared a PPMP: its creator and whoever submitted it. */
    protected function preparers(Ppmp $ppmp): array
    {
        $submitterId = $ppmp->signatories()->where('role', 'submitted')->latest('signed_at')->value('user_id');

        return User::whereIn('id', array_filter([$ppmp->created_by, $submitterId]))->get()->all();
    }

    /** The region's BAC Secretariat head and whoever created the APP. */
    protected function secretariat(AnnualProcurementPlan $app): array
    {
        return array_filter([$this->appService()->signatory($app->region, 'prepared'), $app->created_by ? User::find($app->created_by) : null]);
    }

    /** The office's acronym, or its name when it has none (e.g. "PPSPD - PPRD - MIS SECTION"). */
    protected function officeName(\App\Models\Procurement\Office $office): string
    {
        return $office->acronym ?: $office->name;
    }

    protected function appService(): AppService
    {
        return app(AppService::class);
    }

    /** Notify each user once, skipping the actor, after the surrounding transaction commits. */
    protected function send(iterable $users, User $actor, string $title, string $message, string $url, string $icon): void
    {
        $recipients = collect($users)->filter()->unique('id')->reject(fn (User $user) => $user->id === $actor->id);

        if ($recipients->isEmpty()) {
            return;
        }

        DB::afterCommit(function () use ($recipients, $title, $message, $url, $icon) {
            foreach ($recipients as $user) {
                try {
                    notify_user($user, $title, $message, $url, $icon);
                } catch (Throwable $e) {
                    Log::warning("Procurement notification to user {$user->id} failed: {$e->getMessage()}");
                }
            }
        });
    }
}
