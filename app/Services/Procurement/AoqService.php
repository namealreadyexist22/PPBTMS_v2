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
use App\Models\Procurement\Supplier;
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
    /**
     * Printed only, so they can still change after the award. Blank = the default (signatories()).
     * "Evaluated by" is the TWG evaluator (assignEvaluator, completeEvaluation), not entered here.
     */
    public const PRINT_FIELDS = [
        'prepared_by_name', 'prepared_by_designation', 'certified_by_name', 'certified_by_designation',
        'bac_chair_name', 'bac_vice_chair_name', 'bac_members', 'hope_name',
    ];

    /** The technical comment is the TWG's (saveEvaluation). */
    public const HEADER_FIELDS = ['opened_at', 'remarks', ...self::PRINT_FIELDS];

    /**
     * The RFQ's AOQ. Its signatories default to the AOQ's preparer, the RFQ's signatory and the
     * region's BAC and HOPE (signatories()); the end user (the first PR's requester) is assigned
     * as the TWG evaluator when they are a TWG member.
     */
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

            $aoq = $rfq->aoq()->create([
                'region'     => $rfq->region,
                'status'     => AoqStatus::Draft,
                'opened_at'  => $rfq->submission_deadline,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $endUser = $rfq->load('items.line.request')->requests()->first()?->requested_by_name;
            $twg = $endUser ? User::where('is_activated', true)->where('fullname', $endUser)->get()
                ->first(fn (User $u) => $u->canAccessPermission(Aoq::EVALUATE_PERMISSION)) : null;
            if ($twg) {
                $this->assignEvaluator($aoq, $twg);
            }

            app(RequestTrail::class)->recordForAoq($aoq, 'aoq_started', "Quotations opened; Abstract of Quotations {$aoq->title()} started", $user);

            return $aoq;
        });
    }

    /** Opening, remarks and the TWG evaluator ('evaluator_id', while a draft); the printed names any time. */
    public function updateHeader(Aoq $aoq, User $user, array $data): Aoq
    {
        $fields = $aoq->isEditable() ? self::HEADER_FIELDS : self::PRINT_FIELDS;

        // Blank names print the default
        foreach (self::PRINT_FIELDS as $field) {
            if (array_key_exists($field, $data) && $field !== 'bac_members') {
                $data[$field] = filled($data[$field]) ? trim($data[$field]) : null;
            }
        }
        if (array_key_exists('bac_members', $data)) {
            $data['bac_members'] = array_values(array_filter(array_map('trim', (array) $data['bac_members']), 'strlen')) ?: null;
        }

        return DB::transaction(function () use ($aoq, $user, $data, $fields) {
            if (array_key_exists('evaluator_id', $data) && $aoq->isEditable()) {
                $evaluator = $data['evaluator_id'] ? User::find($data['evaluator_id']) : null;
                if ($data['evaluator_id'] && ! $evaluator?->canAccessPermission(Aoq::EVALUATE_PERMISSION)) {
                    throw new ProcurementException('The evaluator must be a TWG member.');
                }
                $this->assignEvaluator($aoq, $evaluator);
            }

            $aoq->update(array_intersect_key($data, array_flip($fields)) + ['updated_by' => $user->id]);

            return $aoq;
        });
    }

    /** The one TWG member who evaluates the AOQ, printed as "Evaluated by" (null: none yet). */
    public function assignEvaluator(Aoq $aoq, ?User $evaluator): void
    {
        $aoq->evaluators()->sync($evaluator ? [$evaluator->id] : []);
        $aoq->update(['evaluated_by_name' => $evaluator ? $this->nameOf($evaluator) : null, 'evaluated_by_designation' => $evaluator?->designation]);
    }

    /**
     * The names printed: each as set on the AOQ, else its default — prepared by whoever started
     * it, certified by the RFQ's signatory, the region's BAC and HOPE (Signatories & BAC).
     * Each person: [name, designation]; bac_members: list of names.
     */
    public function signatories(Aoq $aoq): array
    {
        $defaults = $this->defaultSignatories($aoq);
        $person = fn (string $who) => filled($aoq->{"{$who}_name"})
            ? [$aoq->{"{$who}_name"}, $aoq->{"{$who}_designation"}]
            : [$defaults[$who][0], $aoq->{"{$who}_designation"} ?: $defaults[$who][1]];

        return [
            'prepared_by'  => $person('prepared_by'),
            'evaluated_by' => [$aoq->evaluated_by_name, $aoq->evaluated_by_designation],
            'certified_by' => $person('certified_by'),
            'bac_chair'    => $aoq->bac_chair_name ?: $defaults['bac_chair'],
            'bac_vice'     => $aoq->bac_vice_chair_name ?: $defaults['bac_vice'],
            'bac_members'  => $aoq->bac_members ?: $defaults['bac_members'],
            'hope'         => $aoq->hope_name ?: $defaults['hope'],
        ];
    }

    /** The defaults, shown in grey where a name is not set. */
    public function defaultSignatories(Aoq $aoq): array
    {
        $officials = app(OfficialsService::class)->all($aoq->region);
        $creator = $aoq->creator;

        return [
            'prepared_by'  => [$creator ? $this->nameOf($creator) : null, $creator?->designation],
            'certified_by' => [$aoq->rfq->signatory_name, $aoq->rfq->signatory_designation],
            'bac_chair'    => $officials['bac_chair']?->fullname,
            'bac_vice'     => $officials['bac_vice_chair']?->fullname,
            'bac_members'  => $officials['bac_members']->map->fullname->values()->all(),
            'hope'         => $officials['hope']?->fullname,
        ];
    }

    /**
     * Add or change a supplier and its quotes. $quotes: rfq_item_id => [unit_price, brand, file (PDF),
     * remove_file]; a blank unit price is no quote for that item. $data may hold the supplier's
     * quotation PDF ('attachment') or 'remove_attachment'. Whether an offer meets the specs is the
     * TWG's finding: changing an offer clears it, so the TWG looks at it again.
     */
    public function saveSupplier(Aoq $aoq, User $user, array $data, array $quotes, ?int $supplierId = null): AoqSupplier
    {
        $supplier = DB::transaction(function () use ($aoq, $user, $data, $quotes, $supplierId) {
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
                'supplier_id'   => $this->directoryEntry($user, $name, $data)->id,
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

                $row = $supplier->quotes()->firstOrNew(['rfq_item_id' => (int) $itemId]);
                $row->fill([
                    'unit_price' => $price === '' ? null : $price,
                    'brand'      => filled($quote['brand'] ?? null) ? $quote['brand'] : null,
                ]);
                $changed = $row->isDirty(['unit_price', 'brand']) || ! empty($quote['file']) || ! empty($quote['remove_file']);
                if ($changed && $row->exists) {
                    $row->fill(['is_compliant' => null, 'twg_remarks' => null]);
                }
                $row->save();
            }

            $aoq->update(['updated_by' => $user->id]);

            return $supplier;
        });

        // Files once the data is saved (a refused save leaves no stray file)
        $files = app(AoqAttachmentService::class);
        if (! empty($data['attachment'])) {
            $files->attach($aoq, $supplier, $data['attachment']);
        } elseif (! empty($data['remove_attachment'])) {
            $files->detach($supplier);
        }

        foreach ($quotes as $itemId => $quote) {
            $row = $supplier->quotes()->where('rfq_item_id', (int) $itemId)->first();
            if (! empty($quote['file'])) {
                $files->attach($aoq, $row, $quote['file']);
            } elseif (! empty($quote['remove_file']) && $row?->attachment_path) {
                $files->detach($row);
            }
        }

        return $supplier;
    }

    public function removeSupplier(Aoq $aoq, User $user, int $supplierId): void
    {
        $paths = DB::transaction(function () use ($aoq, $user, $supplierId) {
            $aoq = $this->lockEditable($aoq);
            $supplier = $aoq->suppliers()->whereKey($supplierId)->firstOrFail();
            $paths = app(AoqAttachmentService::class)->pathsOfSupplier($supplier);
            $supplier->delete();
            $aoq->update(['updated_by' => $user->id]);

            return $paths;
        });

        app(AoqAttachmentService::class)->deleteFiles($paths);
    }

    /**
     * Send the quotations to the AOQ's TWG evaluator ("Evaluated by"; $evaluator assigns one first).
     * They check each offer against the specs, then complete or return it.
     */
    public function sendToTwg(Aoq $aoq, User $user, ?User $evaluator = null): Aoq
    {
        $aoq = DB::transaction(function () use ($aoq, $user, $evaluator) {
            $aoq = $this->lockEditable($aoq);

            if (! $aoq->quotes()->whereNotNull('unit_price')->exists()) {
                throw new ProcurementException('Add the suppliers\' quotations first.');
            }

            if ($evaluator) {
                if (! $evaluator->is_activated || ! $evaluator->canAccessPermission(Aoq::EVALUATE_PERMISSION)) {
                    throw new ProcurementException('The evaluator must be a TWG member.');
                }
                $this->assignEvaluator($aoq, $evaluator);
            }

            if (! $aoq->evaluators()->exists()) {
                throw new ProcurementException('Choose the TWG evaluator under "Evaluated by" first.');
            }

            $aoq->update(['status' => AoqStatus::ForEvaluation, 'sent_to_twg_at' => now(), 'return_reason' => null, 'updated_by' => $user->id]);

            return $aoq;
        });

        $evaluator = $aoq->evaluators()->first();
        app(RequestTrail::class)->recordForAoq($aoq, 'twg_sent', "{$aoq->title()} sent to the TWG for technical evaluation ({$evaluator->fullname})", $user);
        notify_user($evaluator, 'AOQ for TWG evaluation', "{$aoq->title()} ({$aoq->rfq->label()}) is assigned to you for technical evaluation.", route('procurement.twg.show', $aoq), 'fas fa-clipboard-check');

        return $aoq;
    }

    /**
     * The TWG's findings so far: $findings: quote id => [is_compliant (1, 0 or blank), twg_remarks],
     * plus the technical comment printed on the AOQ.
     */
    public function saveEvaluation(Aoq $aoq, User $user, array $findings, ?string $technicalComment): Aoq
    {
        return DB::transaction(function () use ($aoq, $user, $findings, $technicalComment) {
            $aoq = $this->lockForEvaluation($aoq, $user);
            $quotes = $aoq->quotes()->get()->keyBy('id');

            foreach ($findings as $quoteId => $finding) {
                $quote = $quotes->get((int) $quoteId) ?? throw new ProcurementException('An offer is not on this abstract.');
                $verdict = $finding['is_compliant'] ?? null;
                $quote->update([
                    'is_compliant' => $verdict === null || $verdict === '' ? null : (bool) $verdict,
                    'twg_remarks'  => filled($finding['twg_remarks'] ?? null) ? $finding['twg_remarks'] : null,
                ]);
            }

            $aoq->update(['technical_comment' => $technicalComment, 'updated_by' => $user->id]);

            return $aoq;
        });
    }

    /** Done: every offer has a finding; the evaluator signs "Evaluated by" and the BAC can award. */
    public function completeEvaluation(Aoq $aoq, User $user, array $findings, ?string $technicalComment): Aoq
    {
        $aoq = DB::transaction(function () use ($aoq, $user, $findings, $technicalComment) {
            $aoq = $this->saveEvaluation($aoq, $user, $findings, $technicalComment);

            $open = $aoq->quotes()->with('supplier')->whereNotNull('unit_price')->whereNull('is_compliant')->get()
                ->filter(fn ($q) => $q->supplier->is_responsive);
            if ($open->isNotEmpty()) {
                throw new ProcurementException("{$open->count()} offer(s) still have no finding; mark each as meeting the specs or not.");
            }

            $aoq->update([
                'status'                   => AoqStatus::Evaluated,
                'evaluated_at'             => now(),
                'evaluated_by'             => $user->id,
                'evaluated_by_name'        => $this->nameOf($user),
                'evaluated_by_designation' => $user->designation,
                'updated_by'               => $user->id,
            ]);

            return $aoq;
        });

        $failing = $aoq->quotes()->where('is_compliant', false)->count();
        app(RequestTrail::class)->recordForAoq($aoq, 'twg_evaluated', "Technical evaluation of {$aoq->title()} completed" . ($failing ? "; {$failing} offer(s) do not meet the specs" : '; the offers meet the specs'), $user, $aoq->technical_comment);
        $this->notifyBac($aoq, 'AOQ evaluated by the TWG', "{$aoq->title()} was evaluated by {$this->nameOf($user)}; it can now be awarded.");

        return $aoq;
    }

    /** Back to the BAC (e.g. a missing or unreadable spec sheet); it is sent again once corrected. */
    public function returnToBac(Aoq $aoq, User $user, string $reason): Aoq
    {
        $aoq = DB::transaction(function () use ($aoq, $user, $reason) {
            $aoq = $this->lockForEvaluation($aoq, $user);
            $aoq->update(['status' => AoqStatus::Draft, 'return_reason' => $reason, 'updated_by' => $user->id]);

            return $aoq;
        });

        app(RequestTrail::class)->recordForAoq($aoq, 'twg_returned', "{$aoq->title()} returned by the TWG to the BAC", $user, $reason);
        $this->notifyBac($aoq, 'AOQ returned by the TWG', "{$aoq->title()} was returned by {$this->nameOf($user)}: {$reason}");

        return $aoq;
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

        return $aoq->rfq->isPerLot() ? $this->evaluateLot($aoq) : $this->evaluateItems($aoq);
    }

    /**
     * Per lot: each supplier's standing, supplier id => [total_cents (null: incomplete), issue (null when
     * it counts: 'Not responsive', 'Incomplete lot', 'Not compliant', 'Lot over ABC'), rank], and
     * 'lowest' => supplier ids tied at the lowest counting lot total. A supplier counts when it is
     * responsive, quoted every item, every offer meets the specs (not found otherwise) and its
     * total is within the total ABC.
     */
    public function lotStanding(Aoq $aoq): array
    {
        $aoq->loadMissing(['rfq.items.line', 'suppliers.quotes']);
        $items = $aoq->rfq->items;
        $abc = $items->sum(fn (RfqItem $item) => Money::toCents($item->line->total_cost));

        $standing = $aoq->suppliers->mapWithKeys(function (AoqSupplier $supplier) use ($items, $abc) {
            $quotes = $items->map(fn (RfqItem $item) => $supplier->quotes->firstWhere('rfq_item_id', $item->id));
            $complete = $quotes->every(fn ($q) => $q && $q->unit_price !== null);
            $total = $complete ? $items->values()->sum(fn (RfqItem $item, $i) => $this->lineTotal($item, $quotes->values()[$i])) : null;

            return [$supplier->id => [
                'total_cents' => $total,
                'issue'       => match (true) {
                    ! $supplier->is_responsive                         => 'Not responsive',
                    ! $complete                                        => 'Incomplete lot',
                    $quotes->contains(fn ($q) => $q->is_compliant === false) => 'Not compliant',
                    $total > $abc                                      => 'Lot over ABC',
                    default                                            => null,
                },
            ]];
        });

        $counting = $standing->filter(fn ($s) => $s['issue'] === null);
        $ranks = $counting->pluck('total_cents')->unique()->sort()->values()->flip();
        $min = $counting->min('total_cents');

        return [
            'abc_cents' => $abc,
            'suppliers' => $standing->map(fn ($s) => $s + ['rank' => $s['issue'] === null ? $ranks[$s['total_cents']] + 1 : null])->all(),
            'lowest'    => $min === null ? [] : $counting->filter(fn ($s) => $s['total_cents'] === $min)->keys()->all(),
        ];
    }

    /** Per lot: the same rows, every item going to the lowest lot (its ranks and issues are the lot's). */
    protected function evaluateLot(Aoq $aoq): Collection
    {
        $lot = $this->lotStanding($aoq);

        return $this->evaluateItems($aoq)->map(function (array $row) use ($lot) {
            $row['quotes'] = $row['quotes']->map(function (array $q, int $supplierId) use ($lot) {
                $standing = $lot['suppliers'][$supplierId];

                return ['issue' => $q['issue'] === 'No quote' ? 'No quote' : $standing['issue'], 'rank' => $standing['rank']] + $q;
            });
            $row['lowest'] = $lot['lowest'];

            return $row;
        });
    }

    protected function lineTotal(RfqItem $item, AoqQuote $quote): int
    {
        return (int) round(Quantity::toHundredths($item->line->quantity) * Money::toCents($quote->unit_price) / 100);
    }

    protected function evaluateItems(Aoq $aoq): Collection
    {
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
                        $quote->is_compliant === false => 'Not compliant',   // null: not evaluated yet
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
        $aoq = DB::transaction(function () use ($aoq, $user, $tieBreaks) {
            $aoq = Aoq::whereKey($aoq->id)->lockForUpdate()->firstOrFail();

            if ($aoq->status !== AoqStatus::Evaluated) {
                throw new ProcurementException($aoq->status === AoqStatus::Awarded
                    ? "{$aoq->title()} is already awarded."
                    : "{$aoq->title()} is {$aoq->status->label()}; the TWG evaluates the offers before the award.");
            }

            $rows = $this->evaluate($aoq);

            if ($aoq->suppliers->isEmpty()) {
                throw new ProcurementException('Add the suppliers\' quotations first.');
            }

            $perLot = $aoq->rfq->isPerLot();
            if ($rows->every(fn ($row) => $row['lowest'] === [])) {
                throw new ProcurementException($perLot
                    ? 'No supplier\'s lot can be awarded (responsive, every item quoted and meeting the specs, within the total ABC).'
                    : 'No item has a quotation that can be awarded (responsive, compliant and within the ABC).');
            }

            $winners = [];
            foreach ($rows as $row) {
                $lowest = $row['lowest'];

                if (count($lowest) > 1) {
                    // Per lot: one pick for the whole lot ($tieBreaks['lot'])
                    $pick = (int) ($tieBreaks[$perLot ? 'lot' : $row['item']->id] ?? 0);

                    if (! in_array($pick, $lowest, true)) {
                        throw new ProcurementException($perLot
                            ? 'The lot has a tie; choose the supplier the BAC awards it to.'
                            : "\"{$row['item']->line->description}\" has a tie; choose the supplier the BAC awards it to.");
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

        // On each request's trail: what its own items went to
        $rows = $this->evaluate($aoq->fresh());
        $perRequest = $rows->groupBy(fn ($row) => $row['item']->line->purchase_request_id)->map(fn ($group) => $group->map(function ($row) use ($aoq) {
            $name = $row['item']->line->description;
            if (! $row['awarded']) {
                return "{$name}: failed (no responsive quotation within the ABC)";
            }

            $supplier = $aoq->suppliers->firstWhere('id', $row['awarded']);

            return "{$name}: {$supplier->name} (₱" . Money::format(Money::fromCents($row['quotes'][$row['awarded']]['total_cents'])) . ')';
        })->implode('; '))->all();
        app(RequestTrail::class)->recordForAoq($aoq, 'aoq_awarded', "{$aoq->title()} awarded —", $user, null, $perRequest);

        return $aoq;
    }

    /**
     * Back to draft to correct the quotations: from the TWG (recalled), after the evaluation or
     * after the award (cleared). The TWG's findings are kept; it is sent to the TWG again.
     */
    public function reopen(Aoq $aoq, User $user): Aoq
    {
        $previous = null;
        $aoq = DB::transaction(function () use ($aoq, $user, &$previous) {
            $aoq = Aoq::whereKey($aoq->id)->lockForUpdate()->firstOrFail();
            $previous = $aoq->status;

            if ($aoq->status === AoqStatus::Draft) {
                throw new ProcurementException("{$aoq->title()} is already a draft.");
            }

            AoqQuote::whereIn('aoq_supplier_id', $aoq->suppliers()->pluck('id'))->update(['is_awarded' => false]);
            $aoq->update(['status' => AoqStatus::Draft, 'awarded_at' => null, 'evaluated_at' => null, 'updated_by' => $user->id]);

            return $aoq;
        });

        $what = match ($previous) {
            AoqStatus::ForEvaluation => 'recalled from the TWG',
            AoqStatus::Awarded       => 'reopened; the award is cleared',
            default                  => 'reopened to correct the quotations',
        };
        app(RequestTrail::class)->recordForAoq($aoq, 'aoq_reopened', "{$aoq->title()} {$what}", $user);

        return $aoq;
    }

    public function deleteDraft(Aoq $aoq): void
    {
        $paths = DB::transaction(function () use ($aoq) {
            $aoq = $this->lockEditable($aoq);
            $paths = app(AoqAttachmentService::class)->pathsOfAoq($aoq);
            app(RequestTrail::class)->recordForAoq($aoq, 'aoq_deleted', "{$aoq->title()} deleted", auth()->user());
            $aoq->delete();

            return $paths;
        });

        app(AoqAttachmentService::class)->deleteFiles($paths);
    }

    /** The BAC side (who started the AOQ) is told of the TWG's result. */
    protected function notifyBac(Aoq $aoq, string $title, string $message): void
    {
        if ($creator = $aoq->creator) {
            notify_user($creator, $title, $message, route('procurement.aoqs.show', $aoq), 'fas fa-balance-scale');
        }
    }

    /** fullname is a generated column: not on a user model that was not read back. */
    protected function nameOf(User $user): ?string
    {
        return $user->fullname ?? $user->fresh()?->fullname;
    }

    protected function lockForEvaluation(Aoq $aoq, User $user): Aoq
    {
        $locked = Aoq::whereKey($aoq->id)->lockForUpdate()->firstOrFail();

        if ($locked->status !== AoqStatus::ForEvaluation) {
            throw new ProcurementException("{$locked->title()} is {$locked->status->label()}; it is not with the TWG.");
        }

        if (! $locked->canBeEvaluatedBy($user)) {
            throw new ProcurementException("Only the TWG members assigned to {$locked->title()} can evaluate it.");
        }

        return $locked;
    }

    /**
     * The supplier's directory entry, added when it is new; details missing there are filled in
     * from the quotation, details already there are kept (they are changed on the Suppliers page).
     */
    protected function directoryEntry(User $user, string $name, array $data): Supplier
    {
        $supplier = Supplier::whereRaw('lower(name) = ?', [mb_strtolower($name)])->first();
        $details = array_filter([
            'address' => $data['address'] ?? null,
            'tin'     => $data['tin'] ?? null,
            'contact' => $data['contact'] ?? null,
        ], 'filled');

        if (! $supplier) {
            return Supplier::create(['name' => $name, 'created_by' => $user->id, 'updated_by' => $user->id] + $details);
        }

        $missing = array_filter($details, fn ($value, $field) => blank($supplier->{$field}), ARRAY_FILTER_USE_BOTH);
        if ($missing) {
            $supplier->update($missing + ['updated_by' => $user->id]);
        }

        return $supplier;
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
