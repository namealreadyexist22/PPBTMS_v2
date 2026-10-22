<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\AoqStatus;
use App\Exceptions\ProcurementException;
use App\Http\Controllers\Controller;
use App\Models\Procurement\Aoq;
use App\Services\Procurement\AoqService;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * TWG evaluation: the AOQs the BAC assigned to the user. Each offer is checked against the item's
 * specifications, with the supplier's quotation and spec PDFs, then the evaluation is completed
 * (the BAC can award) or returned to the BAC.
 */
class TwgController extends Controller
{
    public function __construct(
        protected AoqService $service
    ) {}

    /** Waiting for the user's evaluation, then the ones evaluated before. */
    public function index(Request $request)
    {
        $user = $request->user();
        $mine = fn ($q) => $user->hasRole('Super Admin') ? $q : $q->whereHas('evaluators', fn ($e) => $e->whereKey($user->id));
        $with = ['rfq.procurementMode', 'rfq.items.line', 'suppliers', 'evaluators'];

        return view('procurement.twg.index', [
            'pending' => $mine(Aoq::with($with))->where('status', AoqStatus::ForEvaluation)->orderBy('sent_to_twg_at')->get(),
            'done'    => $mine(Aoq::with($with))->whereNotNull('evaluated_by')->where('status', '!=', AoqStatus::ForEvaluation)
                ->latest('evaluated_at')->limit(50)->get(),
        ]);
    }

    public function show(Request $request, Aoq $aoq)
    {
        $user = $request->user();
        abort_unless($user->hasRole('Super Admin') || $aoq->isEvaluator($user), 403, 'This abstract is not assigned to you.');
        $aoq->load(['rfq.procurementMode', 'rfq.items.line.request.office', 'suppliers.quotes', 'evaluators', 'evaluator']);

        return view('procurement.twg.show', [
            'aoq'     => $aoq,
            'rows'    => $this->service->evaluate($aoq),
            'canEval' => $aoq->canBeEvaluatedBy($user),
            'lot'     => $aoq->rfq->isPerLot() ? $this->service->lotStanding($aoq) : null,
        ]);
    }

    public function save(Request $request, Aoq $aoq)
    {
        $data = $this->validated($request);

        return $this->attempt(function () use ($request, $aoq, $data) {
            $this->service->saveEvaluation($aoq, $request->user(), $data['findings'] ?? [], $data['technical_comment'] ?? null);

            return 'Evaluation saved.';
        });
    }

    public function complete(Request $request, Aoq $aoq)
    {
        $data = $this->validated($request);

        return $this->attempt(function () use ($request, $aoq, $data) {
            $aoq = $this->service->completeEvaluation($aoq, $request->user(), $data['findings'] ?? [], $data['technical_comment'] ?? null);

            return "{$aoq->title()} evaluated. The BAC can now award it.";
        });
    }

    public function return(Request $request, Aoq $aoq)
    {
        $data = $this->json($request, ['reason' => ['required', 'string', 'max:1000']], ['reason.required' => 'Say what the BAC should correct.']);

        return $this->attempt(function () use ($request, $aoq, $data) {
            $aoq = $this->service->returnToBac($aoq, $request->user(), $data['reason']);

            return "{$aoq->title()} returned to the BAC.";
        });
    }

    protected function validated(Request $request): array
    {
        return $this->json($request, [
            'technical_comment'        => ['nullable', 'string', 'max:2000'],
            'findings'                 => ['nullable', 'array'],
            'findings.*.is_compliant'  => ['nullable', 'in:0,1'],
            'findings.*.twg_remarks'   => ['nullable', 'string', 'max:1000'],
        ], [], ['findings.*.twg_remarks' => 'remarks']);
    }

    protected function attempt(Closure $action): JsonResponse
    {
        try {
            return response()->json(['status' => 'success', 'message' => $action()]);
        } catch (ProcurementException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    protected function json(Request $request, array $rules, array $messages = [], array $attributes = []): array
    {
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json(['status' => 'error', 'errors' => $validator->errors()], 422));
        }

        return $validator->validated();
    }
}
