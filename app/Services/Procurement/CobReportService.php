<?php

namespace App\Services\Procurement;

use App\Enums\AllotmentClass;
use App\Enums\FundGroup;
use App\Enums\PpmpStatus;
use App\Models\Procurement\BudgetAllocation;
use App\Models\Procurement\BudgetPap;
use App\Models\Procurement\BudgetProposal;
use App\Models\Procurement\ExpenseClass;
use App\Models\Procurement\Office;
use App\Models\Procurement\PersonnelService;
use App\Models\Procurement\Ppmp;
use App\Models\Procurement\PpmpItem;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Corporate Operating Budget printouts, from the Budget Proposals, PPMPs and budget allocations:
 *  - Breakdown per Office / Department: PS as set on Budget Allocation; CO and MOOE of each department's PAPs
 *  - per department: Budget Proposal (Summary), Performance Contract (Form A), Details of MOOE (Form B), Details of CO (Form C)
 *
 * The PAPs, their budget lines (MOOE not procured) and performance targets come from the department's
 * Budget Proposal. CO = the Capital Outlay projects the PPMPs put under each PAP; MOOE = their MOOE and
 * semi-expendable projects plus the PAP's budget lines. The PPMPs counted are each office's latest
 * submitted / approved version (as for the budget check), or with $drafts, each office's latest version
 * whatever its status (to preview before submitting). Amounts are in centavos.
 */
class CobReportService
{
    /** Projects of the PPMPs counted for the year, by the Budget Proposal PAP they are under: [budget_pap_id => Collection<PpmpItem>] */
    public function itemsByPap(array $officeIds, int $fiscalYear, bool $drafts = false): Collection
    {
        $ppmpIds = Ppmp::whereIn('office_id', $officeIds)->where('fiscal_year', $fiscalYear)
            ->whereIn('status', $drafts
                ? [PpmpStatus::Draft, PpmpStatus::Returned, PpmpStatus::Submitted, PpmpStatus::Approved]
                : [PpmpStatus::Submitted, PpmpStatus::Approved])
            ->get(['id', 'office_id', 'region', 'version'])
            ->groupBy(fn (Ppmp $p) => $p->office_id . '-' . $p->region->value)
            ->map(fn ($versions) => $versions->sortByDesc('version')->first()->id)
            ->values();

        return PpmpItem::whereIn('ppmp_id', $ppmpIds)
            ->with(['pap', 'expenseClass', 'location', 'unit', 'item', 'fundSource'])
            ->orderBy('sort_order')->orderBy('id')->get()
            ->groupBy(fn (PpmpItem $i) => $i->pap?->budget_pap_id ?? 0);
    }

    /**
     * Breakdown per Office / Department, per fund: rows of department, PS, CO, MOOE, total.
     *
     * @return array<string, array{fund: FundGroup, rows: Collection, totals: array}>
     */
    public function breakdown(int $fiscalYear, bool $drafts = false): array
    {
        $allocations = BudgetAllocation::where('fiscal_year', $fiscalYear)->get()->groupBy('department_id');
        $personnel = PersonnelService::where('fiscal_year', $fiscalYear)->get()->groupBy('department_id');
        $offices = Office::active()->get()->keyBy('id');

        $departments = Office::active()->where('type', 'department')->get()
            ->sortBy(fn (Office $d) => $d->code ?? collect($d->departmentOfficeIds())->map(fn ($id) => $offices->get($id)?->code)->filter()->min() ?? 'zzzzz')
            ->values();

        $result = [];
        foreach (FundGroup::cases() as $fund) {
            $rows = $departments
                ->filter(fn (Office $d) => ($d->budget_fund ?? FundGroup::Regular) === $fund)
                ->map(function (Office $department) use ($fund, $fiscalYear, $drafts, $allocations, $personnel) {
                    $allocation = $allocations->get($department->id)?->firstWhere('fund_group', $fund);
                    $totals = $this->proposal($department, $fiscalYear, $drafts)['totals'];
                    $ps = ($set = $personnel->get($department->id)?->firstWhere('fund_group', $fund)) ? Money::toCents((string) $set->amount) : 0;

                    return [
                        'department' => $department,
                        'ps'         => $ps,
                        'co'         => $totals['co'],
                        'mooe'       => $totals['mooe'],
                        'total'      => $ps + $totals['co'] + $totals['mooe'],
                        // CO and MOOE allocated, to compare on screen with what is planned
                        'allocated'  => $allocation ? Money::toCents((string) $allocation->amount) : null,
                    ];
                })
                ->filter(fn ($row) => $row['total'] > 0 || $row['allocated'])
                ->values();

            $result[$fund->value] = [
                'fund'   => $fund,
                'rows'   => $rows,
                'totals' => collect(['ps', 'co', 'mooe', 'total'])->mapWithKeys(fn ($k) => [$k => $rows->sum($k)])->all(),
            ];
        }

        return $result;
    }

    /**
     * One department's proposal, grouped as the forms are: the department's own PAPs, then each
     * division (Office of the Manager, divisions) with its own PAPs and its sections' (a., b., ...).
     *
     * @return array{department: Office, proposal: ?BudgetProposal, fund: FundGroup, blocks: array, expenseColumns: Collection, totals: array, hasUnclassified: bool}
     */
    public function proposal(Office $department, int $fiscalYear, bool $drafts = false): array
    {
        $fund = $department->budget_fund ?? FundGroup::Regular;
        $officeIds = $department->departmentOfficeIds();
        $budgetProposal = BudgetProposal::where('department_id', $department->id)->where('fiscal_year', $fiscalYear)
            ->with(['paps.indicators', 'paps.budgetLines.expenseClass', 'paps.budgetLines.location', 'paps.budgetLines.fundSource'])->first();
        $items = $budgetProposal ? $this->itemsByPap($officeIds, $fiscalYear, $drafts) : collect();
        $units = Office::whereKey($officeIds)->get()->keyBy('id');
        $childrenOf = $units->groupBy('parent_id');
        $semiId = ExpenseClass::where('code', ExpenseClass::SEMI_EXPENDABLE)->value('id');

        // An office's PAPs by code (those still without one last, in the order written)
        // toBase(): rows are arrays (an empty Eloquent collection's merge() would expect models)
        $papsOf = fn (Office $office) => ($budgetProposal?->paps ?? collect())->toBase()->where('office_id', $office->id)
            ->sortBy(fn ($pap) => [$pap->code === null ? 1 : 0, (string) $pap->code, $pap->sort_order, $pap->id])
            ->map(fn ($pap) => $this->papRow($pap, $items->get($pap->id, collect()), $fund, $semiId))
            ->values();

        $blocks = [];
        // The department's own PAPs (a department without divisions)
        if (($own = $papsOf($department))->isNotEmpty()) {
            $blocks[] = ['office' => null, 'paps' => $own, 'sections' => []];
        }

        foreach ($childrenOf->get($department->id, collect())->sortBy(fn ($o) => $o->code ?? $o->name) as $division) {
            $sections = [];
            $letter = 'a';
            // Sections and anything below them, top down
            $walk = function (Office $parent) use (&$walk, &$sections, &$letter, $childrenOf, $papsOf) {
                foreach ($childrenOf->get($parent->id, collect())->sortBy(fn ($o) => $o->code ?? $o->name) as $section) {
                    $paps = $papsOf($section);
                    if ($paps->isNotEmpty()) {
                        $sections[] = ['office' => $section, 'letter' => $letter++, 'paps' => $paps];
                    }
                    $walk($section);
                }
            };
            $walk($division);

            $paps = $papsOf($division);
            if ($paps->isNotEmpty() || $sections) {
                $blocks[] = ['office' => $division, 'paps' => $paps, 'sections' => $sections];
            }
        }

        // Subtotals per block
        foreach ($blocks as &$block) {
            $all = $block['paps']->merge(collect($block['sections'])->flatMap(fn ($s) => $s['paps']));
            $block['totals'] = $this->sumRows($all);
        }
        unset($block);

        $allRows = collect($blocks)->flatMap(fn ($b) => $b['paps']->merge(collect($b['sections'])->flatMap(fn ($s) => $s['paps'])));

        // Form B columns: the expense classes used, in their order; projects not classed go to "Unclassified"
        $usedClassIds = $allRows->flatMap(fn ($r) => array_keys($r['mooeByClass']))->unique();
        $expenseColumns = ExpenseClass::whereKey($usedClassIds->filter(fn ($id) => $id !== 0)->all())->ordered()->get();

        return [
            'department'      => $department,
            'proposal'        => $budgetProposal,
            'fund'            => $fund,
            'blocks'          => $blocks,
            'expenseColumns'  => $expenseColumns,
            'hasUnclassified' => $usedClassIds->contains(0),
            'totals'          => $this->sumRows($allRows),
        ];
    }

    /**
     * One PAP: CO and MOOE, MOOE per expense class (0 = unclassified), per location, and its CO projects.
     */
    protected function papRow(BudgetPap $pap, Collection $items, FundGroup $fund, ?int $semiId): array
    {
        // Only what is charged to the department's fund
        $items = $items->filter(fn (PpmpItem $i) => ($i->fundSource?->fund_group ?? FundGroup::Regular) === $fund);
        $lines = $pap->budgetLines->filter(fn ($l) => ($l->fundSource?->fund_group ?? FundGroup::Regular) === $fund);

        // toBase(): plain arrays merged in (an Eloquent collection's merge() expects models)
        $mooeEntries = $items->toBase()->reject(fn ($i) => $i->allotment_class === AllotmentClass::Co)
            ->map(fn ($i) => [
                'class'    => (int) ($i->expense_class_id ?: ($i->allotment_class === AllotmentClass::Semi ? $semiId : 0)),
                'location' => $i->location,
                'cents'    => Money::toCents($i->estimated_budget),
            ])
            ->merge($lines->toBase()->map(fn ($l) => ['class' => (int) $l->expense_class_id, 'location' => $l->location, 'cents' => Money::toCents($l->amount)]));

        $byClass = fn ($entries) => $entries->groupBy('class')->map(fn ($g) => $g->sum('cents'))->all();
        $coItems = $items->filter(fn ($i) => $i->allotment_class === AllotmentClass::Co)->sortBy(['sort_order', 'id'])->values();

        // Per location only when some of the PAP's entries have one (e.g. MIS: Quezon City, Bacolod, La Granja)
        $locationGroups = fn ($entries, $key) => $entries->contains(fn ($e) => $e[$key] ?? null)
            ? $entries->groupBy(fn ($e) => $e[$key]?->id ?? 0)->sortBy(fn ($g, $id) => $id === 0 ? 'zzz' : $g->first()[$key]->name)
            : collect();

        return [
            'code'        => $pap->code,
            'title'       => $pap->title,
            // Performance Contract (Form A)
            'description' => null,
            'indicators'  => $pap->indicators,
            'co'          => $coItems->sum(fn ($i) => Money::toCents($i->estimated_budget)),
            'mooe'        => $mooeEntries->sum('cents'),
            'mooeByClass' => $byClass($mooeEntries),
            'mooeByLocation' => $locationGroups($mooeEntries, 'location')
                ->map(fn ($g) => ['location' => $g->first()['location'], 'byClass' => $byClass($g), 'total' => $g->sum('cents')])->values(),
            'coItems'     => $coItems,
            'coByLocation' => $coItems->contains(fn ($i) => $i->location_id)
                ? $coItems->groupBy(fn ($i) => $i->location_id ?? 0)->sortBy(fn ($g, $id) => $id === 0 ? 'zzz' : $g->first()->location->name)
                    ->map(fn ($g) => ['location' => $g->first()->location, 'items' => $g, 'total' => $g->sum(fn ($i) => Money::toCents($i->estimated_budget))])->values()
                : collect([['location' => null, 'items' => $coItems, 'total' => $coItems->sum(fn ($i) => Money::toCents($i->estimated_budget))]]),
        ];
    }

    /** co, mooe, total and MOOE per expense class of some PAP rows. */
    protected function sumRows(Collection $rows): array
    {
        $byClass = [];
        foreach ($rows as $row) {
            foreach ($row['mooeByClass'] as $class => $cents) {
                $byClass[$class] = ($byClass[$class] ?? 0) + $cents;
            }
        }

        return ['co' => $rows->sum('co'), 'mooe' => $rows->sum('mooe'), 'total' => $rows->sum('co') + $rows->sum('mooe'), 'mooeByClass' => $byClass];
    }
}
