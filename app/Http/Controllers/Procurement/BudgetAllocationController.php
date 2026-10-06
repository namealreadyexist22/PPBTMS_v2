<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\FundGroup;
use App\Exceptions\ProcurementException;
use App\Http\Controllers\Controller;
use App\Models\Procurement\BudgetAllocation;
use App\Models\Procurement\Department;
use App\Models\Procurement\Office;
use App\Services\Procurement\BudgetAllocationService;
use App\Support\Money;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Budget officer's page: approved budget per department, year and fund (COB / SIDA), CO and MOOE together. */
class BudgetAllocationController extends Controller
{
    public function __construct(
        protected BudgetAllocationService $budget
    ) {}

    public function index(Request $request)
    {
        $fiscalYear = (int) ($request->input('fy') ?: now()->year + 1);
        $fund = FundGroup::tryFrom((string) $request->input('fund')) ?? FundGroup::Regular;

        $allocations = BudgetAllocation::with('history.user')->where('fiscal_year', $fiscalYear)->where('fund_group', $fund)->get()->keyBy('department_id');

        // One row per department of this fund (in office-number order), with what each of its offices uses
        $rows = Department::active()->where('fund_group', $fund)
            ->with(['offices' => fn ($q) => $q->orderBy('code')])->get()
            ->sortBy(fn (Department $d) => $d->offices->first()?->code ?? 'zzz')->values()
            ->map(function (Department $department) use ($allocations) {
                $allocation = $allocations->get($department->id)?->setRelation('department', $department);
                $usedBy = $allocation ? $this->budget->usedByOffice($allocation) : [];
                $amount = $allocation ? Money::toCents((string) $allocation->amount) : null;
                $used = array_sum($usedBy);

                return [
                    'department' => $department,
                    'allocation' => $allocation,
                    'amount'     => $amount,
                    'used'       => $used,
                    'remaining'  => $amount === null ? null : $amount - $used,
                    'offices'    => $department->offices->map(fn (Office $o) => ['office' => $o, 'used' => $usedBy[$o->id] ?? 0]),
                ];
            });

        return view('procurement.budget.index', [
            'rows'       => $rows,
            'fiscalYear' => $fiscalYear,
            'fund'       => $fund,
            'years'      => range(now()->year - 1, now()->year + 2),
            'totals'     => ['amount' => $rows->sum('amount'), 'used' => $rows->whereNotNull('allocation')->sum('used')],
        ]);
    }

    public function store(Request $request)
    {
        $request->merge(['amount' => str_replace(',', '', (string) $request->input('amount'))]);

        $validator = Validator::make($request->all(), [
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'fiscal_year'   => ['required', 'integer', 'between:2000,2100'],
            'fund_group'    => ['required', Rule::enum(FundGroup::class)],
            'amount'        => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'reason'        => ['nullable', 'string', 'max:1000'],
        ], [], ['amount' => 'budget', 'department_id' => 'department']);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json(['status' => 'error', 'errors' => $validator->errors()], 422));
        }

        $data = $validator->validated();

        try {
            $allocation = $this->budget->save(
                Department::findOrFail($data['department_id']), (int) $data['fiscal_year'], FundGroup::from($data['fund_group']),
                $data['amount'], $request->user(), $data['reason'] ?? null,
            );

            activity()->causedBy($request->user())->performedOn($allocation)
                ->log("set {$allocation->fund_group->label()} budget of {$allocation->department->code} for FY {$allocation->fiscal_year}");

            return response()->json(['status' => 'success', 'message' => "Budget of {$allocation->department->code} saved."]);
        } catch (ProcurementException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }
}
