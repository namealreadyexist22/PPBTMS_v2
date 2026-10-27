<?php

namespace App\Services\Procurement;

use App\Models\Procurement\Aoq;
use App\Models\Procurement\PurchaseRequest;
use App\Models\Procurement\PurchaseRequestEvent;
use App\Models\Procurement\Rfq;
use App\Models\User;

/**
 * The status trail of PRs / JRs: each step recorded on the request itself, including what happens
 * to it through the RFQ, the AOQ, the TWG evaluation and the award (recorded on every request of
 * that RFQ), so the requesting office can follow it.
 */
class RequestTrail
{
    public function record(PurchaseRequest $request, string $event, string $description, ?User $user = null, ?string $remarks = null, ?string $url = null): void
    {
        PurchaseRequestEvent::create([
            'purchase_request_id' => $request->id,
            'event'               => $event,
            'description'         => $description,
            'remarks'             => filled($remarks) ? mb_substr($remarks, 0, 1000) : null,
            'url'                 => $url,
            'user_id'             => $user?->id,
            'created_at'          => now(),
        ]);
    }

    /** The same step on every request with lines on the RFQ ($link false: the RFQ is being deleted). */
    public function recordForRfq(Rfq $rfq, string $event, string $description, ?User $user = null, ?string $remarks = null, bool $link = true): void
    {
        foreach ($rfq->load('items.line.request')->requests() as $request) {
            $this->record($request, $event, $description, $user, $remarks, $link ? route('procurement.rfqs.show', $rfq, false) : null);
        }
    }

    /** A step of the AOQ, on every request of its RFQ; $perRequest (request id => text) adds its own details. */
    public function recordForAoq(Aoq $aoq, string $event, string $description, ?User $user = null, ?string $remarks = null, array $perRequest = []): void
    {
        foreach ($aoq->rfq->load('items.line.request')->requests() as $request) {
            $text = trim($description . (isset($perRequest[$request->id]) ? ' ' . $perRequest[$request->id] : ''));
            $this->record($request, $event, $text, $user, $remarks, route('procurement.aoqs.show', $aoq, false));
        }
    }
}
