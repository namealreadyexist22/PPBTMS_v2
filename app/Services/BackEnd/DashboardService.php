<?php

namespace App\Services\BackEnd;

use App\Core\Models\Setting;
use App\Enums\AoqStatus;
use App\Enums\AppStatus;
use App\Enums\BiddingStatus;
use App\Enums\BiddingStep;
use App\Enums\BudgetProposalStatus;
use App\Enums\PpmpStatus;
use App\Enums\Region;
use App\Enums\RequestStatus;
use App\Enums\RfqStatus;
use App\Models\Procurement\AnnualProcurementPlan;
use App\Models\Procurement\Aoq;
use App\Models\Procurement\Bidding;
use App\Models\Procurement\BudgetProposal;
use App\Models\Procurement\Office;
use App\Models\Procurement\Ppmp;
use App\Models\Procurement\PpmpAmendmentPeriod;
use App\Models\Procurement\PpmpItem;
use App\Models\Procurement\PurchaseRequest;
use App\Models\Procurement\PurchaseRequestEvent;
use App\Models\Procurement\Rfq;
use App\Models\User;
use App\Services\Procurement\AppService;
use App\Services\Procurement\BudgetProposalService;
use Illuminate\Support\Collection;

/**
 * The home dashboard: figures for one fiscal year, limited to what the user may see
 * (their offices' PPMPs and PRs, their region's RFQs, AOQs and biddings). Panels a user
 * has no permission for are left out (null).
 */
class DashboardService
{
    /** Bidding steps grouped into the columns of the stage chart. */
    public const STAGE_GROUPS = [
        'Not started'    => [null],
        'Pre-proc'       => [BiddingStep::PreprocSet, BiddingStep::PreprocHeld],
        'Advertised'     => [BiddingStep::Advertised],
        'Pre-bid'        => [BiddingStep::PrebidHeld, BiddingStep::Supplemental],
        'Bid opening'    => [BiddingStep::Bidding],
        'Post-qual'      => [BiddingStep::PostQualified, BiddingStep::BacResolution],
        'NOA / PO / NTP' => [BiddingStep::Noa, BiddingStep::Po, BiddingStep::Ntp],
        'Delivery'       => [BiddingStep::ForDelivery, BiddingStep::Delivered],
    ];

    public function build(User $user, int $year): array
    {
        $can = fn (string $permission) => $user->canAccessPermission($permission);

        return [
            'year'      => $year,
            'years'     => $this->years(),
            'region'    => $this->isSuperAdmin($user) ? null : ($user->region ?? Region::Lm),
            'kpis'      => $this->kpis($user, $year, $can),
            'actions'   => $this->actions($user, $year, $can),
            'pipeline'  => ($can('receive requests') || $can(Rfq::MANAGE_PERMISSION) || $can(Aoq::MANAGE_PERMISSION))
                ? $this->pipeline($user, $year) : null,
            'offices'   => $can(Ppmp::VIEW_ALL_PERMISSION) ? $this->officeBudgets($user, $year) : null,
            'stages'    => $can(Bidding::MANAGE_PERMISSION) ? $this->biddingStages($user, $year) : null,
            'deadlines' => $this->deadlines($user, $year, $can),
            'activity'  => $this->activity($user, $year),
        ];
    }

    /** Fiscal years with a PPMP, plus this year and next, newest first. */
    public function years(): array
    {
        return Ppmp::query()->distinct()->pluck('fiscal_year')
            ->push((int) now()->year, (int) now()->year + 1)
            ->map(fn ($y) => (int) $y)->unique()->sortDesc()->values()->all();
    }

    private function kpis(User $user, int $year, callable $can): array
    {
        $apps = AnnualProcurementPlan::where('fiscal_year', $year)->where('status', AppStatus::Approved)
            ->when(! $this->isSuperAdmin($user), fn ($q) => $q->where('region', $user->region ?? Region::Lm));

        $ppmpIds = Ppmp::visibleTo($user)->approved()->where('fiscal_year', $year)->select('id');
        $budget = PpmpItem::whereIn('ppmp_id', $ppmpIds)
            ->selectRaw('COALESCE(SUM(estimated_budget), 0) AS estimated, COALESCE(SUM(committed_amount), 0) AS committed')
            ->first();

        $requests = PurchaseRequest::visibleTo($user)->where('fiscal_year', $year)->where('status', RequestStatus::Submitted);

        $kpis = [
            'app_budget'    => (float) $apps->sum('total_budget'),
            'app_count'     => $apps->count(),
            'ppmp_budget'   => (float) $budget->estimated,
            'ppmp_committed'=> (float) $budget->committed,
            'pr_in_process' => (clone $requests)->count(),
            'pr_to_receive' => (clone $requests)->whereNull('received_at')->whereNull('returned_at')->count(),
            'biddings'      => null,
        ];

        if ($can(Bidding::MANAGE_PERMISSION)) {
            $biddings = Bidding::visibleTo($user)->ofYear($year)->where('status', BiddingStatus::Ongoing);
            $kpis['biddings'] = ['count' => (clone $biddings)->count(), 'abc' => (float) $biddings->sum('total_abc')];
        }

        return $kpis;
    }

    /** What is waiting on this user: [label, count, url, tone]. Only rows the user can act on. */
    private function actions(User $user, int $year, callable $can): array
    {
        $rows = [];
        $add = function (string $label, int $count, ?string $url, string $tone) use (&$rows) {
            $rows[] = compact('label', 'count', 'url', 'tone');
        };
        $myOffices = $user->prOfficeIds();

        // PPMPs this user approves as the head of the consolidating office
        if ($can('manage ppmp')) {
            if (Office::where('head_user_id', $user->id)->exists()) {
                $add('PPMPs waiting for your approval', Ppmp::visibleTo($user)->where('status', PpmpStatus::Submitted)
                    ->where('fiscal_year', $year)->with('office')->get()
                    ->filter(fn (Ppmp $p) => $p->isApprovableBy($user))->count(), route('procurement.ppmp.index'), 'warning');
            }
            $add('Your returned PPMPs', Ppmp::whereIn('office_id', $myOffices)->where('status', PpmpStatus::Returned)
                ->where('fiscal_year', $year)->count(), route('procurement.ppmp.index'), 'danger');
        }

        // APPs waiting on the BAC Chair (recommend) or the HOPE (approve) of their region
        if ($can('manage app')) {
            foreach ([['recommended', AppStatus::Submitted, 'APPs waiting for your recommendation'],
                      ['approved', AppStatus::Recommended, 'APPs waiting for your approval']] as [$role, $status, $label]) {
                $regions = collect(Region::cases())
                    ->filter(fn (Region $r) => (int) Setting::get(AppService::signatoryKey($r, $role)) === (int) $user->id);
                if ($regions->isNotEmpty()) {
                    $add($label, AnnualProcurementPlan::where('fiscal_year', $year)->where('status', $status)
                        ->whereIn('region', $regions->all())->count(), route('procurement.app.index'), 'warning');
                }
            }
        }

        if ($can('receive requests')) {
            $add('PRs / JRs to receive', PurchaseRequest::visibleTo($user)->where('fiscal_year', $year)
                ->where('status', RequestStatus::Submitted)->whereNull('received_at')->whereNull('returned_at')
                ->count(), route('procurement.receiving.index'), 'accent');
        }

        if ($can(Aoq::EVALUATE_PERMISSION)) {
            $add('AOQs for your TWG evaluation', Aoq::where('status', AoqStatus::ForEvaluation)
                ->whereHas('evaluators', fn ($q) => $q->whereKey($user->id))->count(), route('procurement.twg.index'), 'accent');
        }

        if ($can(Aoq::MANAGE_PERMISSION)) {
            $add('Evaluated AOQs to award', Aoq::visibleTo($user)->where('status', AoqStatus::Evaluated)->count(),
                route('procurement.aoqs.index'), 'accent');
        }

        if ($can(BudgetProposalService::EVALUATE_PERMISSION)) {
            $add('Budget proposals to evaluate', BudgetProposal::where('fiscal_year', $year)
                ->where('status', BudgetProposalStatus::Submitted)->count(), route('procurement.budget-proposals.index'), 'warning');
        }

        if ($can('manage requests')) {
            $add('Your returned PRs / JRs', PurchaseRequest::whereIn('office_id', $myOffices)->where('fiscal_year', $year)
                ->where('status', RequestStatus::Submitted)->whereNotNull('returned_at')->count(),
                route('procurement.requests.index'), 'danger');
        }

        return $rows;
    }

    /** Small-value procurement, step by step: how many documents sit at each stage. */
    private function pipeline(User $user, int $year): array
    {
        $rfqs = Rfq::visibleTo($user)->ofYear($year);
        $aoqs = Aoq::visibleTo($user)->whereHas('rfq', fn ($q) => $q->ofYear($year));

        return [
            ['PRs to receive',        PurchaseRequest::visibleTo($user)->where('fiscal_year', $year)->where('status', RequestStatus::Submitted)
                                          ->whereNull('received_at')->whereNull('returned_at')->count()],
            ['RFQs being prepared',   (clone $rfqs)->where('status', RfqStatus::Draft)->count()],
            ['Out for quotation',     (clone $rfqs)->where('status', RfqStatus::Issued)->doesntHave('aoq')->count()],
            ['AOQs being prepared',   (clone $aoqs)->where('status', AoqStatus::Draft)->count()],
            ['With the TWG',          (clone $aoqs)->where('status', AoqStatus::ForEvaluation)->count()],
            ['Ready to award',        (clone $aoqs)->where('status', AoqStatus::Evaluated)->count()],
            ['Awarded',               (clone $aoqs)->where('status', AoqStatus::Awarded)->count()],
        ];
    }

    /** Approved PPMP budget and the part PRs have committed, per office, largest budget first. */
    private function officeBudgets(User $user, int $year): Collection
    {
        $rows = PpmpItem::query()
            ->join('ppmps', 'ppmps.id', '=', 'ppmp_items.ppmp_id')
            ->whereNull('ppmps.deleted_at')
            ->where('ppmps.status', PpmpStatus::Approved)
            ->where('ppmps.fiscal_year', $year)
            ->when(! $this->isSuperAdmin($user), fn ($q) => $q->where('ppmps.region', $user->region ?? Region::Lm))
            ->groupBy('ppmps.office_id')
            ->selectRaw('ppmps.office_id, SUM(ppmp_items.estimated_budget) AS estimated, SUM(ppmp_items.committed_amount) AS committed')
            ->orderByDesc('estimated')
            ->limit(8)
            ->get();

        $offices = Office::whereIn('id', $rows->pluck('office_id'))->get()->keyBy('id');

        return $rows->map(fn ($row) => [
            'office'    => $offices[$row->office_id]?->acronym ?: ($offices[$row->office_id]?->name ?? 'Unknown office'),
            'estimated' => (float) $row->estimated,
            'committed' => (float) $row->committed,
            'percent'   => $row->estimated > 0 ? round($row->committed / $row->estimated * 100, $row->committed / $row->estimated < 0.01 ? 1 : 0) : 0,
        ]);
    }

    /** Ongoing biddings per group of steps (see STAGE_GROUPS). */
    private function biddingStages(User $user, int $year): array
    {
        $counts = Bidding::visibleTo($user)->ofYear($year)->where('status', BiddingStatus::Ongoing)
            ->selectRaw('stage, COUNT(*) AS n')->groupBy('stage')->pluck('n', 'stage');

        return collect(self::STAGE_GROUPS)->map(fn (array $steps) => collect($steps)
            ->sum(fn (?BiddingStep $step) => (int) ($counts[$step?->value ?? ''] ?? 0)))->all();
    }

    /** Dates coming up in the next 30 days, soonest first. */
    private function deadlines(User $user, int $year, callable $can): Collection
    {
        $until = now()->addDays(30);
        $rows = collect();

        if ($can(Rfq::MANAGE_PERMISSION)) {
            Rfq::visibleTo($user)->where('status', RfqStatus::Issued)->whereBetween('submission_deadline', [now(), $until])
                ->get()->each(fn (Rfq $rfq) => $rows->push([
                    'label' => $rfq->title() . ' · quotation deadline',
                    'at'    => $rfq->submission_deadline,
                    'url'   => route('procurement.rfqs.show', $rfq),
                ]));
        }

        if ($can(Bidding::MANAGE_PERMISSION)) {
            Bidding::visibleTo($user)->where('status', BiddingStatus::Ongoing)
                ->where(fn ($q) => $q->whereBetween('prebid_at', [now(), $until])->orWhereBetween('bidding_at', [now(), $until])
                    ->orWhereBetween('preproc_at', [now(), $until]))
                ->get()->each(function (Bidding $b) use ($rows, $until) {
                    foreach (['preproc_at' => 'pre-procurement conference', 'prebid_at' => 'pre-bid conference', 'bidding_at' => 'bid opening'] as $field => $what) {
                        if ($b->$field && $b->$field->between(now(), $until)) {
                            $rows->push(['label' => $b->title() . ' · ' . $what, 'at' => $b->$field, 'url' => route('procurement.biddings.show', $b)]);
                        }
                    }
                });
        }

        if ($can('manage ppmp') && ($period = PpmpAmendmentPeriod::openFor($year))) {
            $rows->push(['label' => 'PPMP amendment period closes', 'at' => $period->closes_on->endOfDay(), 'url' => route('procurement.ppmp.index')]);
        }

        return $rows->sortBy('at')->take(6)->values();
    }

    /** The latest steps on the status trails of PRs / JRs the user can see. */
    private function activity(User $user, int $year): Collection
    {
        return PurchaseRequestEvent::with(['request', 'user'])
            ->whereIn('purchase_request_id', PurchaseRequest::visibleTo($user)->where('fiscal_year', $year)->select('id'))
            ->latest('created_at')->latest('id')
            ->limit(8)
            ->get();
    }

    private function isSuperAdmin(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }
}
