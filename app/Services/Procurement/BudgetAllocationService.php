<?php

namespace App\Services\Procurement;

use App\Enums\FundGroup;
use App\Enums\PpmpStatus;
use App\Exceptions\ProcurementException;
use App\Models\Procurement\BudgetAllocation;
use App\Models\Procurement\Office;
use App\Models\Procurement\Ppmp;
use App\Models\Procurement\PpmpItem;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Budget allocations per department, year and fund: one amount for CO and MOOE together.
 * Each department is budgeted under one fund (COB, or SIDA for the SIDA departments), so a
 * project charged to the other fund has no budget and blocks the PPMP's submission.
 *
 * The Budget officer allocates to departments only. Every office under a department shares
 * its budget, first come, first served, until the full allocation is used. What counts as
 * used: each office's latest submitted or approved PPMP (drafts do not hold budget), with the
 * PPMP being checked in place of its own office's earlier version.
 */
class BudgetAllocationService
{
    public function find(Office $office, int $fiscalYear, FundGroup $fund): ?BudgetAllocation
    {
        return BudgetAllocation::where('office_id', $office->id)->where('fiscal_year', $fiscalYear)->where('fund_group', $fund)->first();
    }

    /** Centavos used by the department's offices, optionally with $candidate replacing its office's version. */
    public function usedCents(BudgetAllocation $allocation, ?Ppmp $candidate = null): int
    {
        return array_sum($this->usedByOffice($allocation, $candidate));
    }

    /** Centavos used per office of the department: [office_id => cents]. */
    public function usedByOffice(BudgetAllocation $allocation, ?Ppmp $candidate = null): array
    {
        $officeIds = $allocation->office->departmentOfficeIds();
        $ppmpIds = $this->countedPpmpIds($allocation->fiscal_year, $officeIds, $candidate);

        return PpmpItem::whereIn('ppmp_id', $ppmpIds)
            ->whereHas('fundSource', fn ($q) => $q->where('fund_group', $allocation->fund_group))
            ->with('ppmp:id,office_id')
            ->get(['id', 'ppmp_id', 'estimated_budget'])
            ->groupBy(fn ($item) => $item->ppmp->office_id)
            ->map(fn ($items) => $items->sum(fn ($item) => Money::toCents($item->estimated_budget)))
            ->all();
    }

    /**
     * Budget rows for a PPMP: its department's allocation per fund it touches or that is
     * allocated. 'others' = used by the department's other offices, 'available' = what this
     * PPMP can total (allocation - others).
     */
    public function checkPpmp(Ppmp $ppmp): Collection
    {
        $ppmp->loadMissing('items.fundSource', 'office.parent');
        $department = $ppmp->office->department();

        $allocations = $department
            ? BudgetAllocation::where('office_id', $department->id)->where('fiscal_year', $ppmp->fiscal_year)->get()
                ->each(fn ($a) => $a->setRelation('office', $department))->keyBy(fn ($a) => $a->fund_group->value)
            : collect();

        return $ppmp->items->map(fn ($item) => $item->fundSource->fund_group ?? FundGroup::Regular)
            ->merge($allocations->map->fund_group)
            ->unique(fn (FundGroup $fund) => $fund->value)
            ->sortBy(fn (FundGroup $fund) => array_search($fund, FundGroup::cases(), true))
            ->map(function (FundGroup $fund) use ($ppmp, $department, $allocations) {
                $mine = $ppmp->items->filter(fn ($i) => ($i->fundSource->fund_group ?? FundGroup::Regular) === $fund)
                    ->sum(fn ($i) => Money::toCents($i->estimated_budget));
                $allocation = $allocations->get($fund->value);

                // Projects charged to a fund the department is not budgeted under
                $wrongFund = $department?->budget_fund && $department->budget_fund !== $fund;

                if (! $allocation || $wrongFund) {
                    return ['fund' => $fund, 'department' => $department, 'allocation' => null, 'mine' => $mine, 'amount' => null,
                            'others' => null, 'used' => null, 'available' => null, 'remaining' => null,
                            'wrong_fund' => $wrongFund, 'over' => $wrongFund && $mine > 0];
                }

                $amount = Money::toCents((string) $allocation->amount);
                $used = $this->usedCents($allocation, $ppmp);

                return [
                    'fund' => $fund, 'department' => $department, 'allocation' => $allocation, 'mine' => $mine, 'amount' => $amount,
                    'others' => $used - $mine, 'used' => $used, 'available' => $amount - ($used - $mine),
                    'remaining' => $amount - $used, 'wrong_fund' => false, 'over' => $used > $amount,
                ];
            })->values();
    }

    /**
     * Per fund with an allocation: the most this PPMP can total, what it has now, and the department.
     *
     * @return array<string, array{available:int, mine:int, office:Office}>
     */
    public function limits(Ppmp $ppmp, ?Collection $rows = null): array
    {
        return ($rows ?? $this->checkPpmp($ppmp))->whereNotNull('allocation')
            ->mapWithKeys(fn ($r) => [$r['fund']->value => ['available' => $r['available'], 'mine' => $r['mine'], 'office' => $r['department']]])
            ->all();
    }

    /** Block submitting a PPMP that would take its department over budget. */
    public function assertWithinBudget(Ppmp $ppmp): void
    {
        $over = $this->checkPpmp($ppmp)->where('over', true);

        if ($over->isNotEmpty()) {
            $lines = $over->map(fn ($r) => $r['wrong_fund']
                ? sprintf('%s has no %s budget (it is budgeted under %s); change the source of funds of its %s projects',
                    $r['department']->shortName(), $r['fund']->label(), $r['department']->budget_fund->label(), $r['fund']->label())
                : sprintf('%s budget of %s: ₱%s used of ₱%s (over by ₱%s)',
                    $r['fund']->label(), $r['department']->shortName(),
                    Money::format(Money::fromCents($r['used'])), Money::format(Money::fromCents($r['amount'])),
                    Money::format(Money::fromCents($r['used'] - $r['amount']))));

            throw new ProcurementException('This PPMP cannot be submitted. ' . $lines->join('; ') . '.');
        }
    }

    /**
     * Set or realign a department's allocation. It cannot drop below what its offices'
     * submitted / approved PPMPs already use; a change needs a reason.
     */
    public function save(Office $office, int $fiscalYear, FundGroup $fund, string $amount, User $user, ?string $reason = null): BudgetAllocation
    {
        if (! $office->is_department) {
            throw new ProcurementException("Budgets are allocated per department. {$office->shortName()} is not marked as a department in Offices.");
        }

        if ($office->budget_fund && $office->budget_fund !== $fund) {
            throw new ProcurementException("{$office->shortName()} is budgeted under {$office->budget_fund->label()}, not {$fund->label()}.");
        }

        $existing = $this->find($office, $fiscalYear, $fund);

        if ($existing && trim((string) $reason) === '') {
            throw new ProcurementException('Please give the reason for changing this allocation (e.g. realignment).');
        }

        $new = Money::toCents($amount);

        return DB::transaction(function () use ($office, $fiscalYear, $fund, $user, $reason, $existing, $new) {
            if ($existing && $new < ($used = $this->usedCents($existing->setRelation('office', $office)))) {
                throw new ProcurementException('The budget cannot go below what submitted / approved PPMPs already use (₱' . Money::format(Money::fromCents($used)) . '). Amend those PPMPs first.');
            }

            $allocation = $existing ?? new BudgetAllocation(['fiscal_year' => $fiscalYear, 'office_id' => $office->id, 'fund_group' => $fund, 'created_by' => $user->id]);
            $old = $existing?->amount;

            $allocation->fill(['amount' => Money::fromCents($new), 'updated_by' => $user->id])->save();
            $allocation->history()->create(['user_id' => $user->id, 'old_amount' => $old, 'new_amount' => $allocation->amount, 'reason' => $reason]);

            return $allocation->setRelation('office', $office);
        });
    }

    /** Latest submitted / approved PPMP per office and region; the candidate replaces its own office's. */
    protected function countedPpmpIds(int $fiscalYear, array $officeIds, ?Ppmp $candidate): array
    {
        $ppmps = Ppmp::whereIn('office_id', $officeIds)->where('fiscal_year', $fiscalYear)
            ->whereIn('status', [PpmpStatus::Submitted, PpmpStatus::Approved])
            ->get(['id', 'office_id', 'region', 'version']);

        if ($candidate) {
            $ppmps = $ppmps->reject(fn ($p) => $p->office_id === $candidate->office_id && $p->region === $candidate->region);
            if (in_array($candidate->office_id, $officeIds, true)) {
                $ppmps->push($candidate);
            }
        }

        return $ppmps->groupBy(fn ($p) => $p->office_id . '-' . $p->region->value)
            ->map(fn ($group) => $group->sortByDesc('version')->first()->id)
            ->values()->all();
    }
}
