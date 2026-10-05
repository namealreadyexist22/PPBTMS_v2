<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\AllotmentClass;
use App\Enums\FundGroup;
use App\Exceptions\ProcurementException;
use App\Http\Controllers\Controller;
use App\Models\Procurement\BudgetAllocation;
use App\Models\Procurement\Office;
use App\Services\Procurement\BudgetAllocationService;
use App\Support\Money;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Budget officer's page: approved budget per office, year and fund (COB / SIDA), in MOOE and CO. */
class BudgetAllocationController extends Controller
{
    public function __construct(
        protected BudgetAllocationService $budget
    ) {}

    public function index(Request $request)
    {
        $fiscalYear = (int) ($request->input('fy') ?: now()->year + 1);
        $fund = FundGroup::tryFrom((string) $request->input('fund')) ?? FundGroup::Regular;

        $allocations = BudgetAllocation::with(['office.parent', 'history.user'])
            ->where('fiscal_year', $fiscalYear)->where('fund_group', $fund)->get()
            ->sortBy(fn ($a) => $a->office->code)
            ->map(function (BudgetAllocation $a) use ($fiscalYear, $fund) {
                $children = $this->budget->childAllocations($a->office, $fiscalYear, $fund);
                $row = ['allocation' => $a, 'depth' => $this->depth($a, $fiscalYear, $fund)];

                foreach ([AllotmentClass::Co, AllotmentClass::Mooe] as $class) {
                    $amount = Money::toCents($this->budget->amount($a, $class));
                    $used = $this->budget->usedCents($a, $class);
                    $row[$class->value] = [
                        'amount'    => $amount,
                        'given'     => $children->sum(fn ($c) => Money::toCents($this->budget->amount($c, $class))),
                        'used'      => $used,
                        'remaining' => $amount - $used,
                    ];
                }

                return $row;
            })->values();

        return view('procurement.budget.index', [
            'allocations' => $allocations,
            'fiscalYear'  => $fiscalYear,
            'fund'        => $fund,
            'years'       => range(now()->year - 1, now()->year + 2),
            'offices'     => Office::active()->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request)
    {
        foreach (['mooe_amount', 'co_amount'] as $field) {
            $request->merge([$field => str_replace(',', '', (string) $request->input($field))]);
        }

        $validator = Validator::make($request->all(), [
            'office_id'   => ['required', 'integer', 'exists:offices,id'],
            'fiscal_year' => ['required', 'integer', 'between:2000,2100'],
            'fund_group'  => ['required', Rule::enum(FundGroup::class)],
            'mooe_amount' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'co_amount'   => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'reason'      => ['nullable', 'string', 'max:1000'],
        ], [], ['mooe_amount' => 'MOOE', 'co_amount' => 'CO', 'office_id' => 'office']);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json(['status' => 'error', 'errors' => $validator->errors()], 422));
        }

        $data = $validator->validated();

        try {
            $allocation = $this->budget->save(
                Office::findOrFail($data['office_id']), (int) $data['fiscal_year'], FundGroup::from($data['fund_group']),
                $data['mooe_amount'], $data['co_amount'], $request->user(), $data['reason'] ?? null,
            );

            activity()->causedBy($request->user())->performedOn($allocation)
                ->log("set {$allocation->fund_group->label()} budget of {$allocation->office->code} for FY {$allocation->fiscal_year}");

            return response()->json(['status' => 'success', 'message' => "Budget of {$allocation->office->shortName()} saved."]);
        } catch (ProcurementException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /** How many allocated offices are above this one (for indenting the list). */
    protected function depth(BudgetAllocation $allocation, int $fiscalYear, FundGroup $fund): int
    {
        $depth = 0;
        for ($parent = $this->budget->parentAllocation($allocation->office, $fiscalYear, $fund); $parent; $parent = $this->budget->parentAllocation($parent->office, $fiscalYear, $fund)) {
            $depth++;
        }

        return $depth;
    }
}
