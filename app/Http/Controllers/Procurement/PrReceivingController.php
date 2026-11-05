<?php

namespace App\Http\Controllers\Procurement;

use App\DataTables\Procurement\PrReceivingDataTable;
use App\Enums\Region;
use App\Exceptions\ProcurementException;
use App\Http\Controllers\Controller;
use App\Models\Procurement\PurchaseRequest;
use App\Services\Procurement\PurchaseRequestService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * PR Receiving: the BAC receives the hard copy of each submitted PR / JR of its region. Only then do its
 * items go on an RFQ or a bidding. A request with a problem is returned to its office instead.
 */
class PrReceivingController extends Controller
{
    public function __construct(
        protected PurchaseRequestService $service
    ) {}

    /** The page, or (for the table's requests) one page of requests as JSON. */
    public function index(PrReceivingDataTable $dataTable)
    {
        return $dataTable->render('procurement.receiving.index', [
            'tab'     => PrReceivingDataTable::tab(),
            'pending' => PrReceivingDataTable::pending()->count(),
            'year'    => PrReceivingDataTable::selectedYear(),
            'years'   => PurchaseRequest::yearOptions(PrReceivingDataTable::region()->whereNotNull('submitted_at')),
        ]);
    }

    public function receive(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeRegion($request, $purchaseRequest);
        $data = $this->validateJson($request, [
            'date'    => ['required', 'date', 'before_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ], ['date.required' => 'Set the date received.', 'date.before_or_equal' => 'The date received cannot be in the future.']);

        try {
            // Today: now; an earlier day: that day
            $date = Carbon::parse($data['date']);
            $at = $date->isToday() ? now() : $date->startOfDay();
            $pr = $this->service->receive($purchaseRequest, $request->user(), $at, $data['remarks'] ?? null);
        } catch (ProcurementException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => 'success', 'message' => "{$pr->title()} received. Its items can now go on " . ($pr->procurementLabel() === 'FOR BIDDING' ? 'a bidding.' : 'an RFQ.')]);
    }

    /** Return the request to its office (e.g. an unsigned or incomplete hard copy), for revision or cancellation. */
    public function returnToOffice(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeRegion($request, $purchaseRequest);
        $data = $this->validateJson($request, [
            'return_for' => ['required', Rule::in(array_keys(PurchaseRequest::RETURN_FOR))],
            'reason'     => ['required', 'string', 'max:1000'],
        ], ['return_for.required' => 'Choose whether it is for revision or for cancellation.', 'reason.required' => 'State why it is returned.']);

        try {
            $this->service->returnToOffice($purchaseRequest, $request->user(), $data['return_for'], $data['reason']);
        } catch (ProcurementException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => 'success', 'message' => "{$purchaseRequest->title()} returned to the office " . PurchaseRequest::RETURN_FOR[$data['return_for']] . '.']);
    }

    /** The request as printed (in a modal on the page). */
    public function request(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeRegion($request, $purchaseRequest);

        return app(PurchaseRequestController::class)->printView($purchaseRequest);
    }

    protected function authorizeRegion(Request $request, PurchaseRequest $purchaseRequest): void
    {
        $user = $request->user();

        if (! $user->hasRole('Super Admin') && $purchaseRequest->ppmp->region !== ($user->region ?? Region::Lm)) {
            abort($request->expectsJson() ? response()->json(['status' => 'error', 'message' => 'This request belongs to another region.'], 403) : 403, 'This request belongs to another region.');
        }
    }

    protected function validateJson(Request $request, array $rules, array $messages = []): array
    {
        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json(['status' => 'error', 'errors' => $validator->errors()], 422));
        }

        return $validator->validated();
    }
}
