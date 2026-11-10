<?php

namespace App\Services\Procurement;

use App\Enums\BudgetProposalStatus;
use App\Exceptions\ProcurementException;
use App\Models\Procurement\BudgetLine;
use App\Models\Procurement\BudgetPap;
use App\Models\Procurement\BudgetProposal;
use App\Models\Procurement\FundSource;
use App\Models\Procurement\Office;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Budget Proposal of a department for a fiscal year:
 *  - each office writes its PAP titles, their budget lines (MOOE not procured) and performance targets (Form A)
 *  - the department submits it; the evaluator (Budget and Treasury) gives each PAP its code and evaluates it,
 *    or returns it for changes. Evaluated locks it. Its budget lines hold budget once submitted.
 *  - PPMPs pick their PAPs from it, at any stage.
 */
class BudgetProposalService
{
    /** Who gives the PAP codes and evaluates (the OIC, Budget and Treasury, or whoever is authorized) */
    public const EVALUATE_PERMISSION = 'evaluate budget proposals';

    public const MANAGE_PERMISSION = 'manage budget proposals';

    /** The department's proposal for the year, started when missing. */
    public function forDepartment(Office $department, int $fiscalYear, ?User $user = null): BudgetProposal
    {
        return BudgetProposal::firstOrCreate(
            ['department_id' => $department->id, 'fiscal_year' => $fiscalYear],
            ['status' => BudgetProposalStatus::Draft, 'created_by' => $user?->id],
        );
    }

    // ----- PAPs -----

    public function addPap(BudgetProposal $proposal, Office $office, string $title, ?string $code = null): BudgetPap
    {
        $this->assertEditable($proposal);
        $this->assertOfficeIn($proposal, $office);
        $this->assertTitle($proposal, $office, $title);

        if ($code !== null && trim($code) !== '') {
            $this->assertCodeFree($proposal, trim($code));
        }

        return $proposal->paps()->create([
            'office_id'  => $office->id,
            'title'      => trim($title),
            'code'       => $code !== null && trim($code) !== '' ? trim($code) : null,
            'sort_order' => (int) $proposal->paps()->max('sort_order') + 1,
        ]);
    }

    public function renamePap(BudgetPap $pap, string $title): BudgetPap
    {
        $this->assertEditable($pap->proposal);
        $this->assertTitle($pap->proposal, $pap->office, $title, $pap->id);
        $pap->update(['title' => trim($title)]);

        return $pap;
    }

    public function removePap(BudgetPap $pap): void
    {
        $this->assertEditable($pap->proposal);

        if ($pap->ppmpPaps()->exists()) {
            throw new ProcurementException("\"{$pap->title}\" is used by a PPMP. Remove it from the PPMP first.");
        }

        $pap->delete();   // its targets and budget lines go with it
    }

    /**
     * A PAP's Performance Contract entry (Form A): [['description', 'indicator', 'q1'..'q4', 'total'], ...],
     * replacing what was there. A blank description continues the one above.
     */
    public function savePerformance(BudgetPap $pap, array $indicators): BudgetPap
    {
        $this->assertEditable($pap->proposal);

        return DB::transaction(function () use ($pap, $indicators) {
            $pap->indicators()->delete();

            foreach (array_values($indicators) as $i => $row) {
                $values = collect($row)->only(['description', 'indicator', 'q1', 'q2', 'q3', 'q4', 'total'])
                    ->map(fn ($v) => trim((string) $v) === '' ? null : trim((string) $v));
                if ($values['indicator'] ?? null) {
                    $pap->indicators()->create($values->all() + ['sort_order' => $i + 1]);
                }
            }

            return $pap;
        });
    }

    // ----- Budget lines -----

    /** Budget-only line of a PAP: MOOE that is not procured (travel, training, representation, ...). */
    public function saveBudgetLine(BudgetPap $pap, array $data, ?BudgetLine $line = null): BudgetLine
    {
        $this->assertEditable($pap->proposal);

        // A line may move to another PAP of the same proposal (and the same office)
        if ($line && ((int) $line->pap->budget_proposal_id !== (int) $pap->budget_proposal_id || (int) $line->pap->office_id !== (int) $pap->office_id)) {
            throw new ProcurementException('That budget line is not in this Budget Proposal.');
        }

        if (Money::toCents($data['amount'] ?? 0) <= 0) {
            throw new ProcurementException('The amount must be greater than zero.');
        }

        $fund = FundSource::find($data['fund_source_id'] ?? null);
        if (! $fund) {
            throw new ProcurementException('Choose the source of funds.');
        }
        if ((! $line || (int) $fund->id !== (int) $line->fund_source_id) && ! $fund->allows($pap->office)) {
            throw new ProcurementException("{$fund->name} is for " . ($fund->restrictedOffice?->shortName() ?? 'another office') . ' only. Choose another source of funds.');
        }
        $budgetFund = $pap->proposal->department->budget_fund;
        if ($budgetFund && $fund->fund_group !== $budgetFund && (int) $fund->id !== (int) $line?->fund_source_id) {
            throw new ProcurementException("{$pap->proposal->department->shortName()} is budgeted under {$budgetFund->label()}; choose a {$budgetFund->label()} source of funds.");
        }

        $values = collect($data)->only(['expense_class_id', 'location_id', 'fund_source_id', 'description', 'amount', 'remarks'])->all()
            + ['budget_pap_id' => $pap->id];

        if ($line) {
            $line->update($values);

            return $line;
        }

        return BudgetLine::create($values + ['sort_order' => (int) $pap->budgetLines()->max('sort_order') + 1]);
    }

    public function removeBudgetLine(BudgetLine $line): void
    {
        $this->assertEditable($line->pap->proposal);
        $line->delete();
    }

    // ----- Workflow -----

    public function submit(BudgetProposal $proposal, User $user): BudgetProposal
    {
        $this->assertEditable($proposal);

        if (! $proposal->paps()->exists()) {
            throw new ProcurementException('Add at least one PAP before submitting.');
        }

        return DB::transaction(function () use ($proposal, $user) {
            $proposal->update(['status' => BudgetProposalStatus::Submitted, 'submitted_at' => now(), 'submitted_by' => $user->id]);
            // Its budget lines now hold budget: within the department's allocations
            app(BudgetAllocationService::class)->assertProposalWithinBudget($proposal->fresh());

            return $proposal;
        });
    }

    /** The evaluator gives the PAP codes: [budget_pap_id => code]. While submitted. */
    public function setCodes(BudgetProposal $proposal, array $codes, User $user): BudgetProposal
    {
        $this->assertStatus($proposal, [BudgetProposalStatus::Submitted]);

        $codes = collect($codes)->map(fn ($c) => trim((string) $c) === '' ? null : trim((string) $c));
        $taken = $codes->filter()->duplicates();
        if ($taken->isNotEmpty()) {
            throw new ProcurementException('PAP code ' . $taken->first() . ' is given to more than one PAP.');
        }

        return DB::transaction(function () use ($proposal, $codes) {
            $paps = $proposal->paps()->whereKey($codes->keys())->get()->keyBy('id');
            // Clear first, so codes can be swapped between PAPs
            foreach ($paps as $pap) {
                $pap->update(['code' => null]);
            }
            foreach ($codes as $id => $code) {
                $paps->get($id)?->update(['code' => $code]);
            }

            return $proposal;
        });
    }

    /** Next free code for one of the office's PAPs: YY-office number-NN */
    public function nextCode(Office $office, int $fiscalYear, array $alsoTaken = []): string
    {
        $prefix = sprintf('%02d-%s-', $fiscalYear % 100, $office->code);
        $used = BudgetPap::whereHas('proposal', fn ($q) => $q->where('fiscal_year', $fiscalYear))
            ->where('code', 'like', $prefix . '%')->pluck('code')->merge($alsoTaken)
            ->filter(fn ($c) => str_starts_with((string) $c, $prefix))
            ->map(fn ($c) => (int) substr($c, strlen($prefix)));

        return $prefix . str_pad((string) (($used->max() ?? 0) + 1), 2, '0', STR_PAD_LEFT);
    }

    /** Suggested codes for the PAPs without one, in their order: [budget_pap_id => code] */
    public function suggestCodes(BudgetProposal $proposal): array
    {
        $suggested = [];
        foreach ($proposal->paps()->with('office')->get() as $pap) {
            if (! $pap->code && $pap->office->code) {
                $suggested[$pap->id] = $this->nextCode($pap->office, $proposal->fiscal_year, array_values($suggested));
            }
        }

        return $suggested;
    }

    public function evaluate(BudgetProposal $proposal, User $user): BudgetProposal
    {
        $this->assertStatus($proposal, [BudgetProposalStatus::Submitted]);

        $missing = $proposal->paps()->whereNull('code')->count();
        if ($missing > 0) {
            throw new ProcurementException("Give every PAP its code first ({$missing} without one).");
        }

        $proposal->update(['status' => BudgetProposalStatus::Evaluated, 'evaluated_at' => now(), 'evaluated_by' => $user->id, 'remarks' => null]);

        return $proposal;
    }

    /** Back to the offices for changes (submitted, or evaluated to reopen it). */
    public function returnToDepartment(BudgetProposal $proposal, User $user, string $remarks): BudgetProposal
    {
        $this->assertStatus($proposal, [BudgetProposalStatus::Submitted, BudgetProposalStatus::Evaluated]);
        $proposal->update(['status' => BudgetProposalStatus::Returned, 'remarks' => $remarks, 'evaluated_at' => null, 'evaluated_by' => null]);

        return $proposal;
    }

    // ----- Access -----

    /** Offices of the department a user may write PAPs for: their home office (Super Admin: any with an office number). */
    public function officesFor(BudgetProposal $proposal, User $user): \Illuminate\Support\Collection
    {
        $ids = $proposal->department->departmentOfficeIds();

        return Office::active()->whereKey($ids)->whereNotNull('code')
            ->when(! $user->hasRole('Super Admin'), fn ($q) => $q->whereKey($user->office_id))
            ->orderBy('code')->get();
    }

    /** May the user change this PAP (its title, targets, budget lines)? Its office's staff, while editable. */
    public function canEditPap(BudgetPap $pap, User $user): bool
    {
        return $pap->proposal->status->isEditable()
            && ($user->hasRole('Super Admin') || (int) $user->office_id === (int) $pap->office_id);
    }

    /** May the user submit it? Anyone of the department (Super Admin too). */
    public function canSubmit(BudgetProposal $proposal, User $user): bool
    {
        return $proposal->status->isEditable() && $this->isOfDepartment($proposal, $user);
    }

    public function isOfDepartment(BudgetProposal $proposal, User $user): bool
    {
        return $user->hasRole('Super Admin') || in_array((int) $user->office_id, $proposal->department->departmentOfficeIds(), true);
    }

    public function canEvaluate(User $user): bool
    {
        return $user->hasRole('Super Admin') || $user->canAccessPermission(self::EVALUATE_PERMISSION);
    }

    /** Its department, the evaluators and the budget office see it */
    public function canView(BudgetProposal $proposal, User $user): bool
    {
        return $this->isOfDepartment($proposal, $user) || $this->canEvaluate($user) || $user->canAccessPermission('manage budget');
    }

    // ----- Checks -----

    protected function assertEditable(BudgetProposal $proposal): void
    {
        if (! $proposal->status->isEditable()) {
            throw new ProcurementException("The FY {$proposal->fiscal_year} Budget Proposal of {$proposal->department->shortName()} is {$proposal->status->value}; it can no longer be changed" . ($proposal->status === BudgetProposalStatus::Evaluated ? ' unless the evaluator returns it.' : '.'));
        }
    }

    protected function assertStatus(BudgetProposal $proposal, array $statuses): void
    {
        if (! in_array($proposal->status, $statuses, true)) {
            throw new ProcurementException("The Budget Proposal is {$proposal->status->value}.");
        }
    }

    protected function assertOfficeIn(BudgetProposal $proposal, Office $office): void
    {
        if (! in_array((int) $office->id, $proposal->department->departmentOfficeIds(), true)) {
            throw new ProcurementException("{$office->shortName()} is not under {$proposal->department->shortName()}.");
        }
        if (! $office->code) {
            throw new ProcurementException("{$office->shortName()} has no office number, so it cannot have PAPs. Use its Office of the Manager or a division.");
        }
    }

    protected function assertTitle(BudgetProposal $proposal, Office $office, string $title, ?int $ignoreId = null): void
    {
        if (trim($title) === '') {
            throw new ProcurementException('The PAP title is required.');
        }

        $taken = $proposal->paps()->where('office_id', $office->id)->where('title', trim($title))
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists();
        if ($taken) {
            throw new ProcurementException("{$office->shortName()} already has the PAP \"" . trim($title) . '".');
        }
    }

    protected function assertCodeFree(BudgetProposal $proposal, string $code): void
    {
        if ($proposal->paps()->where('code', $code)->exists()) {
            throw new ProcurementException("PAP {$code} is already in this Budget Proposal.");
        }
    }
}
