<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\PpmpStatus;
use App\Enums\RequestKind;
use App\Enums\RequestStatus;
use App\Exceptions\ProcurementException;
use App\Http\Controllers\Controller;
use App\Models\Procurement\Office;
use App\Models\Procurement\Ppmp;
use App\Models\Procurement\PpmpPap;
use App\Models\Procurement\PurchaseRequest;
use App\Models\Procurement\PurchaseRequestEvent;
use App\Models\User;
use App\Services\Procurement\PurchaseRequestService;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Purchase Requests (PR) and Job Requests (JR): list, workspace, lines, submit / revise / cancel, print. */
class PurchaseRequestController extends Controller
{
    public function __construct(
        protected PurchaseRequestService $service
    ) {}

    public function index(Request $request)
    {
        $kind = RequestKind::tryFrom((string) $request->query('kind')) ?? RequestKind::Pr;
        $status = RequestStatus::tryFrom((string) $request->query('status'));
        $search = trim((string) $request->query('q'));

        $requests = PurchaseRequest::visibleTo($request->user())
            ->with(['office', 'pap', 'items'])
            ->where('kind', $kind)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when(! $status, fn ($q) => $q->where('status', '!=', RequestStatus::Superseded))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('request_no', 'like', "%{$search}%")->orWhere('purpose', 'like', "%{$search}%")))
            ->orderByRaw('request_no is null desc')
            ->orderByDesc('series_year')->orderByDesc('series')->orderByDesc('revision')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        return view('procurement.requests.index', [
            'kind'     => $kind,
            'status'   => $status,
            'search'   => $search,
            'requests' => $requests,
            'counts'   => PurchaseRequest::visibleTo($request->user())->where('status', '!=', RequestStatus::Superseded)
                ->selectRaw('kind, count(*) as total')->groupBy('kind')->pluck('total', 'kind'),
        ]);
    }

    /** New request modal: kind and the PAP to charge, from the approved PPMPs of the user's offices. */
    public function entry(Request $request)
    {
        $user = $request->user();
        $officeIds = $user->hasRole('Super Admin') ? null : $user->prOfficeIds();

        $ppmps = Ppmp::with(['office', 'paps' => fn ($q) => $q->withCount('items')])
            ->where('status', PpmpStatus::Approved)
            ->when($officeIds !== null, fn ($q) => $q->whereIn('office_id', $officeIds))
            ->orderByDesc('fiscal_year')->orderBy('ppmp_no')
            ->get();

        return view('procurement.requests.extras.request_entry', [
            'modalName' => 'REQUEST_ENTRY_MODAL',
            'kind'      => RequestKind::tryFrom((string) $request->query('kind')) ?? RequestKind::Pr,
            'ppmps'     => $ppmps,
            'jrTypes'   => config('procurement.jr_types'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateJson($request, [
            'kind'        => ['required', Rule::enum(RequestKind::class)],
            'ppmp_pap_id' => ['required', 'integer', 'exists:ppmp_paps,id'],
            'jr_type'     => ['nullable', Rule::in(array_keys(config('procurement.jr_types')))],
            'purpose'     => ['nullable', 'string', 'max:2000'],
        ], ['ppmp_pap_id.required' => 'Choose the PAP to charge.'], ['ppmp_pap_id' => 'charge to']);

        $kind = RequestKind::from($data['kind']);
        $data += $this->defaultSignatories($request->user(), PpmpPap::findOrFail($data['ppmp_pap_id'])->ppmp->office, $kind);

        try {
            $pr = $this->service->create($kind, PpmpPap::findOrFail($data['ppmp_pap_id']), $request->user(), $data);

            return response()->json([
                'status'  => 'success',
                'message' => "{$kind->label()} started. Add its items.",
                'url'     => route('procurement.requests.show', $pr),
            ]);
        } catch (ProcurementException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    public function show(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeView($request, $purchaseRequest);
        $pr = $purchaseRequest->load(['office.parent.parent', 'pap', 'ppmp', 'items.ppmpItem.procurementMode', 'revisedFrom', 'creator']);
        $canEdit = $pr->isEditableBy($request->user());

        return view('procurement.requests.show', [
            'pr'           => $pr,
            'canEdit'      => $canEdit && $pr->isEditable(),
            'canManage'    => $canEdit,
            'openRevision' => $pr->status === RequestStatus::Submitted ? $this->service->openRevision($pr) : null,
            'revisions'    => $pr->request_no
                ? PurchaseRequest::where('kind', $pr->kind)->where('series_year', $pr->series_year)->where('series', $pr->series)->orderBy('revision')->get(['uuid', 'revision', 'status'])
                : collect(),
            'jrTypes'      => config('procurement.jr_types'),
            'lines'        => $canEdit && $pr->isEditable() ? $this->linesOrError($pr) : collect(),
            'people'       => $this->signatoryPeople($pr->office),
            // Being quoted: revise / cancel wait until its items are off the RFQ
            'rfq'          => $pr->status === RequestStatus::Submitted ? app(\App\Services\Procurement\RfqService::class)->openRfqFor($pr) : null,
        ]);
    }

    /** The PPMP the request is charged to, read-only in a modal. */
    public function ppmpModal(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeView($request, $purchaseRequest);
        $ppmp = $purchaseRequest->ppmp->load(['office.parent.parent', 'paps.items.unit', 'paps.items.procurementMode', 'paps.items.fundSource']);

        return view('procurement.requests.extras.ppmp_modal', [
            'ppmp'    => $ppmp,
            'pr'      => $purchaseRequest,
            'canOpen' => $request->user()->canAccessPermission('manage ppmp'),
        ]);
    }

    /** The open RFQ the request's items are on, read-only in a modal. */
    public function rfqModal(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeView($request, $purchaseRequest);
        $rfq = app(\App\Services\Procurement\RfqService::class)->openRfqFor($purchaseRequest);
        abort_unless($rfq, 404, 'The request is not on an RFQ.');

        return view('procurement.rfqs.extras.details_modal', [
            'rfq'     => $rfq->load(['procurementMode', 'items.line.request']),
            'canOpen' => $request->user()->canAccessPermission(\App\Models\Procurement\Rfq::MANAGE_PERMISSION),
        ]);
    }

    /** The status trail in a modal, from the list or the request's page. */
    public function trailModal(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeView($request, $purchaseRequest);

        return view('procurement.requests.extras.trail_modal', ['pr' => $purchaseRequest, 'events' => $this->trail($purchaseRequest)]);
    }

    /** The status trail of the request and the revisions before it. */
    protected function trail(PurchaseRequest $pr): \Illuminate\Support\Collection
    {
        $ids = [$pr->id];
        for ($at = $pr; $at->revised_from_id && count($ids) < 50; $at = $at->revisedFrom) {
            $ids[] = $at->revised_from_id;
        }

        return PurchaseRequestEvent::with(['user', 'request'])->whereIn('purchase_request_id', $ids)
            ->orderBy('created_at')->orderBy('id')->get();
    }

    public function header(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeEdit($request, $purchaseRequest);
        $data = $this->validateJson($request, $this->headerRules($purchaseRequest));

        return $this->attempt(function () use ($request, $purchaseRequest, $data) {
            $this->service->updateHeader($purchaseRequest, $request->user(), $data);

            return 'Saved.';
        });
    }

    /** Signatories set from the Print dialog (draft or submitted). */
    public function signatories(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeEdit($request, $purchaseRequest);
        $data = $this->validateJson($request, $this->signatoryRules(true));

        return $this->attempt(function () use ($request, $purchaseRequest, $data) {
            $this->service->updateSignatories($purchaseRequest, $request->user(), $data);

            return 'Signatories saved.';
        });
    }

    public function lineEntry(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeEdit($request, $purchaseRequest);
        $line = $request->filled('id') ? $purchaseRequest->items()->findOrFail($request->id) : null;

        // What each project may still take on this line (editing: this line's own share counts back)
        try {
            $projects = $this->service->availableLines($purchaseRequest, $line);
        } catch (ProcurementException $e) {
            $this->deny($request, $e->getMessage());
        }

        return view('procurement.requests.extras.request_line_entry', [
            'modalName' => 'REQUEST_LINE_MODAL',
            'pr'        => $purchaseRequest,
            'line'      => $line,
            // Editing: the line's project in the current PPMP (by line_uuid: an amendment gives the projects new ids)
            'selected'  => $line ? $projects->firstWhere('line_uuid', $line->line_uuid)?->id
                : $projects->firstWhere('id', $request->integer('project'))?->id,
            'projects'  => $projects->map(fn ($p) => [
                'id'            => $p->id,
                'description'   => $p->description,
                'unit'          => $p->unit?->name,
                'unit_cost'     => $p->unit_cost !== null ? (float) $p->unit_cost : null,
                'standard'      => (bool) $p->item?->isStandard(),
                'specs'         => $p->item?->specifications,
                'mode'          => $p->effectiveMode()?->name,   // the BAC's in the approved APP, else the PPMP's
                'remaining'     => $p->remaining_cents / 100,
                'budget'        => $p->budget_cents / 100,
                'planned_qty'   => $p->planned_hundredths === null ? null : $p->planned_hundredths / 100,
                'remaining_qty' => $p->remaining_hundredths === null ? null : $p->remaining_hundredths / 100,
            ])->values(),
        ]);
    }

    public function lineStore(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeEdit($request, $purchaseRequest);
        $data = $this->validateJson($request, [
            'id'             => ['nullable', 'integer'],
            'ppmp_item_id'   => ['required', 'integer'],
            'stock_no'       => ['nullable', 'string', 'max:50'],
            'unit'           => ['required', 'string', 'max:50'],
            'description'    => ['required', 'string', 'max:500'],
            'specifications' => ['nullable', 'string', 'max:5000'],
            'quantity'       => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'unit_cost'      => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'nature_of_work' => ['nullable', 'string', 'max:2000'],
        ], ['ppmp_item_id.required' => 'Choose the PPMP project.'], ['ppmp_item_id' => 'PPMP project', 'stock_no' => $purchaseRequest->kind === RequestKind::Jr ? 'property no.' : 'stock no.']);

        return $this->attempt(function () use ($request, $purchaseRequest, $data) {
            $line = $request->filled('id') ? $purchaseRequest->items()->findOrFail($request->id) : null;
            $this->service->saveLine($purchaseRequest, $data, $line);

            return $line ? 'Item updated.' : 'Item added.';
        });
    }

    public function lineDestroy(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeEdit($request, $purchaseRequest);
        $this->validateJson($request, ['id' => ['required', 'integer']]);

        return $this->attempt(function () use ($request, $purchaseRequest) {
            $this->service->removeLine($purchaseRequest->items()->findOrFail($request->id));

            return 'Item removed.';
        });
    }

    public function submit(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeEdit($request, $purchaseRequest);
        $data = $this->validateJson($request, $this->headerRules($purchaseRequest));

        return $this->attempt(function () use ($request, $purchaseRequest, $data) {
            $this->service->updateHeader($purchaseRequest, $request->user(), $data);
            $pr = $this->service->submit($purchaseRequest->fresh(), $request->user());

            return "{$pr->title()} submitted. Its amount is now charged to the PPMP.";
        });
    }

    public function revise(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeEdit($request, $purchaseRequest);

        try {
            $copy = $this->service->revise($purchaseRequest, $request->user());

            return response()->json([
                'status'  => 'success',
                'message' => "{$copy->title()} created as a draft with the same signatories.",
                'url'     => route('procurement.requests.show', $copy),
            ]);
        } catch (ProcurementException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    public function cancel(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeEdit($request, $purchaseRequest);
        $data = $this->validateJson($request, ['reason' => ['required', 'string', 'max:500']], [
            'reason.required' => 'Please state the reason for cancelling.',
        ]);

        return $this->attempt(function () use ($request, $purchaseRequest, $data) {
            $this->service->cancel($purchaseRequest, $request->user(), $data['reason']);

            return "{$purchaseRequest->title()} cancelled. Its amount went back to the PPMP.";
        });
    }

    public function destroy(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeEdit($request, $purchaseRequest);

        try {
            $back = $purchaseRequest->revisedFrom ? route('procurement.requests.show', $purchaseRequest->revisedFrom)
                : route('procurement.requests.index', ['kind' => $purchaseRequest->kind->value]);
            $this->service->deleteDraft($purchaseRequest);

            return response()->json(['status' => 'success', 'message' => 'Draft deleted.', 'url' => $back]);
        } catch (ProcurementException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /** Printable PR / JR in the SRA forms (FM-AFD-PPS-003 / -001). */
    public function print(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeView($request, $purchaseRequest);

        return $this->printView($purchaseRequest);
    }

    /** The printed form, without access checks (callers check first, e.g. the RFQ's PR viewer). */
    public function printView(PurchaseRequest $purchaseRequest)
    {
        $pr = $purchaseRequest->load(['office.parent.parent', 'pap', 'items.ppmpItem.procurementMode']);
        $department = $pr->office->department;

        // Section / Unit: the units below the department, top down ("PPPD - MIS SECTION")
        $units = [];
        for ($office = $pr->office; $office && $office->id !== $department?->id; $office = $office->parent) {
            array_unshift($units, $office->name);
        }

        return view('procurement.requests.print', [
            'pr'         => $pr,
            'department' => $department ? ($department->acronym ?: $department->name) : $pr->office->shortName(),
            'section'    => implode(' - ', array_map('mb_strtoupper', $units)),
            'jrType'     => $pr->jr_type ? (config('procurement.jr_types')[$pr->jr_type] ?? $pr->jr_type) : null,
        ]);
    }

    /**
     * Requested by: the person preparing it. Approved by: the head of the department (or, if the
     * department has none set, the nearest head above the office). Both can be changed on the request.
     */
    protected function defaultSignatories(User $user, Office $office, RequestKind $kind): array
    {
        $headId = $office->department?->head_user_id ?: $office->approverId();
        $head = $headId ? User::find($headId) : null;

        return array_filter([
            'requested_by_name'        => $user->fullname,
            'requested_by_designation' => $user->designation,
            'approved_by_name'         => $head?->fullname,
            'approved_by_designation'  => $head?->designation,
        ]);
    }

    /** Names and designations used before by this office, for the signatory suggestions. */
    protected function signatoryNames(int $officeId): array
    {
        $rows = PurchaseRequest::where('office_id', $officeId)->latest('id')->limit(50)
            ->get(['requested_by_name', 'requested_by_designation', 'approved_by_name', 'approved_by_designation']);

        $pairs = [];
        foreach ($rows as $row) {
            foreach (['requested_by', 'approved_by'] as $who) {
                if ($name = $row->{"{$who}_name"}) {
                    $pairs[$name] ??= (string) $row->{"{$who}_designation"};
                }
            }
        }

        return $pairs;
    }

    /** Signatory picker: names used before by the office first, then everyone (the office's people first). */
    protected function signatoryPeople(Office $office): array
    {
        $past = collect($this->signatoryNames($office->id))->map(fn ($designation, $name) => ['name' => $name, 'designation' => $designation, 'office' => null])->values();

        return $past->merge($office->signatoryPeople())->unique('name')->values()->all();
    }

    protected function linesOrError(PurchaseRequest $pr): \Illuminate\Support\Collection|string
    {
        try {
            return $this->service->availableLines($pr);
        } catch (ProcurementException $e) {
            return $e->getMessage();
        }
    }

    protected function headerRules(PurchaseRequest $pr): array
    {
        return [
            'purpose'  => ['nullable', 'string', 'max:2000'],
            'jr_type'  => ['nullable', Rule::in(array_keys(config('procurement.jr_types')))],
        ] + $this->signatoryRules(false);
    }

    protected function signatoryRules(bool $required): array
    {
        $rule = $required ? 'required' : 'nullable';

        return [
            'requested_by_name'        => [$rule, 'string', 'max:255'],
            'requested_by_designation' => ['nullable', 'string', 'max:255'],
            'approved_by_name'         => [$rule, 'string', 'max:255'],
            'approved_by_designation'  => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function attempt(Closure $action): JsonResponse
    {
        try {
            return response()->json(['status' => 'success', 'message' => $action()]);
        } catch (ProcurementException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    protected function authorizeView(Request $request, PurchaseRequest $pr): void
    {
        if (! PurchaseRequest::visibleTo($request->user())->whereKey($pr->id)->exists()) {
            $this->deny($request, 'You do not have access to this request.');
        }
    }

    protected function authorizeEdit(Request $request, PurchaseRequest $pr): void
    {
        if (! $pr->isEditableBy($request->user())) {
            $this->deny($request, 'Only the requesting office can change this request.');
        }
    }

    protected function deny(Request $request, string $message): never
    {
        abort($request->expectsJson()
            ? response()->json(['status' => 'error', 'message' => $message], 403)
            : 403, $message);
    }

    protected function validateJson(Request $request, array $rules, array $messages = [], array $attributes = []): array
    {
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422));
        }

        return $validator->validated();
    }
}
