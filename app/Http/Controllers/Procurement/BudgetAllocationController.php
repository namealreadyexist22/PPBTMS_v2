<?php

namespace App\Http\Controllers\Procurement;

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

/** Budget officer's page: approved budget per office, year and fund (COB / SIDA), CO and MOOE together, per department. */
class BudgetAllocationController extends Controller
{
    public function __construct(
        protected BudgetAllocationService $budget
    ) {}

    public function index(Request $request)
    {
        $fiscalYear = (int) ($request->input('fy') ?: now()->year + 1);
        $fund = FundGroup::tryFrom((string) $request->input('fund')) ?? FundGroup::Regular;

        $allocations = BudgetAllocation::with('history.user')->where('fiscal_year', $fiscalYear)->where('fund_group', $fund)->get()->keyBy('office_id');
        $offices = Office::active()->get()->keyBy('id');

        // One row per department, with what each of its offices uses
        $rows = Office::active()->where('is_department', true)
            ->where(fn ($q) => $fund === FundGroup::Regular ? $q->where('budget_fund', $fund)->orWhereNull('budget_fund') : $q->where('budget_fund', $fund))
            ->orderBy('code')->get()
            ->map(function (Office $department) use ($allocations, $offices) {
                $allocation = $allocations->get($department->id)?->setRelation('office', $department);
                $memberIds = $department->departmentOfficeIds();
                $usedBy = $allocation ? $this->budget->usedByOffice($allocation) : [];
                $amount = $allocation ? Money::toCents((string) $allocation->amount) : null;
                $used = array_sum($usedBy);

                return [
                    'department' => $department,
                    'allocation' => $allocation,
                    'amount'     => $amount,
                    'used'       => $used,
                    'remaining'  => $amount === null ? null : $amount - $used,
                    'offices'    => collect($memberIds)->map(fn ($id) => $offices->get($id))->filter()->sortBy('code')
                                    ->map(fn (Office $o) => ['office' => $o, 'used' => $usedBy[$o->id] ?? 0])->values(),
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
            'office_id'   => ['required', 'integer', 'exists:offices,id'],
            'fiscal_year' => ['required', 'integer', 'between:2000,2100'],
            'fund_group'  => ['required', Rule::enum(FundGroup::class)],
            'amount'      => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'reason'      => ['nullable', 'string', 'max:1000'],
        ], [], ['amount' => 'budget', 'office_id' => 'office']);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json(['status' => 'error', 'errors' => $validator->errors()], 422));
        }

        $data = $validator->validated();

        try {
            $allocation = $this->budget->save(
                Office::findOrFail($data['office_id']), (int) $data['fiscal_year'], FundGroup::from($data['fund_group']),
                $data['amount'], $request->user(), $data['reason'] ?? null,
            );

            activity()->causedBy($request->user())->performedOn($allocation)
                ->log("set {$allocation->fund_group->label()} budget of {$allocation->office->code} for FY {$allocation->fiscal_year}");

            return response()->json(['status' => 'success', 'message' => "Budget of {$allocation->office->shortName()} saved."]);
        } catch (ProcurementException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }
}
