<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\Region;
use App\Enums\RfqStatus;
use App\Exceptions\ProcurementException;
use App\Http\Controllers\Controller;
use App\Models\Procurement\Rfq;
use App\Models\User;
use App\Services\Procurement\RfqService;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Requests for Quotation: list, workspace (items from submitted PRs / JRs, terms), issue / cancel, print. */
class RfqController extends Controller
{
    public function __construct(
        protected RfqService $service
    ) {}

    public function index(Request $request)
    {
        $status = RfqStatus::tryFrom((string) $request->query('status'));
        $search = trim((string) $request->query('q'));

        $rfqs = Rfq::visibleTo($request->user())
            ->with(['procurementMode', 'items.line.request'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('rfq_no', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%")
                ->orWhereHas('items.line.request', fn ($r) => $r->where('request_no', 'like', "%{$search}%"))))
            ->orderByRaw('rfq_no is null desc')
            ->orderByDesc('series_year')->orderByDesc('series')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        return view('procurement.rfqs.index', [
            'status'  => $status,
            'search'  => $search,
            'rfqs'    => $rfqs,
            'regions' => $request->user()->hasRole('Super Admin') ? Region::cases() : [],
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $data = $this->validateJson($request, [
            'title'  => ['nullable', 'string', 'max:500'],
            'region' => ['nullable', Rule::enum(Region::class)],
        ]);

        // Only a Super Admin picks the region; everyone else works in their own
        $region = $user->hasRole('Super Admin') && ! empty($data['region']) ? Region::from($data['region']) : ($user->region ?? Region::Lm);
        $rfq = $this->service->create($user, $region, $data);

        return response()->json(['status' => 'success', 'message' => 'RFQ started. Add the items to quote.', 'url' => route('procurement.rfqs.show', $rfq)]);
    }

    public function show(Request $request, Rfq $rfq)
    {
        $this->authorizeView($request, $rfq);
        $rfq->load(['procurementMode', 'items.line.request.office', 'items.line.request.pap', 'creator']);

        return view('procurement.rfqs.show', [
            'rfq'     => $rfq,
            'canEdit' => $rfq->isEditable(),
            'people'  => $this->signatoryPeople($request->user()),
        ]);
    }

    public function header(Request $request, Rfq $rfq)
    {
        $this->authorizeView($request, $rfq);
        $data = $this->validateJson($request, $this->headerRules());

        return $this->attempt(function () use ($request, $rfq, $data) {
            $this->service->updateHeader($rfq, $request->user(), $data);

            return 'Saved.';
        });
    }

    /** Add items: the submitted PRs / JRs of the region with lines not on another RFQ. */
    public function picker(Request $request, Rfq $rfq)
    {
        $this->authorizeView($request, $rfq);

        if (! $rfq->isEditable()) {
            $this->deny($request, "{$rfq->title()} is {$rfq->status->label()} and can no longer be changed.");
        }

        return view('procurement.rfqs.extras.rfq_picker', [
            'modalName' => 'RFQ_PICKER_MODAL',
            'rfq'       => $rfq->load('procurementMode'),
            'requests'  => $this->service->availableRequests($rfq),
        ]);
    }

    public function addLines(Request $request, Rfq $rfq)
    {
        $this->authorizeView($request, $rfq);
        $data = $this->validateJson($request, ['lines' => ['required', 'array', 'min:1'], 'lines.*' => ['integer']], [
            'lines.required' => 'Choose at least one item.',
        ]);

        return $this->attempt(function () use ($request, $rfq, $data) {
            $this->service->addLines($rfq, $request->user(), $data['lines']);
            $n = count($data['lines']);

            return $n === 1 ? '1 item added.' : "{$n} items added.";
        });
    }

    public function removeLine(Request $request, Rfq $rfq)
    {
        $this->authorizeView($request, $rfq);
        $this->validateJson($request, ['id' => ['required', 'integer']]);

        return $this->attempt(function () use ($request, $rfq) {
            $this->service->removeLine($rfq, $request->user(), $request->integer('id'));

            return 'Item taken off the RFQ.';
        });
    }

    public function issue(Request $request, Rfq $rfq)
    {
        $this->authorizeView($request, $rfq);
        $data = $this->validateJson($request, $this->headerRules());

        return $this->attempt(function () use ($request, $rfq, $data) {
            $rfq = $this->service->issue($rfq, $request->user(), $data);

            return "{$rfq->title()} issued. Print it for the canvassers.";
        });
    }

    public function cancel(Request $request, Rfq $rfq)
    {
        $this->authorizeView($request, $rfq);
        $data = $this->validateJson($request, ['reason' => ['required', 'string', 'max:500']], ['reason.required' => 'Please state the reason for cancelling.']);

        return $this->attempt(function () use ($request, $rfq, $data) {
            $this->service->cancel($rfq, $request->user(), $data['reason']);

            return "{$rfq->title()} cancelled. Its items can go on another RFQ.";
        });
    }

    /** Return a request in process to its office, for revision or cancellation (its items come off the RFQ). */
    public function returnRequest(Request $request, Rfq $rfq, \App\Models\Procurement\PurchaseRequest $purchaseRequest)
    {
        $this->authorizeView($request, $rfq);
        $data = $this->validateJson($request, [
            'return_for' => ['required', \Illuminate\Validation\Rule::in(array_keys(\App\Models\Procurement\PurchaseRequest::RETURN_FOR))],
            'reason'     => ['required', 'string', 'max:1000'],
        ], ['return_for.required' => 'Choose whether it is for revision or for cancellation.', 'reason.required' => 'State why it is returned.']);

        try {
            $this->service->returnRequest($rfq, $purchaseRequest, $request->user(), $data['return_for'], $data['reason']);
        } catch (ProcurementException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        // A draft RFQ left without items is deleted: back to the list
        return response()->json([
            'status'  => 'success',
            'message' => "{$purchaseRequest->title()} returned to the office " . \App\Models\Procurement\PurchaseRequest::RETURN_FOR[$data['return_for']] . '.',
            'url'     => Rfq::whereKey($rfq->id)->exists() ? null : route('procurement.rfqs.index'),
        ]);
    }

    /** Return a submitted request not yet on an RFQ to its office (from the item picker), for revision or cancellation. */
    public function returnToOffice(Request $request, \App\Models\Procurement\PurchaseRequest $purchaseRequest)
    {
        $user = $request->user();
        if (! $user->hasRole('Super Admin') && $purchaseRequest->ppmp->region !== ($user->region ?? \App\Enums\Region::Lm)) {
            $this->deny($request, 'This request belongs to another region.');
        }

        $data = $this->validateJson($request, [
            'return_for' => ['required', \Illuminate\Validation\Rule::in(array_keys(\App\Models\Procurement\PurchaseRequest::RETURN_FOR))],
            'reason'     => ['required', 'string', 'max:1000'],
        ], ['return_for.required' => 'Choose whether it is for revision or for cancellation.', 'reason.required' => 'State why it is returned.']);

        return $this->attempt(function () use ($user, $purchaseRequest, $data) {
            app(\App\Services\Procurement\PurchaseRequestService::class)->returnToOffice($purchaseRequest, $user, $data['return_for'], $data['reason']);

            return "{$purchaseRequest->title()} returned to the office " . \App\Models\Procurement\PurchaseRequest::RETURN_FOR[$data['return_for']] . '.';
        });
    }

    public function destroy(Request $request, Rfq $rfq)
    {
        $this->authorizeView($request, $rfq);

        return $this->attempt(function () use ($rfq) {
            $this->service->deleteDraft($rfq);

            return 'Draft deleted.';
        });
    }

    /** A PR / JR whose items are on this RFQ, as its printed form (shown in a modal on the RFQ page). */
    public function request(Request $request, Rfq $rfq, \App\Models\Procurement\PurchaseRequest $purchaseRequest)
    {
        $this->authorizeView($request, $rfq);

        if (! $rfq->items()->whereHas('line', fn ($q) => $q->where('purchase_request_id', $purchaseRequest->id))->exists()) {
            $this->deny($request, "{$purchaseRequest->title()} has no items on this RFQ.");
        }

        return app(PurchaseRequestController::class)->printView($purchaseRequest);
    }

    /** Printable RFQ; ?copies=N prints N copies, one per supplier the canvassers will visit. */
    public function print(Request $request, Rfq $rfq)
    {
        $this->authorizeView($request, $rfq);

        return view('procurement.rfqs.print', [
            'rfq'    => $rfq->load(['procurementMode', 'items.line.request']),
            'copies' => max(1, min(10, $request->integer('copies', 1))),
        ]);
    }

    protected function headerRules(): array
    {
        return [
            'title'                 => ['nullable', 'string', 'max:500'],
            'award_basis'           => ['nullable', \Illuminate\Validation\Rule::enum(\App\Enums\AwardBasis::class)],
            'delivery_place'        => ['nullable', 'string', 'max:500'],
            // Calendar days, printed in the RFQ notes (blank: filled in by hand)
            'delivery_period'       => ['nullable', 'integer', 'min:1', 'max:3650'],
            'price_validity'        => ['nullable', 'integer', 'min:1', 'max:3650'],
            'warranty'              => ['nullable', 'string', 'max:255'],
            'payment_terms'         => ['nullable', 'string', 'max:255'],
            'submission_deadline'   => ['nullable', 'date'],
            'remarks'               => ['nullable', 'string', 'max:2000'],
            'signatory_name'        => ['nullable', 'string', 'max:255'],
            'signatory_designation' => ['nullable', 'string', 'max:255'],
            'canvassed_by_name'     => ['nullable', 'string', 'max:255'],
        ];
    }

    /** Signatory picker: names used on RFQs before, then everyone. */
    protected function signatoryPeople(User $user): array
    {
        $past = Rfq::whereNotNull('signatory_name')->latest('id')->limit(30)->get(['signatory_name', 'signatory_designation'])->toBase()
            ->map(fn ($r) => ['name' => $r->signatory_name, 'designation' => $r->signatory_designation, 'office' => null]);
        $people = User::where('is_activated', true)->with('office')->orderBy('fname')->orderBy('lname')->get()->toBase()
            ->map(fn ($u) => ['name' => $u->fullname, 'designation' => $u->designation, 'office' => $u->office?->acronym ?: $u->office?->name]);

        // A name used before that is a user's (in any capitalization) is that user: listed once, with the designation
        $users = $people->keyBy(fn ($p) => mb_strtolower(trim($p['name'])));
        $past = $past->reject(fn ($p) => $users->has(mb_strtolower(trim($p['name']))))
            ->unique(fn ($p) => mb_strtolower(trim($p['name'])));

        return $past->merge($people)->unique('name')->values()->all();
    }

    protected function attempt(Closure $action): JsonResponse
    {
        try {
            return response()->json(['status' => 'success', 'message' => $action()]);
        } catch (ProcurementException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    protected function authorizeView(Request $request, Rfq $rfq): void
    {
        if (! Rfq::visibleTo($request->user())->whereKey($rfq->id)->exists()) {
            $this->deny($request, 'This RFQ belongs to another region.');
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
