<?php

namespace App\Services\Procurement;

use App\Enums\AllotmentClass;
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
 * Budget allocations per office, year and fund (COB / SIDA), in MOOE and CO.
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

    public function amount(BudgetAllocation $allocation, AllotmentClass $class): string
    {
        return $class === AllotmentClass::Co ? (string) $allocation->co_amount : (string) $allocation->mooe_amount;
    }

    /** Centavos used under an allocation's office for one class, optionally with $candidate replacing its office's version. */
    public function usedCents(BudgetAllocation $allocation, AllotmentClass $class, ?Ppmp $candidate = null): int
    {
        $officeIds = Office::withDescendantIds([$allocation->office_id])->all();
        $ppmpIds = $this->countedPpmpIds($allocation->fiscal_year, $officeIds, $candidate);

        return PpmpItem::whereIn('ppmp_id', $ppmpIds)
            ->where('allotment_class', $class)
            ->whereHas('fundSource', fn ($q) => $q->where('fund_group', $allocation->fund_group))
            ->get(['estimated_budget'])
            ->sum(fn ($item) => Money::toCents($item->estimated_budget));
    }

    /**
     * Budget rows for a PPMP's page and submit check: every allocation from its office up,
     * for each fund it touches or that is allocated, CO first then MOOE.
     */
    public function checkPpmp(Ppmp $ppmp): Collection
    {
        $ppmp->loadMissing('items.fundSource', 'office.parent');
        $chain = $this->chain($ppmp->office);

        $funds = $ppmp->items->map(fn ($item) => $item->fundSource->fund_group ?? FundGroup::Regular)
            ->merge(BudgetAllocation::whereIn('office_id', $chain->pluck('id'))->where('fiscal_year', $ppmp->fiscal_year)->pluck('fund_group'))
            ->unique(fn (FundGroup $fund) => $fund->value);

        $rows = collect();

        foreach ($funds as $fund) {
            $allocations = BudgetAllocation::with('office')->whereIn('office_id', $chain->pluck('id'))
                ->where('fiscal_year', $ppmp->fiscal_year)->where('fund_group', $fund)->get()
                ->sortBy(fn ($a) => $chain->search(fn ($o) => $o->id === $a->office_id));

            foreach ([AllotmentClass::Co, AllotmentClass::Mooe] as $class) {
                $mine = $ppmp->items->filter(fn ($i) => $i->allotment_class === $class && ($i->fundSource->fund_group ?? FundGroup::Regular) === $fund)
                    ->sum(fn ($i) => Money::toCents($i->estimated_budget));

                if ($allocations->isEmpty()) {
                    if ($mine > 0) {
                        $rows->push(['fund' => $fund, 'class' => $class, 'allocation' => null, 'mine' => $mine, 'amount' => null, 'used' => null, 'remaining' => null, 'over' => false]);
                    }
                    continue;
                }

                foreach ($allocations as $allocation) {
                    $amount = Money::toCents($this->amount($allocation, $class));
                    $used = $this->usedCents($allocation, $class, $ppmp);

                    $rows->push([
                        'fund' => $fund, 'class' => $class, 'allocation' => $allocation, 'mine' => $mine,
                        'amount' => $amount, 'used' => $used, 'remaining' => $amount - $used, 'over' => $used > $amount,
                    ]);
                }
            }
        }

        return $rows;
    }

    /** Block submitting a PPMP that would go over any allocation above it. */
    public function assertWithinBudget(Ppmp $ppmp): void
    {
        $over = $this->checkPpmp($ppmp)->where('over', true);

        if ($over->isNotEmpty()) {
            $lines = $over->map(fn ($r) => sprintf('%s %s of %s: ₱%s used of ₱%s (over by ₱%s)',
                $r['fund']->label(), $r['class']->short(), $r['allocation']->office->shortName(),
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
    public function save(Office $office, int $fiscalYear, FundGroup $fund, string $mooe, string $co, User $user, ?string $reason = null): BudgetAllocation
    {
        $existing = $this->find($office, $fiscalYear, $fund);

        if ($existing && trim((string) $reason) === '') {
            throw new ProcurementException('Please give the reason for changing this allocation (e.g. realignment).');
        }

        $new = [AllotmentClass::Mooe->value => Money::toCents($mooe), AllotmentClass::Co->value => Money::toCents($co)];

        return DB::transaction(function () use ($office, $fiscalYear, $fund, $mooe, $co, $user, $reason, $existing, $new) {
            $parent = $this->parentAllocation($office, $fiscalYear, $fund);

            foreach ([AllotmentClass::Mooe, AllotmentClass::Co] as $class) {
                $children = $this->childAllocations($office, $fiscalYear, $fund)
                    ->sum(fn ($a) => Money::toCents($this->amount($a, $class)));

                if ($children > $new[$class->value]) {
                    throw new ProcurementException("{$class->short()} cannot be lower than what the offices under {$office->shortName()} were given (₱" . Money::format(Money::fromCents($children)) . ').');
                }

                if ($parent) {
                    $siblings = $this->childAllocations($parent->office, $fiscalYear, $fund)
                        ->reject(fn ($a) => $a->office_id === $office->id)
                        ->sum(fn ($a) => Money::toCents($this->amount($a, $class)));
                    $room = Money::toCents($this->amount($parent, $class)) - $siblings;

                    if ($new[$class->value] > $room) {
                        throw new ProcurementException("{$class->short()} is more than what is left of {$parent->office->shortName()}'s allocation (₱" . Money::format(Money::fromCents(max($room, 0))) . ').');
                    }
                }

                if ($existing) {
                    $used = $this->usedCents($existing, $class);
                    if ($new[$class->value] < $used) {
                        throw new ProcurementException("{$class->short()} cannot go below what submitted / approved PPMPs already use (₱" . Money::format(Money::fromCents($used)) . '). Amend those PPMPs first.');
                    }
                }
            }

            $allocation = $existing ?? new BudgetAllocation(['fiscal_year' => $fiscalYear, 'office_id' => $office->id, 'fund_group' => $fund, 'created_by' => $user->id]);
            $old = $existing ? [$existing->mooe_amount, $existing->co_amount] : [null, null];

            $allocation->fill(['mooe_amount' => Money::fromCents($new['mooe']), 'co_amount' => Money::fromCents($new['co']), 'updated_by' => $user->id])->save();
            $allocation->history()->create([
                'user_id' => $user->id, 'old_mooe' => $old[0], 'old_co' => $old[1],
                'new_mooe' => $allocation->mooe_amount, 'new_co' => $allocation->co_amount, 'reason' => $reason,
            ]);

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
