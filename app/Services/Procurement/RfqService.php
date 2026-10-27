<?php

namespace App\Services\Procurement;

use App\Enums\AwardBasis;
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
 * most one RFQ that is not cancelled. Issuing numbers it (YYYY-NNNN, series per year); a
 * cancelled RFQ frees its lines. While its lines are on an RFQ, a request cannot be revised or cancelled.
 */
class RfqService
{
    public const HEADER_FIELDS = [
        'title', 'award_basis', 'delivery_place', 'delivery_period', 'price_validity', 'warranty', 'payment_terms',
        'submission_deadline', 'remarks', 'signatory_name', 'signatory_designation', 'canvassed_by_name',
    ];

    /** Printed only, so they can still change after the RFQ is issued. */
    public const PRINT_FIELDS = ['signatory_name', 'signatory_designation', 'canvassed_by_name'];

    public function create(User $user, Region $region, array $data): Rfq
    {
        $last = Rfq::where('region', $region)->latest('id')->first();
        // Signed by: the region's RFQ signatory (Signatories & BAC page), else as on its last RFQ
        $signer = app(OfficialsService::class)->user($region, 'rfq_signatory');

        return Rfq::create(array_intersect_key(array_filter($data, fn ($v) => $v !== null), array_flip(self::HEADER_FIELDS)) + [
            'award_basis'           => AwardBasis::Item,
            'region'                => $region,
            'status'                => RfqStatus::Draft,
            // Delivery and price validity days, signatory and canvasser as on the region's last RFQ
            'delivery_period'       => $last?->delivery_period,
            'price_validity'        => $last?->price_validity,
            'signatory_name'        => $signer?->fullname ?? $last?->signatory_name,
            'signatory_designation' => $signer ? $signer->designation : $last?->signatory_designation,
            'canvassed_by_name'     => $last?->canvassed_by_name,
            'created_by'            => $user->id,
            'updated_by'            => $user->id,
        ]);
    }

    public function updateHeader(Rfq $rfq, User $user, array $data): Rfq
    {
        $fields = $rfq->isEditable() ? self::HEADER_FIELDS : self::PRINT_FIELDS;

        if (! $rfq->isEditable() && $rfq->status !== RfqStatus::Issued) {
            throw new ProcurementException("{$rfq->title()} is {$rfq->status->label()} and can no longer be changed.");
        }

        if (array_key_exists('award_basis', $data) && blank($data['award_basis'])) {
            unset($data['award_basis']);   // kept as it is
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
            ->whereNull('returned_at')   // returned to the office: it revises or cancels it first
            ->whereHas('ppmp', fn ($q) => $q->where('region', $rfq->region))
            // Being revised: wait for the revision
            ->whereNotIn('id', PurchaseRequest::where('status', RequestStatus::Draft)->whereNotNull('revised_from_id')->select('revised_from_id'))
            ->orderBy('series_year')->orderBy('series')
            ->get()
            ->each(function (PurchaseRequest $pr) use ($taken, $rfq) {
                $pr->setRelation('available', $pr->items
                    ->reject(fn ($line) => in_array($line->id, $taken, true))
                    ->filter(fn ($line) => ! $rfq->procurement_mode_id || $line->ppmpItem?->effectiveModeId() === (int) $rfq->procurement_mode_id)
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

                if ($pr->returned_at) {
                    throw new ProcurementException("{$pr->title()} was returned to the office " . PurchaseRequest::RETURN_FOR[$pr->return_for] . '; it goes on an RFQ again once revised.');
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

                // The BAC's mode in the approved APP, else the PPMP's
                $lineMode = $line->ppmpItem?->effectiveModeId();
                $mode ??= $lineMode;

                if ($lineMode !== (int) $mode) {
                    throw new ProcurementException("\"{$line->description}\" has another procurement mode; an RFQ holds items of one mode.");
                }

                $rfq->items()->create(['purchase_request_item_id' => $line->id, 'sort_order' => ++$order]);
                $taken[] = $line->id;
            }

            $rfq->update(['procurement_mode_id' => $mode, 'updated_by' => $user->id]);
            $this->recalculate($rfq);

            foreach ($lines->groupBy('purchase_request_id') as $prLines) {
                $names = $prLines->pluck('description')->implode(', ');
                app(RequestTrail::class)->record($prLines->first()->request, 'rfq_added', "Put on {$rfq->title()} for quotation: {$names}", $user, null, route('procurement.rfqs.show', $rfq, false));
            }

            return $rfq;
        });
    }

    public function removeLine(Rfq $rfq, User $user, int $rfqItemId): Rfq
    {
        return DB::transaction(function () use ($rfq, $user, $rfqItemId) {
            $rfq = $this->lockEditable($rfq);
            $item = $rfq->items()->with('line.request')->whereKey($rfqItemId)->firstOrFail();
            $item->delete();
            app(RequestTrail::class)->record($item->line->request, 'rfq_removed', "\"{$item->line->description}\" taken off {$rfq->title()}", $user, null, route('procurement.rfqs.show', $rfq, false));

            // Empty again: any mode can be added
            $rfq->update(['procurement_mode_id' => $rfq->items()->exists() ? $rfq->procurement_mode_id : null, 'updated_by' => $user->id]);
            $this->recalculate($rfq);

            return $rfq;
        });
    }

    /** Number the RFQ (Quotation No. YYYY-NNNN); it is then printed and sent out. Terms are fixed, the names printed on it are not. */
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
                'rfq_no'      => sprintf('%d-%04d', $now->year, $series),
                'status'      => RfqStatus::Issued,
                'issued_at'   => $now,
                'updated_by'  => $user->id,
            ])->save();
            app(RequestTrail::class)->recordForRfq($rfq, 'rfq_issued', "{$rfq->title()} issued to suppliers; quotations due {$rfq->submission_deadline->format('M d, Y g:i A')}", $user);

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

            if ($aoq = $rfq->aoq()->first()) {
                throw new ProcurementException($aoq->isEditable()
                    ? "{$rfq->title()} has an abstract of quotations; delete it first."
                    : "{$rfq->title()} is awarded ({$aoq->title()}); reopen and delete the abstract first.");
            }

            $rfq->update(['status' => RfqStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => $reason, 'updated_by' => $user->id]);
            app(RequestTrail::class)->recordForRfq($rfq, 'rfq_cancelled', "{$rfq->title()} cancelled; the items can go on another RFQ", $user, $reason);

            return $rfq;
        });
    }

    public function deleteDraft(Rfq $rfq): void
    {
        DB::transaction(function () use ($rfq) {
            $rfq = $this->lockEditable($rfq);
            app(RequestTrail::class)->recordForRfq($rfq, 'rfq_removed', "{$rfq->title()} deleted; the items can go on another RFQ", auth()->user(), null, false);
            $rfq->delete();
        });
    }

    /**
     * Return a request in process to its office, for revision or for cancellation: its items come off
     * the RFQ (with any quotations for them on a draft AOQ). An RFQ left without items is deleted
     * (draft) or cancelled (issued). Refused once the AOQ is with the TWG, evaluated or awarded.
     */
    public function returnRequest(Rfq $rfq, PurchaseRequest $request, User $user, string $for, string $reason): PurchaseRequest
    {
        if (! array_key_exists($for, PurchaseRequest::RETURN_FOR)) {
            throw new ProcurementException('Say whether it is returned for revision or for cancellation.');
        }

        $files = [];
        $request = DB::transaction(function () use ($rfq, $request, $user, $for, $reason, &$files) {
            $rfq = Rfq::whereKey($rfq->id)->lockForUpdate()->firstOrFail();
            $request = PurchaseRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! in_array($rfq->status, [RfqStatus::Draft, RfqStatus::Issued], true)) {
                throw new ProcurementException("{$rfq->title()} is {$rfq->status->label()}.");
            }

            $aoq = $rfq->aoq()->first();
            if ($aoq && ! $aoq->isEditable()) {
                throw new ProcurementException("{$aoq->title()} is {$aoq->status->label()}; recall or reopen it first, then return the request.");
            }

            $items = $rfq->items()->whereHas('line', fn ($q) => $q->where('purchase_request_id', $request->id))->get();
            if ($items->isEmpty()) {
                throw new ProcurementException("{$request->title()} has no items on {$rfq->title()}.");
            }

            // The quotations for its items go with them (their PDFs once committed)
            if ($aoq) {
                $files = \App\Models\Procurement\AoqQuote::whereIn('rfq_item_id', $items->pluck('id'))->pluck('attachment_path')->filter()->all();
            }
            $rfq->items()->whereKey($items->pluck('id'))->delete();

            app(PurchaseRequestService::class)->markReturned($request, $user, $for, $reason, $rfq->title());

            if (! $rfq->items()->exists()) {
                // Nothing left to quote
                if ($rfq->status === RfqStatus::Draft) {
                    $rfq->delete();
                } else {
                    if ($aoq) {
                        $files = array_merge($files, app(AoqAttachmentService::class)->pathsOfAoq($aoq));
                        $aoq->delete();
                    }
                    $rfq->update(['status' => RfqStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => "Its requests were returned to the office: {$reason}", 'updated_by' => $user->id]);
                }
            } else {
                $rfq->update(['updated_by' => $user->id]);
                $this->recalculate($rfq);
            }

            return $request;
        });

        app(AoqAttachmentService::class)->deleteFiles($files);

        return $request;
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
