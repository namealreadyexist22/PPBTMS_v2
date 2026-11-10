<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\BudgetProposalStatus;
use App\Exceptions\ProcurementException;
use App\Http\Controllers\Controller;
use App\Models\Procurement\BudgetLine;
use App\Models\Procurement\BudgetPap;
use App\Models\Procurement\BudgetProposal;
use App\Models\Procurement\ExpenseClass;
use App\Models\Procurement\FundSource;
use App\Models\Procurement\Location;
use App\Models\Procurement\Office;
use App\Models\User;
use App\Services\Procurement\BudgetProposalService;
use App\Services\Procurement\CobReportService;
use App\Services\Procurement\CobSignatoryService;
use App\Support\Money;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Budget Proposal of a department: its offices' PAP titles, budget lines (MOOE not procured) and
 * performance targets (Form A); submitted to the evaluator, who gives the PAP codes and evaluates it.
 * Its printout (Summary, Forms A, B, C) and who submits it are here too.
 */
class BudgetProposalController extends Controller
{
    public function __construct(
        protected BudgetProposalService $proposals
    ) {}

    /** The departments' proposals for a year: the user's own department, or every one for the evaluators and budget office. */
    public function index(Request $request)
    {
        $user = $request->user();
        $fiscalYear = (int) ($request->input('fy') ?: now()->year + 1);
        $seesAll = $this->proposals->canEvaluate($user) || $user->canAccessPermission('manage budget');
        $offices = Office::active()->get()->keyBy('id');

        $departments = Office::active()->where('type', 'department')->get()
            ->filter(fn (Office $d) => $seesAll || $user->hasRole('Super Admin') || in_array((int) $user->office_id, $d->departmentOfficeIds(), true))
            ->sortBy(fn (Office $d) => $d->code ?? collect($d->departmentOfficeIds())->map(fn ($id) => $offices->get($id)?->code)->filter()->min() ?? 'zzzzz')
            ->values();

        $proposals = BudgetProposal::where('fiscal_year', $fiscalYear)->whereIn('department_id', $departments->pluck('id'))
            ->withCount('paps')->get()->keyBy('department_id');
        $lineTotals = BudgetLine::whereHas('pap.proposal', fn ($q) => $q->where('fiscal_year', $fiscalYear))->with('pap:id,budget_proposal_id')->get()
            ->groupBy(fn ($l) => $l->pap->budget_proposal_id)->map(fn ($lines) => $lines->sum(fn ($l) => Money::toCents($l->amount)));

        return view('procurement.budget_proposals.index', [
            'fiscalYear' => $fiscalYear,
            'years'      => range(now()->year - 1, now()->year + 2),
            'rows'       => $departments->map(fn (Office $d) => [
                'department' => $d,
                'proposal'   => $proposals->get($d->id),
                'lines'      => ($p = $proposals->get($d->id)) ? ($lineTotals[$p->id] ?? 0) : 0,
                'canOpen'    => $proposals->has($d->id) || $user->hasRole('Super Admin') || in_array((int) $user->office_id, $d->departmentOfficeIds(), true),
            ]),
            'canEvaluate' => $this->proposals->canEvaluate($user),
        ]);
    }

    /** Open the department's proposal for the year, starting it when missing (its own staff). */
    public function open(Request $request)
    {
        $data = $this->validateJson($request, ['department_id' => ['required', 'integer', 'exists:offices,id'], 'fiscal_year' => ['required', 'integer', 'between:2000,2100']]);
        $department = Office::findOrFail($data['department_id']);
        abort_unless($department->isDepartment(), 422);
        $user = $request->user();

        $existing = BudgetProposal::where('department_id', $department->id)->where('fiscal_year', $data['fiscal_year'])->first();
        if (! $existing && ! $user->hasRole('Super Admin') && ! in_array((int) $user->office_id, $department->departmentOfficeIds(), true)) {
            $this->deny($request, "Only {$department->shortName()} can start its Budget Proposal.");
        }

        $proposal = $existing ?? $this->proposals->forDepartment($department, (int) $data['fiscal_year'], $user);

        return response()->json(['status' => 'success', 'url' => route('procurement.budget-proposals.show', $proposal)]);
    }

    public function show(Request $request, BudgetProposal $proposal)
    {
        $user = $this->authorizeView($request, $proposal);
        $proposal->load(['department', 'paps.office', 'paps.budgetLines.expenseClass', 'paps.budgetLines.location', 'paps.budgetLines.fundSource', 'submitter', 'evaluator']);
        $proposal->paps->loadCount(['indicators', 'ppmpPaps']);
        $department = $proposal->department;
        $canEvaluate = $this->proposals->canEvaluate($user) && $proposal->status === BudgetProposalStatus::Submitted;

        // Offices of the department that can have PAPs, top down, each with its PAPs
        $units = collect(Office::groupedByDepartment())->firstWhere('department.id', $department->id)['units'] ?? [];
        $papsByOffice = $proposal->paps->groupBy('office_id');
        // What the PPMPs (latest versions, drafts too) put under each PAP: [budget_pap_id => [co, mooe]]
        $planned = app(CobReportService::class)->itemsByPap($department->departmentOfficeIds(), $proposal->fiscal_year, true)
            ->map(fn ($items) => [
                'co'   => $items->filter(fn ($i) => $i->allotment_class === \App\Enums\AllotmentClass::Co)->sum(fn ($i) => Money::toCents($i->estimated_budget)),
                'mooe' => $items->reject(fn ($i) => $i->allotment_class === \App\Enums\AllotmentClass::Co)->sum(fn ($i) => Money::toCents($i->estimated_budget)),
            ]);

        return view('procurement.budget_proposals.show', [
            'proposal'     => $proposal,
            'department'   => $department,
            'offices'      => collect($units)->filter(fn ($u) => $u['office']->code || $papsByOffice->has($u['office']->id))->values(),
            'papsByOffice' => $papsByOffice,
            'planned'      => $planned,
            'myOffices'    => $proposal->status->isEditable() ? $this->proposals->officesFor($proposal, $user) : collect(),
            'canEditPap'   => fn (BudgetPap $pap) => $this->proposals->canEditPap($pap->setRelation('proposal', $proposal), $user),
            'canSubmit'    => $this->proposals->canSubmit($proposal, $user),
            'canEvaluate'  => $canEvaluate,
            'canReturn'    => $this->proposals->canEvaluate($user) && in_array($proposal->status, [BudgetProposalStatus::Submitted, BudgetProposalStatus::Evaluated], true),
            'suggested'    => $canEvaluate ? $this->proposals->suggestCodes($proposal) : [],
            // Who submits the printed proposal (none set: the department head)
            'canSetSubmitters' => $this->proposals->isOfDepartment($proposal, $user) || $user->canAccessPermission('manage budget'),
            'submitterIds' => app(CobSignatoryService::class)->submitterIds($department),
            'submitters'   => app(CobSignatoryService::class)->submitters($department),
            'users'        => User::where('is_activated', true)->orderBy('fname')->orderBy('lname')->get(['id', 'fullname', 'designation']),
            'lineTotal'    => $proposal->paps->flatMap->budgetLines->sum(fn ($l) => Money::toCents($l->amount)),
        ]);
    }

    /** The printout: Summary, Performance Contract (Form A), Details of MOOE (Form B) and of CO (Form C). */
    public function print(Request $request, BudgetProposal $proposal)
    {
        $this->authorizeView($request, $proposal);
        $drafts = $request->boolean('drafts');
        $department = $proposal->department;

        return view('procurement.budget.cob_proposal', app(CobReportService::class)->proposal($department, $proposal->fiscal_year, $drafts) + [
            'fiscalYear'  => $proposal->fiscal_year,
            'drafts'      => $drafts,
            'years'       => [],
            'departments' => collect(),
            'signatories' => app(CobSignatoryService::class)->all(),
            'submitters'  => app(CobSignatoryService::class)->submitters($department),
            'proposalUrl' => fn (Office $d, array $query) => route('procurement.budget-proposals.print', [$proposal] + $query),
            'backUrl'     => route('procurement.budget-proposals.show', $proposal),
        ]);
    }

    // ----- PAPs -----

    /** Add a PAP (title) for one of the user's offices, or rename one. */
    public function papStore(Request $request, BudgetProposal $proposal)
    {
        $user = $this->authorizeView($request, $proposal);
        $data = $this->validateJson($request, [
            'id'        => ['nullable', 'integer'],
            'office_id' => ['nullable', 'integer'],
            'title'     => ['required', 'string', 'max:255'],
        ], [], ['title' => 'PAP title']);

        return $this->attempt(function () use ($request, $proposal, $user, $data) {
            if ($request->filled('id')) {
                $pap = $this->papOf($proposal, $request->integer('id'));
                $this->assertCanEditPap($request, $pap, $user);
                $this->proposals->renamePap($pap, $data['title']);

                return 'PAP renamed.';
            }

            $office = $this->proposals->officesFor($proposal, $user)->firstWhere('id', (int) ($data['office_id'] ?? 0));
            if (! $office) {
                throw new ProcurementException('You can add PAPs for your own office only.');
            }
            $this->proposals->addPap($proposal, $office, $data['title']);

            return "PAP added for {$office->shortName()}.";
        });
    }

    public function papDestroy(Request $request, BudgetProposal $proposal)
    {
        $user = $this->authorizeView($request, $proposal);
        $pap = $this->papOf($proposal, $request->integer('id'));
        $this->assertCanEditPap($request, $pap, $user);

        return $this->attempt(function () use ($pap) {
            $this->proposals->removePap($pap);

            return 'PAP removed.';
        });
    }

    /** A PAP's Performance Contract entry (Form A), as its own page. */
    public function performance(Request $request, BudgetProposal $proposal, int $pap)
    {
        $user = $this->authorizeView($request, $proposal);
        $pap = $this->papOf($proposal, $pap)->load(['indicators', 'office']);

        return view('procurement.budget_proposals.performance', [
            'proposal' => $proposal->load('department'),
            'pap'      => $pap,
            // To go to another PAP of the same office, with how many indicators each has
            'paps'     => $proposal->paps()->where('office_id', $pap->office_id)->withCount('indicators')->get(),
            'canEdit'  => $this->proposals->canEditPap($pap, $user),
        ]);
    }

    public function performanceStore(Request $request, BudgetProposal $proposal, int $pap)
    {
        $user = $this->authorizeView($request, $proposal);
        $pap = $this->papOf($proposal, $pap);
        $this->assertCanEditPap($request, $pap, $user);
        $data = $this->validateJson($request, [
            'indicators'               => ['nullable', 'array', 'max:100'],
            'indicators.*.description' => ['nullable', 'string', 'max:3000'],
            'indicators.*.indicator'   => ['nullable', 'string', 'max:500'],
            'indicators.*.q1'          => ['nullable', 'string', 'max:30'],
            'indicators.*.q2'          => ['nullable', 'string', 'max:30'],
            'indicators.*.q3'          => ['nullable', 'string', 'max:30'],
            'indicators.*.q4'          => ['nullable', 'string', 'max:30'],
            'indicators.*.total'       => ['nullable', 'string', 'max:30'],
        ], [], ['indicators.*.indicator' => 'performance indicator']);

        return $this->attempt(function () use ($pap, $data) {
            $this->proposals->savePerformance($pap, $data['indicators'] ?? []);

            return "Performance targets of \"{$pap->title}\" saved.";
        });
    }

    // ----- Budget lines -----

    public function lineEntry(Request $request, BudgetProposal $proposal)
    {
        $user = $this->authorizeView($request, $proposal);
        $line = $request->filled('id') ? $this->lineOf($proposal, $request->integer('id')) : null;
        $pap = $line?->pap ?? $this->papOf($proposal, $request->integer('pap_id'));
        $this->assertCanEditPap($request, $pap, $user);

        $activeOr = fn (string $model, ?int $current) => $model::where(fn ($q) => $q->where('is_active', true)->when($current, fn ($q) => $q->orWhereKey($current)));
        $budgetFund = $proposal->department->budget_fund;
        $fundSources = $activeOr(FundSource::class, $line?->fund_source_id)->get()
            ->filter(fn ($fund) => $fund->id === $line?->fund_source_id || ($fund->allows($pap->office) && (! $budgetFund || $fund->fund_group === $budgetFund)))
            ->sortBy(fn ($fund) => [$fund->code === 'COB' ? 0 : 1, $fund->name])->values();

        return view('procurement.budget_proposals.extras.line_entry', [
            'proposal'       => $proposal,
            'line'           => $line,
            'pap'            => $pap,
            // The same office's PAPs, to move a line to another
            'paps'           => $proposal->paps()->where('office_id', $pap->office_id)->get(),
            'expenseClasses' => $activeOr(ExpenseClass::class, $line?->expense_class_id)->ordered()->get(),
            'locations'      => $activeOr(Location::class, $line?->location_id)->orderBy('name')->get(),
            'fundSources'    => $fundSources,
            'defaultFundId'  => $line?->fund_source_id ?? $fundSources->first()?->id,
        ]);
    }

    public function lineStore(Request $request, BudgetProposal $proposal)
    {
        $user = $this->authorizeView($request, $proposal);
        $request->merge(['amount' => str_replace(',', '', (string) $request->input('amount'))]);
        $data = $this->validateJson($request, [
            'id'               => ['nullable', 'integer'],
            'budget_pap_id'    => ['required', 'integer'],
            'expense_class_id' => ['required', 'integer', 'exists:expense_classes,id'],
            'location_id'      => ['nullable', 'integer', 'exists:locations,id'],
            'fund_source_id'   => ['required', 'integer', 'exists:fund_sources,id'],
            'description'      => ['nullable', 'string', 'max:1000'],   // a label only; the printouts total by expense class
            'amount'           => ['required', 'numeric', 'gt:0', 'max:9999999999999.99'],
            'remarks'          => ['nullable', 'string', 'max:1000'],
        ], [], ['budget_pap_id' => 'PAP', 'expense_class_id' => 'expense class', 'location_id' => 'location', 'fund_source_id' => 'source of funds']);

        $pap = $this->papOf($proposal, (int) $data['budget_pap_id']);
        $line = $request->filled('id') ? $this->lineOf($proposal, $request->integer('id')) : null;
        $this->assertCanEditPap($request, $line?->pap ?? $pap, $user);
        $this->assertCanEditPap($request, $pap, $user);

        return $this->attempt(function () use ($pap, $data, $line) {
            $this->proposals->saveBudgetLine($pap, $data, $line);

            return $line ? 'Budget line updated.' : 'Budget line added.';
        });
    }

    public function lineDestroy(Request $request, BudgetProposal $proposal)
    {
        $user = $this->authorizeView($request, $proposal);
        $line = $this->lineOf($proposal, $request->integer('id'));
        $this->assertCanEditPap($request, $line->pap, $user);

        return $this->attempt(function () use ($line) {
            $this->proposals->removeBudgetLine($line);

            return 'Budget line removed.';
        });
    }

    // ----- Workflow -----

    public function submit(Request $request, BudgetProposal $proposal)
    {
        $user = $this->authorizeView($request, $proposal);
        if (! $this->proposals->isOfDepartment($proposal, $user)) {
            $this->deny($request, "Only {$proposal->department->shortName()} can submit its Budget Proposal.");
        }

        return $this->attempt(function () use ($proposal, $user) {
            $this->proposals->submit($proposal, $user);
            activity()->causedBy($user)->performedOn($proposal)->log("submitted the FY {$proposal->fiscal_year} Budget Proposal of {$proposal->department->shortName()}");

            return 'Budget Proposal submitted for evaluation.';
        });
    }

    /** The evaluator saves the PAP codes. */
    public function codes(Request $request, BudgetProposal $proposal)
    {
        $user = $this->authorizeEvaluator($request);
        $data = $this->validateJson($request, ['codes' => ['nullable', 'array'], 'codes.*' => ['nullable', 'string', 'max:50']]);

        return $this->attempt(function () use ($proposal, $user, $data) {
            $this->proposals->setCodes($proposal, $data['codes'] ?? [], $user);

            return 'PAP codes saved.';
        });
    }

    public function evaluate(Request $request, BudgetProposal $proposal)
    {
        $user = $this->authorizeEvaluator($request);

        return $this->attempt(function () use ($proposal, $user) {
            $this->proposals->evaluate($proposal, $user);
            activity()->causedBy($user)->performedOn($proposal)->log("evaluated the FY {$proposal->fiscal_year} Budget Proposal of {$proposal->department->shortName()}");

            return 'Budget Proposal evaluated.';
        });
    }

    public function returnToDepartment(Request $request, BudgetProposal $proposal)
    {
        $user = $this->authorizeEvaluator($request);
        $data = $this->validateJson($request, ['remarks' => ['required', 'string', 'max:1000']], ['remarks.required' => 'Please state why it is returned.']);

        return $this->attempt(function () use ($proposal, $user, $data) {
            $this->proposals->returnToDepartment($proposal, $user, $data['remarks']);

            return "Budget Proposal returned to {$proposal->department->shortName()}.";
        });
    }

    /** Who submits the printed proposal (up to two; none = the department head). */
    public function submitters(Request $request, BudgetProposal $proposal)
    {
        $user = $this->authorizeView($request, $proposal);
        if (! $this->proposals->isOfDepartment($proposal, $user) && ! $user->canAccessPermission('manage budget')) {
            $this->deny($request, 'You cannot set who submits this Budget Proposal.');
        }
        $data = $this->validateJson($request, ['users' => ['nullable', 'array', 'max:2'], 'users.*' => ['integer', 'exists:users,id']], ['users.max' => 'Pick at most two people.']);

        app(CobSignatoryService::class)->setSubmitters($proposal->department, $data['users'] ?? []);
        activity()->causedBy($user)->log("set who submits the budget proposal of {$proposal->department->shortName()}");

        return response()->json(['status' => 'success', 'message' => 'Submitted by saved.']);
    }

    // ----- Helpers -----

    protected function papOf(BudgetProposal $proposal, int $id): BudgetPap
    {
        return $proposal->paps()->with('office')->findOrFail($id)->setRelation('proposal', $proposal);
    }

    protected function lineOf(BudgetProposal $proposal, int $id): BudgetLine
    {
        $line = BudgetLine::with('pap.office')->findOrFail($id);
        abort_unless((int) $line->pap->budget_proposal_id === (int) $proposal->id, 404);
        $line->pap->setRelation('proposal', $proposal);

        return $line;
    }

    protected function authorizeView(Request $request, BudgetProposal $proposal): User
    {
        $user = $request->user();
        if (! $this->proposals->canView($proposal, $user)) {
            $this->deny($request, 'You do not have access to this Budget Proposal.');
        }

        return $user;
    }

    protected function authorizeEvaluator(Request $request): User
    {
        $user = $request->user();
        if (! $this->proposals->canEvaluate($user)) {
            $this->deny($request, 'Only the evaluator (Budget and Treasury) can do this.');
        }

        return $user;
    }

    protected function assertCanEditPap(Request $request, BudgetPap $pap, User $user): void
    {
        if (! $this->proposals->canEditPap($pap, $user)) {
            $this->deny($request, $pap->proposal->status->isEditable()
                ? "Only {$pap->office->shortName()} can change its PAP."
                : "The Budget Proposal is {$pap->proposal->status->value}; it can no longer be changed.");
        }
    }

    protected function attempt(Closure $action): JsonResponse
    {
        try {
            return response()->json(['status' => 'success', 'message' => $action()]);
        } catch (ProcurementException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    protected function deny(Request $request, string $message): never
    {
        abort($request->expectsJson() ? response()->json(['status' => 'error', 'message' => $message], 403) : 403, $message);
    }

    protected function validateJson(Request $request, array $rules, array $messages = [], array $attributes = []): array
    {
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);
        if ($validator->fails()) {
            throw new HttpResponseException(response()->json(['status' => 'error', 'errors' => $validator->errors()], 422));
        }

        return $validator->validated();
    }
}
