<?php

namespace App\Services\Procurement;

use App\Enums\BiddingStatus;
use App\Enums\BiddingStep;
use App\Enums\Region;
use App\Enums\RequestStatus;
use App\Exceptions\ProcurementException;
use App\Models\Procurement\Bidding;
use App\Models\Procurement\BiddingEvent;
use App\Models\Procurement\BiddingLot;
use App\Models\Procurement\BiddingLotItem;
use App\Models\Procurement\FundSource;
use App\Models\Procurement\PurchaseRequest;
use App\Models\Procurement\PurchaseRequestItem;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Competitive (public) bidding.
 *
 * The BAC creates a bidding for one region and one fund source, which numbers it (YYYY-FUND-NNNN, series
 * per year and fund), and puts Competitive Bidding lines of received PRs / JRs into lots (each awarded on
 * its own). A line is in at most one bidding that is not cancelled, and Competitive Bidding lines go on no
 * RFQ. Its steps then follow the agency's process, each one also on the PRs' status trail. Lots can change
 * until it is advertised. A re-set bidding date (at the pre-bid conference or by a supplemental bulletin)
 * keeps the earlier one in the history, with the reason. A bidding is cancelled, not deleted: its number
 * is not used again.
 */
class BiddingService
{
    /** After each step, the steps that can come next (optional ones may be skipped). */
    public const NEXT = [
        ''               => [BiddingStep::PreprocSet, BiddingStep::Advertised],
        'preproc_set'    => [BiddingStep::PreprocSet, BiddingStep::PreprocHeld],
        'preproc_held'   => [BiddingStep::Advertised],
        'advertised'     => [BiddingStep::PrebidHeld, BiddingStep::Supplemental, BiddingStep::Bidding],
        'prebid_held'    => [BiddingStep::Supplemental, BiddingStep::Bidding],
        'supplemental'   => [BiddingStep::Supplemental, BiddingStep::Bidding],
        // The bids' evaluation and the lot-by-lot steps come next
        'bidding'        => [],
    ];

    /** A line for competitive bidding: its mode (the BAC's in the approved APP, else the PPMP's) is Competitive Bidding. */
    public static function isBiddingLine(PurchaseRequestItem $line): bool
    {
        return in_array($line->ppmpItem?->effectiveMode()?->code, config('procurement.bidding_mode_codes', ['CB']), true);
    }

    /** A new bidding of the region and fund source, numbered now (YYYY-FUND-NNNN, series per year and fund). */
    public function create(User $user, Region $region, FundSource $fund, array $data): Bidding
    {
        return DB::transaction(function () use ($user, $region, $fund, $data) {
            $year = (int) now()->year;
            $series = (int) Bidding::where('series_year', $year)->where('fund_source_id', $fund->id)->lockForUpdate()->max('series') + 1;

            return Bidding::create([
                'bid_no'         => sprintf('%d-%s-%04d', $year, strtoupper($fund->code), $series),
                'series_year'    => $year,
                'series'         => $series,
                'fund_source_id' => $fund->id,
                'region'         => $region,
                'title'          => $data['title'] ?? null,
                'status'         => BiddingStatus::Ongoing,
                'created_by'     => $user->id,
                'updated_by'     => $user->id,
            ]);
        });
    }

    public function updateTitle(Bidding $bidding, User $user, ?string $title): Bidding
    {
        $this->assertNotCancelled($bidding);
        $bidding->update(['title' => $title, 'updated_by' => $user->id]);

        return $bidding;
    }

    /** The steps that can be recorded now. */
    public function nextSteps(Bidding $bidding): array
    {
        return $bidding->status === BiddingStatus::Cancelled ? [] : self::NEXT[$bidding->stage?->value ?? ''];
    }

    // ---------- Lots and lines ----------

    /**
     * Received PRs / JRs of the bidding's region with Competitive Bidding lines of its fund source in no
     * other bidding, each with those lines (->available).
     */
    public function availableRequests(Bidding $bidding): Collection
    {
        $taken = $this->takenLineIds();

        return PurchaseRequest::with(['office', 'pap', 'items.ppmpItem.procurementMode', 'items.ppmpItem.fundSource'])
            ->where('status', RequestStatus::Submitted)
            ->whereNotNull('received_at')   // its hard copy received by the BAC (PR Receiving)
            ->whereNull('returned_at')   // returned to the office: it revises or cancels it first
            ->whereHas('ppmp', fn ($q) => $q->where('region', $bidding->region))
            // Being revised: wait for the revision
            ->whereNotIn('id', PurchaseRequest::where('status', RequestStatus::Draft)->whereNotNull('revised_from_id')->select('revised_from_id'))
            ->orderBy('series_year')->orderBy('series')
            ->get()
            ->each(function (PurchaseRequest $pr) use ($taken, $bidding) {
                $pr->setRelation('available', $pr->items
                    ->reject(fn ($line) => in_array($line->id, $taken, true))
                    ->filter(fn ($line) => static::isBiddingLine($line))
                    ->filter(fn ($line) => (int) $line->ppmpItem?->fund_source_id === (int) $bidding->fund_source_id)
                    ->values());
            })
            ->filter(fn (PurchaseRequest $pr) => $pr->available->isNotEmpty())
            ->values();
    }

    /** Put PR / JR lines in a lot ($lotId null: a new lot). They must be received, for competitive bidding, of the region and fund source. */
    public function addLines(Bidding $bidding, User $user, array $lineIds, ?int $lotId = null): Bidding
    {
        return DB::transaction(function () use ($bidding, $user, $lineIds, $lotId) {
            $bidding = $this->lockLotsEditable($bidding);
            $lines = PurchaseRequestItem::with(['request.ppmp', 'ppmpItem.procurementMode', 'ppmpItem.fundSource'])->whereIn('id', $lineIds)->lockForUpdate()->get();

            if ($lines->isEmpty()) {
                throw new ProcurementException('Choose at least one item.');
            }

            $lot = $lotId ? $bidding->lots()->whereKey($lotId)->first() : null;
            if ($lotId && ! $lot) {
                throw new ProcurementException('That lot is not in this bidding.');
            }

            $taken = $this->takenLineIds();
            $fund = (int) $bidding->fund_source_id;

            foreach ($lines as $line) {
                $pr = $line->request;

                if ($pr->status !== RequestStatus::Submitted) {
                    throw new ProcurementException("{$pr->title()} is {$pr->status->label()}; only submitted requests go to bidding.");
                }

                if ($pr->returned_at) {
                    throw new ProcurementException("{$pr->title()} was returned to the office " . PurchaseRequest::RETURN_FOR[$pr->return_for] . '; it can go to bidding again once revised.');
                }

                if (! $pr->isReceived()) {
                    throw new ProcurementException("{$pr->title()} has not been received by the BAC yet (PR Receiving).");
                }

                if ($pr->ppmp->region !== $bidding->region) {
                    throw new ProcurementException("{$pr->title()} is a {$pr->ppmp->region->label()} request; this bidding is for {$bidding->region->label()}.");
                }

                if (! static::isBiddingLine($line)) {
                    throw new ProcurementException("\"{$line->description}\" is not for competitive bidding ({$line->ppmpItem?->effectiveMode()?->name}); it goes on an RFQ.");
                }

                if (in_array($line->id, $taken, true)) {
                    throw new ProcurementException("\"{$line->description}\" of {$pr->title()} is already in another bidding.");
                }

                // One fund source per bidding (its number carries it)
                if ((int) $line->ppmpItem?->fund_source_id !== $fund) {
                    throw new ProcurementException("\"{$line->description}\" is charged to {$line->ppmpItem?->fundSource?->code}; this bidding is for {$bidding->fundSource->code} items (one fund source per bidding).");
                }

                $lot ??= $bidding->lots()->create(['lot_no' => (int) $bidding->lots()->max('lot_no') + 1]);
                $lot->items()->create(['purchase_request_item_id' => $line->id, 'sort_order' => (int) $lot->items()->max('sort_order') + 1]);
                $taken[] = $line->id;
            }

            $bidding->update(['updated_by' => $user->id]);
            $this->recalculate($bidding);

            foreach ($lines->groupBy('purchase_request_id') as $prLines) {
                $names = $prLines->pluck('description')->implode(', ');
                app(RequestTrail::class)->record($prLines->first()->request, 'bid_added', "For public bidding: put in {$lot->name()} of {$bidding->title()}: {$names}",
                    $user, null, route('procurement.biddings.show', $bidding, false));
            }

            return $bidding;
        });
    }

    public function removeLine(Bidding $bidding, User $user, int $lotItemId): Bidding
    {
        return DB::transaction(function () use ($bidding, $user, $lotItemId) {
            $bidding = $this->lockLotsEditable($bidding);
            $item = BiddingLotItem::with(['line.request', 'lot'])->whereKey($lotItemId)->whereHas('lot', fn ($q) => $q->where('bidding_id', $bidding->id))->firstOrFail();
            $item->delete();
            app(RequestTrail::class)->record($item->line->request, 'bid_removed', "\"{$item->line->description}\" taken off {$bidding->title()}", $user, null, route('procurement.biddings.show', $bidding, false));

            $bidding->update(['updated_by' => $user->id]);
            $this->recalculate($bidding);

            return $bidding;
        });
    }

    /** Move a line to another lot of the bidding. */
    public function moveLine(Bidding $bidding, User $user, int $lotItemId, int $lotId): Bidding
    {
        return DB::transaction(function () use ($bidding, $user, $lotItemId, $lotId) {
            $bidding = $this->lockLotsEditable($bidding);
            $item = BiddingLotItem::whereKey($lotItemId)->whereHas('lot', fn ($q) => $q->where('bidding_id', $bidding->id))->firstOrFail();
            $lot = $bidding->lots()->whereKey($lotId)->firstOrFail();

            $item->update(['bidding_lot_id' => $lot->id, 'sort_order' => (int) $lot->items()->max('sort_order') + 1]);
            $bidding->update(['updated_by' => $user->id]);
            $this->recalculate($bidding);

            return $bidding;
        });
    }

    /** A new (empty) lot, or a lot's title. */
    public function saveLot(Bidding $bidding, User $user, ?int $lotId, ?string $title): BiddingLot
    {
        return DB::transaction(function () use ($bidding, $user, $lotId, $title) {
            $bidding = $this->lockLotsEditable($bidding);
            $lot = $lotId
                ? $bidding->lots()->whereKey($lotId)->firstOrFail()
                : $bidding->lots()->make(['lot_no' => (int) $bidding->lots()->max('lot_no') + 1]);
            $lot->fill(['title' => $title])->save();
            $bidding->update(['updated_by' => $user->id]);

            return $lot;
        });
    }

    /** Remove an empty lot; the lots after it move up (Lot 3 becomes Lot 2). */
    public function deleteLot(Bidding $bidding, User $user, int $lotId): void
    {
        DB::transaction(function () use ($bidding, $user, $lotId) {
            $bidding = $this->lockLotsEditable($bidding);
            $lot = $bidding->lots()->whereKey($lotId)->firstOrFail();

            if ($lot->items()->exists()) {
                throw new ProcurementException("{$lot->name()} has items; move or remove them first.");
            }

            $lot->delete();
            $bidding->lots()->orderBy('lot_no')->get()->each(fn (BiddingLot $l, int $i) => $l->update(['lot_no' => $i + 1]));
            $bidding->update(['updated_by' => $user->id]);
        });
    }

    // ---------- Steps ----------

    /**
     * Record a step (the lots must have their items: each step goes on the PRs' trail). $data (validated by
     * the controller), by step:
     *   preproc_set: preproc_at · preproc_held: date ·
     *   advertised: date, bidding_at, prebid_at?, philgeps_link?, website_link? ·
     *   prebid_held: date, bidding_at? (re-set), website_link? · supplemental: date, bidding_at? (re-set) · bidding: date
     *   remarks: any step (the reason, for a re-set bidding date).
     */
    public function recordStep(Bidding $bidding, User $user, BiddingStep $step, array $data): Bidding
    {
        return DB::transaction(function () use ($bidding, $user, $step, $data) {
            $bidding = Bidding::whereKey($bidding->id)->lockForUpdate()->firstOrFail();

            if (! in_array($step, $this->nextSteps($bidding), true)) {
                $next = collect($this->nextSteps($bidding))->map->label()->implode(' or ');
                throw new ProcurementException($bidding->status === BiddingStatus::Cancelled
                    ? "{$bidding->title()} is cancelled."
                    : ($next ? "Next is: {$next}." : 'The bids are evaluated next.'));
            }

            $date = isset($data['date']) ? Carbon::parse($data['date']) : null;
            $remarks = filled($data['remarks'] ?? null) ? trim($data['remarks']) : null;
            $details = [];
            $update = ['stage' => $step, 'updated_by' => $user->id];
            $trail = '';
            $this->assertHasItems($bidding);

            switch ($step) {
                case BiddingStep::PreprocSet:
                    $at = Carbon::parse($data['preproc_at']);
                    $details = ['preproc_at' => $at->toDateTimeString(), 'previous' => $bidding->preproc_at?->toDateTimeString()];
                    $update['preproc_at'] = $at;
                    $trail = "Pre-procurement conference of {$bidding->title()} " . ($bidding->preproc_at ? 're-set to ' : 'set for ') . $at->format('M d, Y g:i A');
                    $date = $at;
                    break;

                case BiddingStep::PreprocHeld:
                    $update['preproc_held_at'] = $date;
                    $trail = "Pre-procurement conference of {$bidding->title()} held " . $date->format('M d, Y');
                    break;

                case BiddingStep::Advertised:
                    $biddingAt = Carbon::parse($data['bidding_at']);
                    $prebidAt = filled($data['prebid_at'] ?? null) ? Carbon::parse($data['prebid_at']) : null;
                    $this->assertAfter($biddingAt, $date, 'The public bidding is after the advertisement.');
                    if ($prebidAt) {
                        $this->assertAfter($prebidAt, $date, 'The pre-bid conference is after the advertisement.');
                        $this->assertAfter($biddingAt, $prebidAt, 'The public bidding is after the pre-bid conference.');
                    }
                    $update += [
                        'advertised_at' => $date,
                        'bidding_at'    => $biddingAt,
                        'prebid_at'     => $prebidAt,
                        'philgeps_link' => $data['philgeps_link'] ?? null,
                        'website_link'  => $data['website_link'] ?? null,
                    ];
                    $details = array_filter([
                        'bidding_at'    => $biddingAt->toDateTimeString(),
                        'prebid_at'     => $prebidAt?->toDateTimeString(),
                        'philgeps_link' => $data['philgeps_link'] ?? null,
                        'website_link'  => $data['website_link'] ?? null,
                    ]);
                    $trail = "Invitation to bid ({$bidding->title()}) advertised " . $date->format('M d, Y')
                        . ($prebidAt ? '; pre-bid conference ' . $prebidAt->format('M d, Y g:i A') : '')
                        . '; public bidding ' . $biddingAt->format('M d, Y g:i A');
                    break;

                case BiddingStep::PrebidHeld:
                case BiddingStep::Supplemental:
                    $held = $step === BiddingStep::PrebidHeld;
                    if ($held) {
                        $update['prebid_held_at'] = $date;
                    }
                    if (filled($data['website_link'] ?? null)) {
                        $update['website_link'] = $data['website_link'];
                        $details['website_link'] = $data['website_link'];
                    }
                    $trail = ($held ? 'Pre-bid conference held ' : 'Supplemental bid bulletin issued ') . $date->format('M d, Y');

                    // The public bidding moved: the earlier date stays in the history, with the reason
                    if (filled($data['bidding_at'] ?? null)) {
                        $new = Carbon::parse($data['bidding_at']);
                        if (! $bidding->bidding_at || ! $new->equalTo($bidding->bidding_at)) {
                            if (! $remarks) {
                                throw new ProcurementException('State why the public bidding is re-set.');
                            }
                            $this->assertAfter($new, $date, 'The re-set public bidding is after ' . ($held ? 'the pre-bid conference.' : 'the bulletin.'));
                            $details += ['bidding_at' => $new->toDateTimeString(), 'previous' => $bidding->bidding_at?->toDateTimeString()];
                            $update += ['bidding_at' => $new, 'bidding_resets' => $bidding->bidding_resets + 1];
                            $trail .= '; public bidding re-set to ' . $new->format('M d, Y g:i A');
                        }
                    }
                    break;

                case BiddingStep::Bidding:
                    $update['bidding_held_at'] = $date;
                    $trail = 'Public bidding held ' . $date->format('M d, Y');
                    break;

                default:
                    throw new ProcurementException('This step is recorded lot by lot, once the bids are evaluated.');
            }

            $bidding->update($update);
            BiddingEvent::create([
                'bidding_id'  => $bidding->id,
                'step'        => $step,
                'happened_at' => $date,
                'details'     => $details ?: null,
                'remarks'     => $remarks ? mb_substr($remarks, 0, 1000) : null,
                'user_id'     => $user->id,
                'created_at'  => now(),
            ]);
            app(RequestTrail::class)->recordForBidding($bidding->fresh(), 'bid_' . $step->value, $trail, $user, $remarks);

            return $bidding;
        });
    }

    /** Cancel a bidding (e.g. the requests are withdrawn): its lines are free for another bidding. The number is not reused. */
    public function cancel(Bidding $bidding, User $user, string $reason): Bidding
    {
        return DB::transaction(function () use ($bidding, $user, $reason) {
            $bidding = Bidding::whereKey($bidding->id)->lockForUpdate()->firstOrFail();

            if ($bidding->status !== BiddingStatus::Ongoing) {
                throw new ProcurementException("{$bidding->title()} is already cancelled.");
            }

            $bidding->update(['status' => BiddingStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => $reason, 'updated_by' => $user->id]);
            app(RequestTrail::class)->recordForBidding($bidding, 'bid_cancelled', "{$bidding->title()} cancelled; the items can go to another bidding", $user, $reason);

            return $bidding;
        });
    }

    /** The open bidding a request's lines are in, if any. */
    public function openBiddingFor(PurchaseRequest $request): ?Bidding
    {
        return Bidding::where('status', '!=', BiddingStatus::Cancelled)
            ->whereHas('lotItems.line', fn ($q) => $q->where('purchase_request_id', $request->id))
            ->first();
    }

    /** Ids of PR / JR lines in a bidding that is not cancelled. */
    public function takenLineIds(): array
    {
        return DB::table('bidding_lot_items')
            ->join('bidding_lots', 'bidding_lots.id', '=', 'bidding_lot_items.bidding_lot_id')
            ->join('biddings', 'biddings.id', '=', 'bidding_lots.bidding_id')
            ->where('biddings.status', '!=', BiddingStatus::Cancelled->value)
            ->pluck('bidding_lot_items.purchase_request_item_id')->map(fn ($id) => (int) $id)->all();
    }

    /** Before a step: lots with items, of requests still submitted and not returned. */
    protected function assertHasItems(Bidding $bidding): void
    {
        $bidding->load('lots.items.line.request');

        if ($bidding->lots->isEmpty() || $bidding->lots->every(fn ($lot) => $lot->items->isEmpty())) {
            throw new ProcurementException('Add the items to bid first.');
        }

        if ($empty = $bidding->lots->first(fn ($lot) => $lot->items->isEmpty())) {
            throw new ProcurementException("{$empty->name()} has no items; add items to it or remove it.");
        }

        $stale = $bidding->lines()->map->request->first(fn ($pr) => $pr->status !== RequestStatus::Submitted || $pr->returned_at);
        if ($stale) {
            throw new ProcurementException("{$stale->title()} is " . ($stale->returned_at ? 'returned to the office' : $stale->status->label()) . '; remove its items first.');
        }
    }

    protected function assertAfter(Carbon $later, ?Carbon $earlier, string $message): void
    {
        if ($earlier && $later->lt($earlier->copy()->startOfDay())) {
            throw new ProcurementException($message);
        }
    }

    protected function assertNotCancelled(Bidding $bidding): void
    {
        if ($bidding->status === BiddingStatus::Cancelled) {
            throw new ProcurementException("{$bidding->title()} is cancelled.");
        }
    }

    protected function recalculate(Bidding $bidding): void
    {
        $total = 0;
        foreach ($bidding->lots()->with('items.line')->get() as $lot) {
            $cents = $lot->items->sum(fn ($i) => Money::toCents($i->line->total_cost));
            $lot->update(['abc' => Money::fromCents($cents)]);
            $total += $cents;
        }
        $bidding->update(['total_abc' => Money::fromCents($total)]);
    }

    protected function lockLotsEditable(Bidding $bidding): Bidding
    {
        $locked = Bidding::whereKey($bidding->id)->lockForUpdate()->firstOrFail();

        if (! $locked->lotsEditable()) {
            throw new ProcurementException($locked->status === BiddingStatus::Cancelled
                ? "{$locked->title()} is cancelled."
                : "{$locked->title()} is advertised; its lots can no longer change.");
        }

        return $locked;
    }
}
