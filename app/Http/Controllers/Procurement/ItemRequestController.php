<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\ItemRequestStatus;
use App\Exceptions\ProcurementException;
use App\Http\Controllers\Controller;
use App\Models\Procurement\ItemRequest;
use App\Models\Procurement\Office;
use App\Services\Procurement\ItemRequestService;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Item requests: "Our requests" (the office asks the procuring office for standard items) and
 * "Requests to us" (the procuring office approves all, part or none, into its PPMP distribution).
 */
class ItemRequestController extends Controller
{
    public function __construct(
        protected ItemRequestService $service
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $procuring = $this->service->procuringOfficeIds($user);
        $tab = $request->query('tab') === 'incoming' && $procuring->isNotEmpty() ? 'incoming' : 'ours';
        $status = ItemRequestStatus::tryFrom((string) $request->query('status'));
        // Offices with their parents: the list shows each one's department
        $with = ['item.unit', 'requestingOffice.parent.parent.parent', 'procuringOffice.parent.parent.parent', 'requester', 'decider', 'ppmpItem.ppmp'];

        $ours = ItemRequest::with($with)
            ->when(! $user->hasRole('Super Admin'), fn ($q) => $q->where('requesting_office_id', $user->office_id))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest('id')->paginate(25, ['*'], 'ours_page')->withQueryString();

        $incoming = ItemRequest::with($with)->whereIn('procuring_office_id', $procuring)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByRaw("case when status = 'pending' then 0 else 1 end")->latest('id')
            ->paginate(25, ['*'], 'incoming_page')->withQueryString();

        // To size the projects: per item and fiscal year, what is pending and what was approved
        $totals = ItemRequest::with('item.unit')->whereIn('procuring_office_id', $procuring)
            ->whereIn('status', [ItemRequestStatus::Pending, ItemRequestStatus::Approved, ItemRequestStatus::Partial])
            ->get()->groupBy(fn ($r) => $r->fiscal_year . '|' . $r->item_id)
            ->map(fn ($group) => [
                'fiscal_year' => $group->first()->fiscal_year,
                'item'        => $group->first()->item,
                'pending'     => $group->filter->isPending()->sum(fn ($r) => (float) $r->quantity),
                'approved'    => $group->reject->isPending()->sum(fn ($r) => (float) $r->approved_quantity),
                'offices'     => $group->pluck('requesting_office_id')->unique()->count(),
            ])->sortBy(fn ($t) => [$t['fiscal_year'], $t['item']->code])->values();

        return view('procurement.item_requests.index', compact('tab', 'ours', 'incoming', 'totals', 'procuring', 'status'));
    }

    /** New request: the standard items another office procures for the user's office. */
    public function entry(Request $request)
    {
        $office = $this->office($request);

        return view('procurement.item_requests.entry', [
            'office' => $office,
            'items'  => $this->service->requestableItems($office),
            'years'  => range((int) now()->year, (int) now()->year + 2),
        ]);
    }

    public function store(Request $request)
    {
        $office = $this->office($request);
        $data = $this->json($request, [
            'item_id'     => ['required', 'integer'],
            'fiscal_year' => ['required', 'integer', 'between:' . now()->year . ',' . (now()->year + 2)],
            'quantity'    => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'purpose'     => ['nullable', 'string', 'max:2000'],
        ], ['item_id.required' => 'Choose the item.', 'quantity.gt' => 'Enter the quantity requested.']);

        return $this->attempt(function () use ($request, $office, $data) {
            $ir = $this->service->create($request->user(), $office, $data);

            return "{$ir->request_no} sent to {$ir->procuringOffice->shortName()}.";
        });
    }

    public function cancel(Request $request, ItemRequest $itemRequest)
    {
        if (! $request->user()->hasRole('Super Admin') && (int) $itemRequest->requesting_office_id !== (int) $request->user()->office_id) {
            $this->deny($request, 'Only the requesting office can cancel it.');
        }

        return $this->attempt(function () use ($request, $itemRequest) {
            $this->service->cancel($itemRequest, $request->user());

            return "{$itemRequest->request_no} cancelled.";
        });
    }

    /** A request in detail (modal): what was asked and why, the item's specs and the decision. */
    public function show(Request $request, ItemRequest $itemRequest)
    {
        $user = $request->user();
        $decides = $this->service->canDecide($user, $itemRequest);
        if (! $decides && (int) $itemRequest->requesting_office_id !== (int) $user->office_id) {
            $this->deny($request, 'Only the requesting and the procuring office can see this request.');
        }

        return view('procurement.item_requests.show', [
            'ir'        => $itemRequest->load(['item.unit', 'requestingOffice', 'procuringOffice', 'requester', 'decider', 'ppmpItem.ppmp']),
            'canDecide' => $decides && $itemRequest->isPending(),
            'canCancel' => $itemRequest->isPending() && ($user->hasRole('Super Admin') || (int) $itemRequest->requesting_office_id === (int) $user->office_id),
        ]);
    }

    /** The procuring office's decision: how many, and into which of its PPMP projects. */
    public function decideEntry(Request $request, ItemRequest $itemRequest)
    {
        $this->authorizeDecide($request, $itemRequest);
        $itemRequest->load(['item.unit', 'requestingOffice', 'procuringOffice', 'requester']);

        return view('procurement.item_requests.decide', [
            'ir'       => $itemRequest,
            'projects' => $this->service->projectsFor($itemRequest),
        ]);
    }

    public function decide(Request $request, ItemRequest $itemRequest)
    {
        $this->authorizeDecide($request, $itemRequest);
        $data = $this->json($request, [
            'approved_quantity' => ['required', 'numeric', 'min:0'],
            'ppmp_item_id'      => ['nullable', 'integer'],
            'decision_remarks'  => ['nullable', 'string', 'max:1000'],
        ], ['approved_quantity.required' => 'Enter the quantity approved (0 to decline).']);

        return $this->attempt(function () use ($request, $itemRequest, $data) {
            $ir = $this->service->decide($itemRequest, $request->user(), (float) $data['approved_quantity'], $data['ppmp_item_id'] ?? null, $data['decision_remarks'] ?? null);

            return "{$ir->request_no}: {$ir->status->label()}.";
        });
    }

    /** The user's home office (Super Admin may act for another with ?office_id=). */
    protected function office(Request $request): Office
    {
        $user = $request->user();
        $office = $user->hasRole('Super Admin') && $request->filled('office_id') ? Office::find($request->integer('office_id')) : $user->office;

        if (! $office) {
            $this->deny($request, 'Your account has no office; ask the administrator to set it.');
        }

        return $office;
    }

    protected function authorizeDecide(Request $request, ItemRequest $itemRequest): void
    {
        if (! $this->service->canDecide($request->user(), $itemRequest)) {
            $this->deny($request, 'Only the office that procures this item can decide the request.');
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

    protected function json(Request $request, array $rules, array $messages = []): array
    {
        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json(['status' => 'error', 'errors' => $validator->errors()], 422));
        }

        return $validator->validated();
    }
}
