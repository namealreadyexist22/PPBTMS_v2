<?php

namespace App\Services\Procurement;

use App\Enums\AoqStatus;
use App\Enums\RfqStatus;
use App\Exceptions\ProcurementException;
use App\Models\Procurement\Aoq;
use App\Models\Procurement\AoqQuote;
use App\Models\Procurement\AoqSupplier;
use App\Models\Procurement\Rfq;
use App\Models\Procurement\RfqItem;
use App\Models\User;
use App\Support\Money;
use App\Support\Quantity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Abstract of Quotations.
 *
 * One per issued RFQ. The BAC records each supplier that submitted a quotation and its unit
 * price per item. A quote counts when the supplier is responsive, the offer meets the
 * specifications and its total is within the item's ABC; each item goes to its lowest counting
 * quote (per item award, so one RFQ can go to several suppliers). A tie is broken by the BAC
 * (e.g. draw lots) and chosen on awarding. An item without a counting quote fails.
 */
class AoqService
{
    /** Printed only, so they can still change after the award. */
    public const PRINT_FIELDS = ['bac_chair_name', 'bac_vice_chair_name', 'bac_members', 'hope_name'];

    public const HEADER_FIELDS = ['opened_at', 'remarks', ...self::PRINT_FIELDS];

    /** The RFQ's AOQ, started with the region's BAC and HOPE as signatories. */
    public function start(Rfq $rfq, User $user): Aoq
    {
        return DB::transaction(function () use ($rfq, $user) {
            $rfq = Rfq::whereKey($rfq->id)->lockForUpdate()->firstOrFail();

            if ($aoq = $rfq->aoq()->first()) {
                return $aoq;
            }

            if ($rfq->status !== RfqStatus::Issued) {
                throw new ProcurementException("{$rfq->title()} is {$rfq->status->label()}; only an issued RFQ has an abstract of quotations.");
            }

            $officials = app(OfficialsService::class)->all($rfq->region);

            return $rfq->aoq()->create([
                'region'              => $rfq->region,
                'status'              => AoqStatus::Draft,
                'opened_at'           => $rfq->submission_deadline,
                'bac_chair_name'      => $officials['bac_chair']?->fullname,
                'bac_vice_chair_name' => $officials['bac_vice_chair']?->fullname,
                'bac_members'         => $officials['bac_members']->map->fullname->values()->all(),
                'hope_name'           => $officials['hope']?->fullname,
                'created_by'          => $user->id,
                'updated_by'          => $user->id,
            ]);
        });
    }

    public function updateHeader(Aoq $aoq, User $user, array $data): Aoq
    {
        $fields = $aoq->isEditable() ? self::HEADER_FIELDS : self::PRINT_FIELDS;

        if (array_key_exists('bac_members', $data)) {
            $data['bac_members'] = array_values(array_filter(array_map('trim', (array) $data['bac_members']), 'strlen'));
        }

        $aoq->update(array_intersect_key($data, array_flip($fields)) + ['updated_by' => $user->id]);

        return $aoq;
    }

    /**
     * Add or change a supplier and its quotes. $quotes: rfq_item_id => [unit_price, brand, is_compliant];
     * a blank unit price is no quote for that item.
     */
    public function saveSupplier(Aoq $aoq, User $user, array $data, array $quotes, ?int $supplierId = null): AoqSupplier
    {
        return DB::transaction(function () use ($aoq, $user, $data, $quotes, $supplierId) {
            $aoq = $this->lockEditable($aoq);
            $itemIds = $aoq->rfq->items()->pluck('id')->map(fn ($id) => (int) $id)->all();
            $name = trim((string) ($data['name'] ?? ''));

            if ($name === '') {
                throw new ProcurementException('Enter the supplier\'s name.');
            }

            $duplicate = $aoq->suppliers()->whereRaw('lower(name) = ?', [mb_strtolower($name)])
                ->when($supplierId, fn ($q) => $q->whereKeyNot($supplierId))->exists();
            if ($duplicate) {
                throw new ProcurementException("{$name} is already on this abstract.");
            }

            $fields = [
                'name'          => $name,
                'address'       => $data['address'] ?? null,
                'tin'           => $data['tin'] ?? null,
                'contact'       => $data['contact'] ?? null,
                'is_responsive' => (bool) ($data['is_responsive'] ?? true),
                'remarks'       => $data['remarks'] ?? null,
            ];

            if ($supplierId) {
                $supplier = $aoq->suppliers()->whereKey($supplierId)->firstOrFail();
                $supplier->update($fields);
            } else {
                $supplier = $aoq->suppliers()->create($fields + ['sort_order' => (int) $aoq->suppliers()->max('sort_order') + 1]);
            }

            foreach ($quotes as $itemId => $quote) {
                if (! in_array((int) $itemId, $itemIds, true)) {
                    throw new ProcurementException('A quoted item is not on this RFQ.');
                }

                $price = $quote['unit_price'] ?? null;
                if ($price !== null && $price !== '' && Money::toCents($price) <= 0) {
                    throw new ProcurementException('A unit price must be greater than zero; leave it blank for no quote.');
                }

                $supplier->quotes()->updateOrCreate(['rfq_item_id' => (int) $itemId], [
                    'unit_price'   => $price === '' ? null : $price,
                    'brand'        => $quote['brand'] ?? null,
                    'is_compliant' => (bool) ($quote['is_compliant'] ?? true),
                ]);
            }

            $aoq->update(['updated_by' => $user->id]);

            return $supplier;
        });
    }

    public function removeSupplier(Aoq $aoq, User $user, int $supplierId): void
    {
        DB::transaction(function () use ($aoq, $user, $supplierId) {
            $aoq = $this->lockEditable($aoq);
            $aoq->suppliers()->whereKey($supplierId)->firstOrFail()->delete();
            $aoq->update(['updated_by' => $user->id]);
        });
    }

    /**
     * The abstract, one row per RFQ item:
     *   item (RfqItem with line), abc_cents,
     *   quotes: supplier id => [quote, total_cents, issue (null when it counts: 'No quote', 'Not responsive', 'Not compliant', 'Over ABC'), rank],
     *   lowest: supplier ids tied at the lowest counting total ([] = the item fails),
     *   awarded: supplier id it was awarded to, or null.
     */
    public function evaluate(Aoq $aoq): Collection
    {
        $aoq->loadMissing(['rfq.items.line', 'suppliers.quotes']);

        return $aoq->rfq->items->map(function (RfqItem $item) use ($aoq) {
            $line = $item->line;
            $abc = Money::toCents($line->total_cost);
            $hundredths = Quantity::toHundredths($line->quantity);
            $awarded = null;

            $quotes = $aoq->suppliers->mapWithKeys(function (AoqSupplier $supplier) use ($item, $abc, $hundredths, &$awarded) {
                /** @var AoqQuote|null $quote */
                $quote = $supplier->quotes->firstWhere('rfq_item_id', $item->id);
                $priced = $quote && $quote->unit_price !== null;
                $total = $priced ? (int) round($hundredths * Money::toCents($quote->unit_price) / 100) : null;

                if ($quote?->is_awarded) {
                    $awarded = $supplier->id;
                }

                return [$supplier->id => [
                    'quote' => $quote,
                    'total_cents' => $total,
                    'issue' => match (true) {
                        ! $priced                => 'No quote',
                        ! $supplier->is_responsive => 'Not responsive',
                        ! $quote->is_compliant    => 'Not compliant',
                        $total > $abc            => 'Over ABC',
                        default                  => null,
                    },
                ]];
            });

            $counting = $quotes->filter(fn ($q) => $q['issue'] === null);
            $ranks = $counting->pluck('total_cents')->unique()->sort()->values()->flip();
            $quotes = $quotes->map(fn ($q) => $q + ['rank' => $q['issue'] === null ? $ranks[$q['total_cents']] + 1 : null]);
            $min = $counting->min('total_cents');

            return [
                'item'      => $item,
                'abc_cents' => $abc,
                'quotes'    => $quotes,
                'lowest'    => $min === null ? [] : $counting->filter(fn ($q) => $q['total_cents'] === $min)->keys()->all(),
                'awarded'   => $awarded,
            ];
        });
    }

    /**
     * Award each item to its lowest counting quote; $tieBreaks (rfq_item_id => supplier id) picks
     * among suppliers tied at the lowest. Items without a counting quote fail.
     */
    public function award(Aoq $aoq, User $user, array $tieBreaks = []): Aoq
    {
        return DB::transaction(function () use ($aoq, $user, $tieBreaks) {
            $aoq = $this->lockEditable($aoq);
            $rows = $this->evaluate($aoq);

            if ($aoq->suppliers->isEmpty()) {
                throw new ProcurementException('Add the suppliers\' quotations first.');
            }

            if ($rows->every(fn ($row) => $row['lowest'] === [])) {
                throw new ProcurementException('No item has a quotation that can be awarded (responsive, compliant and within the ABC).');
            }

            $winners = [];
            foreach ($rows as $row) {
                $lowest = $row['lowest'];

                if (count($lowest) > 1) {
                    $pick = (int) ($tieBreaks[$row['item']->id] ?? 0);

                    if (! in_array($pick, $lowest, true)) {
                        throw new ProcurementException("\"{$row['item']->line->description}\" has a tie; choose the supplier the BAC awards it to.");
                    }

                    $lowest = [$pick];
                }

                if ($lowest) {
                    $winners[] = $row['quotes'][$lowest[0]]['quote']->id;
                }
            }

            AoqQuote::whereIn('aoq_supplier_id', $aoq->suppliers->pluck('id'))->update(['is_awarded' => false]);
            AoqQuote::whereIn('id', $winners)->update(['is_awarded' => true]);
            $aoq->update(['status' => AoqStatus::Awarded, 'awarded_at' => now(), 'updated_by' => $user->id]);

            return $aoq;
        });
    }

    /** Back to draft to correct the quotations; the award is cleared. */
    public function reopen(Aoq $aoq, User $user): Aoq
    {
        return DB::transaction(function () use ($aoq, $user) {
            $aoq = Aoq::whereKey($aoq->id)->lockForUpdate()->firstOrFail();

            if ($aoq->status !== AoqStatus::Awarded) {
                throw new ProcurementException("{$aoq->title()} is not awarded.");
            }

            AoqQuote::whereIn('aoq_supplier_id', $aoq->suppliers()->pluck('id'))->update(['is_awarded' => false]);
            $aoq->update(['status' => AoqStatus::Draft, 'awarded_at' => null, 'updated_by' => $user->id]);

            return $aoq;
        });
    }

    public function deleteDraft(Aoq $aoq): void
    {
        DB::transaction(fn () => $this->lockEditable($aoq)->delete());
    }

    protected function lockEditable(Aoq $aoq): Aoq
    {
        $locked = Aoq::whereKey($aoq->id)->lockForUpdate()->firstOrFail();

        if (! $locked->isEditable()) {
            throw new ProcurementException("{$locked->title()} is {$locked->status->label()}; reopen it to make changes.");
        }

        return $locked;
    }
}
