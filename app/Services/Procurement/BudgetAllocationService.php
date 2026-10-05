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
 * Budget allocations per office, year and fund (COB / SIDA): one amount for CO and MOOE together.
 *
 * An allocation caps the PPMPs of its office and every office under it. Offices below
 * can get their own allocation out of it (a division's split), so a PPMP is checked
 * against every allocation from its office up. What counts as used: each office's
 * latest submitted or approved PPMP (drafts do not hold budget), with the PPMP being
 * checked in place of its own office's earlier version.
 */
class BudgetAllocationService
{
    public function find(Office $office, int $fiscalYear, FundGroup $fund): ?BudgetAllocation
    {
        return BudgetAllocation::where('office_id', $office->id)->where('fiscal_year', $fiscalYear)->where('fund_group', $fund)->first();
    }

    /** Centavos used under an allocation's office, optionally with $candidate replacing its office's version. */
    public function usedCents(BudgetAllocation $allocation, ?Ppmp $candidate = null): int
    {
        $officeIds = Office::withDescendantIds([$allocation->office_id])->all();
        $ppmpIds = $this->countedPpmpIds($allocation->fiscal_year, $officeIds, $candidate);

        return PpmpItem::whereIn('ppmp_id', $ppmpIds)
            ->whereHas('fundSource', fn ($q) => $q->where('fund_group', $allocation->fund_group))
            ->get(['estimated_budget'])
            ->sum(fn ($item) => Money::toCents($item->estimated_budget));
    }

    /**
     * Budget rows for a PPMP: every allocation from its office up, per fund it touches or
     * that is allocated. 'others' = used by the other offices under that allocation,
     * 'available' = what this PPMP can use in total there (allocation - others).
     */
    public function checkPpmp(Ppmp $ppmp): Collection
    {
        $ppmp->loadMissing('items.fundSource', 'office.parent');
        $chain = $this->chain($ppmp->office);

        $funds = $ppmp->items->map(fn ($item) => $item->fundSource->fund_group ?? FundGroup::Regular)
            ->merge(BudgetAllocation::whereIn('office_id', $chain->pluck('id'))->where('fiscal_year', $ppmp->fiscal_year)->pluck('fund_group'))
            ->unique(fn (FundGroup $fund) => $fund->value)
            ->sortBy(fn (FundGroup $fund) => array_search($fund, FundGroup::cases(), true));

        $rows = collect();

        foreach ($funds as $fund) {
            $mine = $ppmp->items->filter(fn ($i) => ($i->fundSource->fund_group ?? FundGroup::Regular) === $fund)
                ->sum(fn ($i) => Money::toCents($i->estimated_budget));

            $allocations = BudgetAllocation::with('office')->whereIn('office_id', $chain->pluck('id'))
                ->where('fiscal_year', $ppmp->fiscal_year)->where('fund_group', $fund)->get()
                ->sortBy(fn ($a) => $chain->search(fn ($o) => $o->id === $a->office_id));

            if ($allocations->isEmpty()) {
                $rows->push(['fund' => $fund, 'allocation' => null, 'mine' => $mine, 'amount' => null, 'others' => null,
                             'used' => null, 'available' => null, 'remaining' => null, 'over' => false]);
                continue;
            }

            foreach ($allocations as $allocation) {
                $amount = Money::toCents((string) $allocation->amount);
                $used = $this->usedCents($allocation, $ppmp);

                $rows->push([
                    'fund' => $fund, 'allocation' => $allocation, 'mine' => $mine, 'amount' => $amount,
                    'others' => $used - $mine, 'used' => $used, 'available' => $amount - ($used - $mine),
                    'remaining' => $amount - $used, 'over' => $used > $amount,
                ]);
            }
        }

        return $rows;
    }

    /**
     * Per fund: the most this PPMP can total (the tightest allocation above it), what it has now,
     * and which office's allocation is the tightest. Funds without an allocation are left out.
     *
     * @return array<string, array{available:int, mine:int, office:Office}>
     */
    public function limits(Ppmp $ppmp, ?Collection $rows = null): array
    {
        return ($rows ?? $this->checkPpmp($ppmp))->whereNotNull('allocation')->groupBy(fn ($r) => $r['fund']->value)
            ->map(function ($group) {
                $tightest = $group->sortBy('available')->first();

                return ['available' => $tightest['available'], 'mine' => $tightest['mine'], 'office' => $tightest['allocation']->office];
            })->all();
    }

    /** Block submitting a PPMP that would go over any allocation above it. */
    public function assertWithinBudget(Ppmp $ppmp): void
    {
        $over = $this->checkPpmp($ppmp)->where('over', true);

        if ($over->isNotEmpty()) {
            $lines = $over->map(fn ($r) => sprintf('%s budget of %s: ₱%s used of ₱%s (over by ₱%s)',
                $r['fund']->label(), $r['allocation']->office->shortName(),
                Money::format(Money::fromCents($r['used'])), Money::format(Money::fromCents($r['amount'])),
                Money::format(Money::fromCents($r['used'] - $r['amount']))));

            throw new ProcurementException('This PPMP goes over the budget allocation. ' . $lines->join('; ') . '.');
        }
    }

    /**
     * Set or realign an allocation. Offices below cannot together get more than this one,
     * this one cannot exceed what is left of the allocation above it, and it cannot drop
     * below what submitted / approved PPMPs already use.
     */
    public function save(Office $office, int $fiscalYear, FundGroup $fund, string $amount, User $user, ?string $reason = null): BudgetAllocation
    {
        $existing = $this->find($office, $fiscalYear, $fund);

        if ($existing && trim((string) $reason) === '') {
            throw new ProcurementException('Please give the reason for changing this allocation (e.g. realignment).');
        }

        $new = Money::toCents($amount);

        return DB::transaction(function () use ($office, $fiscalYear, $fund, $user, $reason, $existing, $new) {
            $children = $this->childAllocations($office, $fiscalYear, $fund)->sum(fn ($a) => Money::toCents((string) $a->amount));

            if ($children > $new) {
                throw new ProcurementException("The budget cannot be lower than what the offices under {$office->shortName()} were given (₱" . Money::format(Money::fromCents($children)) . ').');
            }

            if ($parent = $this->parentAllocation($office, $fiscalYear, $fund)) {
                $siblings = $this->childAllocations($parent->office, $fiscalYear, $fund)
                    ->reject(fn ($a) => $a->office_id === $office->id)
                    ->sum(fn ($a) => Money::toCents((string) $a->amount));
                $room = Money::toCents((string) $parent->amount) - $siblings;

                if ($new > $room) {
                    throw new ProcurementException("The budget is more than what is left of {$parent->office->shortName()}'s allocation (₱" . Money::format(Money::fromCents(max($room, 0))) . ').');
                }
            }

            if ($existing && $new < ($used = $this->usedCents($existing))) {
                throw new ProcurementException('The budget cannot go below what submitted / approved PPMPs already use (₱' . Money::format(Money::fromCents($used)) . '). Amend those PPMPs first.');
            }

            $allocation = $existing ?? new BudgetAllocation(['fiscal_year' => $fiscalYear, 'office_id' => $office->id, 'fund_group' => $fund, 'created_by' => $user->id]);
            $old = $existing?->amount;

            $allocation->fill(['amount' => Money::fromCents($new), 'updated_by' => $user->id])->save();
            $allocation->history()->create(['user_id' => $user->id, 'old_amount' => $old, 'new_amount' => $allocation->amount, 'reason' => $reason]);

            return $allocation;
        });
    }

    /** The nearest allocation above this office (same year and fund). */
    public function parentAllocation(Office $office, int $fiscalYear, FundGroup $fund): ?BudgetAllocation
    {
        for ($o = $office->parent; $o; $o = $o->parent) {
            if ($allocation = $this->find($o, $fiscalYear, $fund)) {
                return $allocation->setRelation('office', $o);
            }
        }

        return null;
    }

    /** Allocations directly under this one: offices below whose nearest allocated office above is this office. */
    public function childAllocations(Office $office, int $fiscalYear, FundGroup $fund): Collection
    {
        $below = Office::withDescendantIds([$office->id])->reject(fn ($id) => $id === $office->id);

        return BudgetAllocation::with('office.parent')->whereIn('office_id', $below)
            ->where('fiscal_year', $fiscalYear)->where('fund_group', $fund)->get()
            ->filter(fn ($a) => $this->parentAllocation($a->office, $fiscalYear, $fund)?->office_id === $office->id)
            ->values();
    }

    /** The office and every office above it, nearest first. */
    protected function chain(Office $office): Collection
    {
        $chain = collect();
        for ($o = $office; $o; $o = $o->parent) {
            $chain->push($o);
        }

        return $chain;
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
