<?php

namespace App\Services\Procurement;

use App\Enums\Region;
use App\Enums\RequestStatus;
use App\Enums\RfqStatus;
use App\Exceptions\ProcurementException;
use App\Models\Procurement\PurchaseRequest;
use App\Models\Procurement\PurchaseRequestItem;
use App\Models\Procurement\Rfq;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Requests for Quotation.
 *
 * An RFQ gathers lines of submitted PRs / JRs of one region and one procurement mode (several
 * requests can be canvassed together, or one request split over several RFQs). A line is on at
 * most one RFQ that is not cancelled. Issuing numbers it (YYYY-MM-XXXX, series per year); a
 * cancelled RFQ frees its lines. While its lines are on an RFQ, a request cannot be revised or cancelled.
 */
class RfqService
{
    public const HEADER_FIELDS = [
        'title', 'delivery_place', 'delivery_period', 'price_validity', 'warranty', 'payment_terms',
        'submission_deadline', 'remarks', 'signatory_name', 'signatory_designation',
    ];

    public function create(User $user, Region $region, array $data): Rfq
    {
        $last = Rfq::where('region', $region)->latest('id')->first();

        return Rfq::create(array_intersect_key($data, array_flip(self::HEADER_FIELDS)) + [
            'region'         => $region,
            'status'         => RfqStatus::Draft,
            // Terms and signatory as on the region's last RFQ
            'delivery_place' => $last?->delivery_place,
            'delivery_period'=> $last?->delivery_period ?? 'Within 15 calendar days upon receipt of Purchase Order',
            'price_validity' => $last?->price_validity ?? '30 calendar days from the deadline of submission',
            'warranty'       => $last?->warranty,
            'payment_terms'  => $last?->payment_terms ?? 'Government terms (after delivery, inspection and acceptance)',
            'signatory_name' => $last?->signatory_name,
            'signatory_designation' => $last?->signatory_designation,
            'created_by'     => $user->id,
            'updated_by'     => $user->id,
        ]);
    }

    public function updateHeader(Rfq $rfq, User $user, array $data): Rfq
    {
        $fields = $rfq->isEditable() ? self::HEADER_FIELDS : ['signatory_name', 'signatory_designation'];

        if (! $rfq->isEditable() && $rfq->status !== RfqStatus::Issued) {
            throw new ProcurementException("{$rfq->title()} is {$rfq->status->label()} and can no longer be changed.");
        }

        $rfq->update(array_intersect_key($data, array_flip($fields)) + ['updated_by' => $user->id]);

        return $rfq;
    }

    /**
     * Submitted PRs / JRs of the RFQ's region with lines that are on no other open RFQ, each with
     * those lines (->available). Lines of another procurement mode than the RFQ's are left out.
     */
    public function availableRequests(Rfq $rfq): Collection
    {
        $taken = $this->takenLineIds();

        return PurchaseRequest::with(['office', 'pap', 'items.ppmpItem.procurementMode'])
            ->where('status', RequestStatus::Submitted)
            ->whereHas('ppmp', fn ($q) => $q->where('region', $rfq->region))
            // Being revised: wait for the revision
            ->whereNotIn('id', PurchaseRequest::where('status', RequestStatus::Draft)->whereNotNull('revised_from_id')->select('revised_from_id'))
            ->orderBy('series_year')->orderBy('series')
            ->get()
            ->each(function (PurchaseRequest $pr) use ($taken, $rfq) {
                $pr->setRelation('available', $pr->items
                    ->reject(fn ($line) => in_array($line->id, $taken, true))
                    ->filter(fn ($line) => ! $rfq->procurement_mode_id || (int) $line->ppmpItem?->procurement_mode_id === (int) $rfq->procurement_mode_id)
                    ->values());
            })
            ->filter(fn (PurchaseRequest $pr) => $pr->available->isNotEmpty())
            ->values();
    }

    /** Put PR / JR lines on a draft RFQ. They must be submitted, of the RFQ's region and mode, and on no other open RFQ. */
    public function addLines(Rfq $rfq, User $user, array $lineIds): Rfq
    {
        return DB::transaction(function () use ($rfq, $user, $lineIds) {
            $rfq = $this->lockEditable($rfq);
            $lines = PurchaseRequestItem::with(['request.ppmp', 'ppmpItem'])->whereIn('id', $lineIds)->lockForUpdate()->get();

            if ($lines->isEmpty()) {
                throw new ProcurementException('Choose at least one item.');
            }

            $taken = $this->takenLineIds();
            $mode = $rfq->procurement_mode_id;
            $order = (int) $rfq->items()->max('sort_order');

            foreach ($lines as $line) {
                $pr = $line->request;

                if ($pr->status !== RequestStatus::Submitted) {
                    throw new ProcurementException("{$pr->title()} is {$pr->status->label()}; only submitted requests go on an RFQ.");
                }

                if ($pr->ppmp->region !== $rfq->region) {
                    throw new ProcurementException("{$pr->title()} is a {$pr->ppmp->region->label()} request; this RFQ is for {$rfq->region->label()}.");
                }

                if (in_array($line->id, $taken, true)) {
                    throw new ProcurementException("\"{$line->description}\" of {$pr->title()} is already on another RFQ.");
                }

                if (PurchaseRequest::where('revised_from_id', $pr->id)->where('status', RequestStatus::Draft)->exists()) {
                    throw new ProcurementException("{$pr->title()} is being revised; submit or delete its revision first.");
                }

                $mode ??= $line->ppmpItem?->procurement_mode_id;

                if ((int) $line->ppmpItem?->procurement_mode_id !== (int) $mode) {
                    throw new ProcurementException("\"{$line->description}\" has another procurement mode; an RFQ holds items of one mode.");
                }

                $rfq->items()->create(['purchase_request_item_id' => $line->id, 'sort_order' => ++$order]);
                $taken[] = $line->id;
            }

            $rfq->update(['procurement_mode_id' => $mode, 'updated_by' => $user->id]);
            $this->recalculate($rfq);

            return $rfq;
        });
    }

    public function removeLine(Rfq $rfq, User $user, int $rfqItemId): Rfq
    {
        return DB::transaction(function () use ($rfq, $user, $rfqItemId) {
            $rfq = $this->lockEditable($rfq);
            $rfq->items()->whereKey($rfqItemId)->firstOrFail()->delete();

            // Empty again: any mode can be added
            $rfq->update(['procurement_mode_id' => $rfq->items()->exists() ? $rfq->procurement_mode_id : null, 'updated_by' => $user->id]);
            $this->recalculate($rfq);

            return $rfq;
        });
    }

    /** Number the RFQ; it is then printed and sent out. Terms can no longer change, the signatory still can. */
    public function issue(Rfq $rfq, User $user, array $data = []): Rfq
    {
        return DB::transaction(function () use ($rfq, $user, $data) {
            $rfq = $this->lockEditable($rfq);
            $rfq->fill(array_intersect_key($data, array_flip(self::HEADER_FIELDS)));

            if (! $rfq->items()->exists()) {
                throw new ProcurementException('Add at least one item before issuing.');
            }

            if (! $rfq->submission_deadline) {
                throw new ProcurementException('Set the deadline for submitting quotations before issuing.');
            }

            if (! $rfq->signatory_name) {
                throw new ProcurementException('Set who signs the RFQ before issuing.');
            }

            // Lines whose request was revised or cancelled meanwhile cannot go out
            $stale = $rfq->items()->with('line.request')->get()->first(fn ($i) => $i->line->request->status !== RequestStatus::Submitted);
            if ($stale) {
                throw new ProcurementException("{$stale->line->request->title()} is {$stale->line->request->status->label()}; remove its items first.");
            }

            $now = now();
            $last = Rfq::where('series_year', $now->year)->lockForUpdate()->max('series');
            $series = (int) $last + 1;

            $rfq->fill([
                'series_year' => $now->year,
                'series'      => $series,
                'rfq_no'      => sprintf('%d-%02d-%04d', $now->year, $now->month, $series),
                'status'      => RfqStatus::Issued,
                'issued_at'   => $now,
                'updated_by'  => $user->id,
            ])->save();

            return $rfq;
        });
    }

    /** Cancel an issued RFQ: its lines are free for another RFQ. The number is not reused. */
    public function cancel(Rfq $rfq, User $user, string $reason): Rfq
    {
        return DB::transaction(function () use ($rfq, $user, $reason) {
            $rfq = Rfq::whereKey($rfq->id)->lockForUpdate()->firstOrFail();

            if ($rfq->status !== RfqStatus::Issued) {
                throw new ProcurementException("Only an issued RFQ can be cancelled. {$rfq->title()} is {$rfq->status->label()}.");
            }

            $rfq->update(['status' => RfqStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => $reason, 'updated_by' => $user->id]);

            return $rfq;
        });
    }

    public function deleteDraft(Rfq $rfq): void
    {
        DB::transaction(fn () => $this->lockEditable($rfq)->delete());
    }

    /** The open RFQ a request's lines are on, if any (blocks revising or cancelling the request). */
    public function openRfqFor(PurchaseRequest $request): ?Rfq
    {
        return Rfq::where('status', '!=', RfqStatus::Cancelled)
            ->whereHas('items.line', fn ($q) => $q->where('purchase_request_id', $request->id))
            ->first();
    }

    /** Ids of PR / JR lines on an RFQ that is not cancelled. */
    protected function takenLineIds(): array
    {
        return DB::table('rfq_items')->join('rfqs', 'rfqs.id', '=', 'rfq_items.rfq_id')
            ->where('rfqs.status', '!=', RfqStatus::Cancelled->value)
            ->pluck('rfq_items.purchase_request_item_id')->map(fn ($id) => (int) $id)->all();
    }

    protected function recalculate(Rfq $rfq): void
    {
        $cents = $rfq->items()->with('line')->get()->sum(fn ($i) => Money::toCents($i->line->total_cost));
        $rfq->update(['total_abc' => Money::fromCents($cents)]);
    }

    protected function lockEditable(Rfq $rfq): Rfq
    {
        $locked = Rfq::whereKey($rfq->id)->lockForUpdate()->firstOrFail();

        if (! $locked->isEditable()) {
            throw new ProcurementException("{$locked->title()} is {$locked->status->label()} and can no longer be changed.");
        }

        return $locked;
    }
}
