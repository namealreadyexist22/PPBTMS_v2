<?php

namespace App\Services\Procurement;

use App\Enums\PpmpStatus;
use App\Enums\RequestKind;
use App\Enums\RequestStatus;
use App\Exceptions\ProcurementException;
use App\Models\Procurement\Ppmp;
use App\Models\Procurement\PpmpItem;
use App\Models\Procurement\PpmpPap;
use App\Models\Procurement\PurchaseRequest;
use App\Models\Procurement\PurchaseRequestItem;
use App\Models\User;
use App\Support\Money;
use App\Support\Quantity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Purchase Requests (PR) and Job Requests (JR).
 *
 * A request is prepared as a draft against one PAP of the office's approved PPMP ("CHARGE TO").
 * Submitting it gives it its number (YYYY-MM-XXXX, series per kind and year) and charges each
 * line to its PPMP project; cancelling gives the budget back. A revision keeps the number and the
 * signatories and, when submitted, takes the place of the request before it (Rev. 1, 2, ...).
 */
class PurchaseRequestService
{
    public function __construct(
        protected PpmpBudgetService $budget
    ) {}

    public function create(RequestKind $kind, PpmpPap $pap, User $user, array $data = []): PurchaseRequest
    {
        $ppmp = $pap->ppmp;

        if ($ppmp->status !== PpmpStatus::Approved) {
            throw new ProcurementException("PPMP {$ppmp->ppmp_no} is not the approved version, so requests cannot be charged to it.");
        }

        if (! $user->hasRole('Super Admin') && ! in_array((int) $ppmp->office_id, $user->prOfficeIds(), true)) {
            throw new ProcurementException("You cannot prepare requests for {$ppmp->office->shortName()}.");
        }

        return PurchaseRequest::create($this->header($kind, $data) + [
            'kind'        => $kind,
            'fiscal_year' => $ppmp->fiscal_year,
            'office_id'   => $ppmp->office_id,
            'ppmp_id'     => $ppmp->id,
            'ppmp_pap_id' => $pap->id,
            'status'      => RequestStatus::Draft,
            'created_by'  => $user->id,
            'updated_by'  => $user->id,
        ]);
    }

    /** Purpose, JR type and the signatories (set on save or print). SAI is filled in by Property later. */
    public function updateHeader(PurchaseRequest $request, User $user, array $data): PurchaseRequest
    {
        $this->assertEditable($request);
        $request->update($this->header($request->kind, $data) + ['updated_by' => $user->id]);

        return $request;
    }

    /** Signatories only: may be set on a submitted request too, e.g. right before printing. */
    public function updateSignatories(PurchaseRequest $request, User $user, array $data): PurchaseRequest
    {
        if (! in_array($request->status, [RequestStatus::Draft, RequestStatus::Submitted], true)) {
            throw new ProcurementException("{$request->title()} is {$request->status->label()}; its signatories can no longer change.");
        }

        $request->update(array_intersect_key($data, array_flip(self::SIGNATORY_FIELDS)) + ['updated_by' => $user->id]);

        return $request;
    }

    public const SIGNATORY_FIELDS = ['requested_by_name', 'requested_by_designation', 'approved_by_name', 'approved_by_designation'];

    /**
     * PPMP projects of the request's PAP in the office's currently approved PPMP (an amendment
     * may have been approved since the request was started), with what each has left to charge:
     * remaining_cents / budget_cents (budget) and remaining_hundredths / planned_hundredths
     * (quantity; null for a lot). $except is the line being edited: its own share is counted back.
     */
    public function availableLines(PurchaseRequest $request, ?PurchaseRequestItem $except = null): Collection
    {
        $lines = $this->currentPap($request)->items()->with(['unit', 'item', 'procurementMode'])->get();

        return $this->withRemaining($request, $lines, $except);
    }

    /**
     * Hundredths of a unit the request may still put on a project; null = no quantity limit (a lot). May be negative.
     *
     * The planned quantity of the project in the currently approved PPMP, less what submitted requests
     * hold on it and this request's other lines, plus (for a revision) what the request it replaces holds.
     * A hard limit like the budget: saveLine and submit refuse to go over it.
     */
    public function remainingQuantity(PurchaseRequest $request, PpmpItem $project, ?PurchaseRequestItem $except = null): ?int
    {
        $current = $this->budget->currentLine($project);

        return $this->withRemaining($request, collect([$current]), $except)->first()->remaining_hundredths;
    }

    /**
     * Add or edit a line. Amount = quantity x unit cost. The line may take neither more than what is
     * left of the project's planned quantity (a lot has none) nor more than its remaining budget.
     */
    public function saveLine(PurchaseRequest $request, array $data, ?PurchaseRequestItem $line = null): PurchaseRequestItem
    {
        $this->assertEditable($request);

        // Locked like submit(): an edit cannot land after the request was submitted and charged
        return DB::transaction(fn () => $this->writeLine($this->lockEditable($request), $data, $line));
    }

    protected function writeLine(PurchaseRequest $request, array $data, ?PurchaseRequestItem $line): PurchaseRequestItem
    {

        $project = $this->currentPap($request)->items()->with(['unit', 'item'])->find($data['ppmp_item_id'] ?? null);

        if (! $project) {
            throw new ProcurementException("Choose a procurement project under {$request->pap->label()}.");
        }

        // Catalog (standard) items keep the PPMP unit cost and the TWG specifications
        if ($project->item?->isStandard()) {
            $data['unit_cost'] = $project->unit_cost;
            $data['specifications'] = $data['specifications'] ?? $project->item->specifications;
        }

        $quantityHundredths = Quantity::toHundredths($data['quantity']);
        $totalCents = (int) round($quantityHundredths * Money::toCents($data['unit_cost']) / 100);

        if ($quantityHundredths <= 0 || $totalCents <= 0) {
            throw new ProcurementException('Quantity and unit cost must be greater than zero.');
        }

        // $project is already the line of the currently approved PPMP
        $this->withRemaining($request, collect([$project]), $line);
        $quantityLeft = $project->remaining_hundredths;
        $remaining = $project->remaining_cents;

        if ($quantityLeft !== null && $quantityHundredths > $quantityLeft) {
            throw new ProcurementException(sprintf(
                'Over the PPMP quantity of "%s": this line is %s, only %s is left on the project.',
                $project->description, Quantity::text($quantityHundredths, $project->unit?->name),
                Quantity::text(max(0, $quantityLeft), $project->unit?->name),
            ));
        }

        if ($totalCents > $remaining) {
            throw new ProcurementException(sprintf(
                'Over the PPMP budget of "%s": this line is %s, only %s is left on the project.',
                $project->description, Money::format(Money::fromCents($totalCents)), Money::format(Money::fromCents(max(0, $remaining))),
            ));
        }

        return DB::transaction(function () use ($request, $data, $line, $project, $totalCents) {
            $values = [
                'ppmp_item_id'   => $project->id,
                'line_uuid'      => $project->line_uuid,
                'stock_no'       => $data['stock_no'] ?? null,
                'unit'           => $data['unit'] ?? $project->unit?->name,
                'description'    => $data['description'] ?? $project->description,
                'specifications' => $data['specifications'] ?? null,
                'quantity'       => $data['quantity'],
                'unit_cost'      => $data['unit_cost'],
                'total_cost'     => Money::fromCents($totalCents),
                'nature_of_work' => $request->kind === RequestKind::Jr ? ($data['nature_of_work'] ?? null) : null,
            ];

            if ($line) {
                $line->update($values);
            } else {
                $line = $request->items()->create($values + ['sort_order' => (int) $request->items()->max('sort_order') + 1]);
            }

            $request->recalculateTotal();

            return $line;
        });
    }

    public function removeLine(PurchaseRequestItem $line): void
    {
        $this->assertEditable($line->request);

        DB::transaction(function () use ($line) {
            $request = $this->lockEditable($line->request);
            $line->delete();
            $request->recalculateTotal();
        });
    }

    /**
     * Number the request and charge its lines to the PPMP. A revision keeps the number: the
     * request it revises gives its charges back and is marked superseded in the same transaction.
     */
    public function submit(PurchaseRequest $request, User $user, array $data = []): PurchaseRequest
    {
        $this->assertEditable($request);

        return DB::transaction(function () use ($request, $user, $data) {
            $request = PurchaseRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $this->assertEditable($request);

            if ($data) {
                $request->fill(array_intersect_key($data, array_flip(self::SIGNATORY_FIELDS)));
            }

            $this->assertReady($request);

            if ($request->revised_from_id) {
                $previous = PurchaseRequest::whereKey($request->revised_from_id)->lockForUpdate()->firstOrFail();

                if ($previous->status !== RequestStatus::Submitted) {
                    throw new ProcurementException("{$previous->title()} is {$previous->status->label()}, so this revision can no longer replace it.");
                }

                $this->releaseLines($previous);
                $previous->update(['status' => RequestStatus::Superseded, 'updated_by' => $user->id]);
            } else {
                $this->assignNumber($request);
            }

            // Amount and quantity, each checked under the project's row lock: a draft saved within the
            // limits may be over them now that other requests were submitted
            foreach ($request->items()->with('ppmpItem')->get() as $line) {
                $this->budget->charge($line->ppmpItem, $line->total_cost, $line->quantity);
            }

            $request->fill([
                'status'       => RequestStatus::Submitted,
                'submitted_at' => now(),
                'updated_by'   => $user->id,
            ])->save();

            return $request;
        });
    }

    /** Copy a submitted request into a draft revision with the same number and signatories. */
    public function revise(PurchaseRequest $request, User $user): PurchaseRequest
    {
        if ($request->status !== RequestStatus::Submitted) {
            throw new ProcurementException("Only a submitted request can be revised. {$request->title()} is {$request->status->label()}.");
        }

        if ($open = $this->openRevision($request)) {
            throw new ProcurementException("{$open->title()} is already being prepared. Open it instead.");
        }

        return DB::transaction(function () use ($request, $user) {
            $copy = $request->replicate(['uuid', 'status', 'submitted_at', 'cancelled_at', 'cancel_reason', 'created_by', 'updated_by']);
            $copy->fill([
                'revision'        => $request->revision + 1,
                'revised_from_id' => $request->id,
                'status'          => RequestStatus::Draft,
                'created_by'      => $user->id,
                'updated_by'      => $user->id,
            ])->save();

            foreach ($request->items as $line) {
                $copy->items()->create($line->replicate(['purchase_request_id'])->toArray());
            }

            return $copy;
        });
    }

    public function openRevision(PurchaseRequest $request): ?PurchaseRequest
    {
        return PurchaseRequest::where('revised_from_id', $request->id)->where('status', RequestStatus::Draft)->first();
    }

    /** Cancel a submitted request: its charges go back to the PPMP projects. */
    public function cancel(PurchaseRequest $request, User $user, string $reason): PurchaseRequest
    {
        if ($request->status !== RequestStatus::Submitted) {
            throw new ProcurementException("Only a submitted request can be cancelled. {$request->title()} is {$request->status->label()}.");
        }

        if ($open = $this->openRevision($request)) {
            throw new ProcurementException("Delete the draft {$open->title()} first.");
        }

        return DB::transaction(function () use ($request, $user, $reason) {
            $request = PurchaseRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            // Again on the locked row: a cancel or a revision may have gone through meanwhile
            if ($request->status !== RequestStatus::Submitted) {
                throw new ProcurementException("Only a submitted request can be cancelled. {$request->title()} is {$request->status->label()}.");
            }

            if ($open = $this->openRevision($request)) {
                throw new ProcurementException("Delete the draft {$open->title()} first.");
            }

            $this->releaseLines($request);
            $request->update([
                'status'        => RequestStatus::Cancelled,
                'cancelled_at'  => now(),
                'cancel_reason' => $reason,
                'updated_by'    => $user->id,
            ]);

            return $request;
        });
    }

    public function deleteDraft(PurchaseRequest $request): void
    {
        if ($request->status !== RequestStatus::Draft) {
            throw new ProcurementException("Only a draft can be deleted. {$request->title()} is {$request->status->label()}.");
        }

        $request->delete();
    }

    /** "2026-06-1218": submit year and month, series per kind running through the year. */
    protected function assignNumber(PurchaseRequest $request): void
    {
        $now = now();
        $last = PurchaseRequest::where('kind', $request->kind)->where('series_year', $now->year)
            ->lockForUpdate()->max('series');
        $series = (int) $last + 1;

        $request->fill([
            'series_year' => $now->year,
            'series'      => $series,
            'request_no'  => sprintf('%d-%02d-%04d', $now->year, $now->month, $series),
        ]);
    }

    protected function assertReady(PurchaseRequest $request): void
    {
        if (! $request->items()->exists()) {
            throw new ProcurementException('Add at least one item before submitting.');
        }

        if (! trim((string) $request->purpose)) {
            throw new ProcurementException('State the purpose before submitting.');
        }

        if ($request->kind === RequestKind::Jr && ! $request->jr_type) {
            throw new ProcurementException('Choose the JR type before submitting.');
        }

        if (! $request->requested_by_name || ! $request->approved_by_name) {
            throw new ProcurementException('Set the Requested by and Approved by names before submitting.');
        }
    }

    protected function releaseLines(PurchaseRequest $request): void
    {
        foreach ($request->items()->with('ppmpItem')->get() as $line) {
            $this->budget->release($line->ppmpItem, $line->total_cost, $line->quantity);
        }
    }

    /** The PAP with the same code in the office's currently approved PPMP. */
    protected function currentPap(PurchaseRequest $request): PpmpPap
    {
        // Same office, year and region (an office can have an approved LM and an approved VIS PPMP)
        $ppmp = Ppmp::where('office_id', $request->office_id)->where('fiscal_year', $request->fiscal_year)
            ->where('region', $request->ppmp->region)
            ->where('status', PpmpStatus::Approved)->first();
        $pap = $ppmp?->paps()->where('code', $request->pap->code)->first();

        if (! $pap) {
            throw new ProcurementException("{$request->pap->label()} is no longer in the approved PPMP of {$request->office->shortName()}.");
        }

        return $pap;
    }

    /**
     * Sets on each line of the currently approved PPMP what the request may still put on it:
     * remaining_cents / budget_cents and remaining_hundredths / planned_hundredths (null when the
     * line has no quantity limit). What submitted requests hold is on the line itself
     * (committed_amount / committed_quantity). Two queries at most, however many lines.
     */
    protected function withRemaining(PurchaseRequest $request, Collection $lines, ?PurchaseRequestItem $except): Collection
    {
        $uuids = $lines->pluck('line_uuid')->all();
        $columns = ['line_uuid', 'quantity', 'total_cost'];
        $submitted = fn ($q) => $q->where('status', RequestStatus::Submitted);

        // This request's other lines
        $own = $request->items()->whereIn('line_uuid', $uuids)
            ->when($except, fn ($q) => $q->whereKeyNot($except->id))
            ->get($columns)->groupBy('line_uuid');

        // A revision gets back what the request it replaces holds
        $replaced = $request->revised_from_id
            ? PurchaseRequestItem::where('purchase_request_id', $request->revised_from_id)->whereIn('line_uuid', $uuids)
                ->whereHas('request', $submitted)->get($columns)->groupBy('line_uuid')
            : collect();

        // Summed per row, rounded like the amounts are saved
        $hundredths = fn (?Collection $items) => (int) $items?->sum(fn ($i) => Quantity::toHundredths($i->quantity));
        $cents = fn (?Collection $items) => (int) $items?->sum(fn ($i) => Money::toCents($i->total_cost));

        return $lines->each(function (PpmpItem $line) use ($own, $replaced, $hundredths, $cents) {
            $uuid = $line->line_uuid;
            $planned = $line->plannedHundredths();

            $line->setAttribute('budget_cents', Money::toCents($line->estimated_budget));
            $line->setAttribute('remaining_cents', Money::toCents($line->estimated_budget) - Money::toCents($line->committed_amount)
                - $cents($own->get($uuid)) + $cents($replaced->get($uuid)));
            $line->setAttribute('planned_hundredths', $planned);
            $line->setAttribute('remaining_hundredths', $planned === null ? null
                : $planned - $line->committedHundredths() - $hundredths($own->get($uuid)) + $hundredths($replaced->get($uuid)));
        });
    }

    protected function header(RequestKind $kind, array $data): array
    {
        $fields = ['purpose', ...self::SIGNATORY_FIELDS];
        $fields = array_merge($fields, $kind === RequestKind::Jr ? ['jr_type'] : []);

        return array_intersect_key($data, array_flip($fields));
    }

    /** The request's row, locked for this transaction, checked again for edits. */
    protected function lockEditable(PurchaseRequest $request): PurchaseRequest
    {
        $locked = PurchaseRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
        $this->assertEditable($locked);

        return $locked;
    }

    protected function assertEditable(PurchaseRequest $request): void
    {
        if (! $request->isEditable()) {
            throw new ProcurementException("{$request->title()} is {$request->status->label()} and can no longer be changed.");
        }
    }
}
