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

    /** Purpose, JR type, SAI and the signatories (set on save or print). */
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
     * may have been approved since the request was started), with what each has left to charge.
     */
    public function availableLines(PurchaseRequest $request): \Illuminate\Support\Collection
    {
        $pap = $this->currentPap($request);

        return $pap->items()->with(['unit', 'item', 'procurementMode'])->get()
            ->each(function (PpmpItem $line) use ($request) {
                $line->setAttribute('remaining_cents', $this->remainingCents($request, $line));
            });
    }

    /** Add or edit a line. Amount = quantity x unit cost, never more than the project's remaining budget. */
    public function saveLine(PurchaseRequest $request, array $data, ?PurchaseRequestItem $line = null): PurchaseRequestItem
    {
        $this->assertEditable($request);

        $project = $this->currentPap($request)->items()->with(['unit', 'item'])->find($data['ppmp_item_id'] ?? null);

        if (! $project) {
            throw new ProcurementException("Choose a procurement project under {$request->pap->label()}.");
        }

        // Catalog (standard) items keep the PPMP unit cost and the TWG specifications
        if ($project->item?->isStandard()) {
            $data['unit_cost'] = $project->unit_cost;
            $data['specifications'] = $data['specifications'] ?? $project->item->specifications;
        }

        $quantityHundredths = (int) round((float) $data['quantity'] * 100);
        $totalCents = (int) round($quantityHundredths * Money::toCents($data['unit_cost']) / 100);

        if ($quantityHundredths <= 0 || $totalCents <= 0) {
            throw new ProcurementException('Quantity and unit cost must be greater than zero.');
        }

        $remaining = $this->remainingCents($request, $project, $line);

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
        $request = $line->request;
        $this->assertEditable($request);

        DB::transaction(function () use ($line, $request) {
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

            foreach ($request->items()->with('ppmpItem')->get() as $line) {
                $this->budget->charge($line->ppmpItem, $line->total_cost);
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
            $this->budget->release($line->ppmpItem, $line->total_cost);
        }
    }

    /** The PAP with the same code in the office's currently approved PPMP. */
    protected function currentPap(PurchaseRequest $request): PpmpPap
    {
        $ppmp = Ppmp::where('office_id', $request->office_id)->where('fiscal_year', $request->fiscal_year)
            ->where('status', PpmpStatus::Approved)->first();
        $pap = $ppmp?->paps()->where('code', $request->pap->code)->first();

        if (! $pap) {
            throw new ProcurementException("{$request->pap->label()} is no longer in the approved PPMP of {$request->office->shortName()}.");
        }

        return $pap;
    }

    /**
     * Centavos the request may still put on a PPMP project: the project's uncharged budget, less
     * this request's other lines on it, plus (for a revision) what the request it replaces holds.
     */
    protected function remainingCents(PurchaseRequest $request, PpmpItem $project, ?PurchaseRequestItem $except = null): int
    {
        $current = $this->budget->currentLine($project);
        $cents = Money::toCents($current->estimated_budget) - Money::toCents($current->committed_amount);

        $cents -= $request->items()->where('line_uuid', $project->line_uuid)
            ->when($except, fn ($q) => $q->whereKeyNot($except->id))
            ->get()->sum(fn ($i) => Money::toCents($i->total_cost));

        if ($request->revised_from_id) {
            $cents += PurchaseRequestItem::where('purchase_request_id', $request->revised_from_id)
                ->where('line_uuid', $project->line_uuid)
                ->whereHas('request', fn ($q) => $q->where('status', RequestStatus::Submitted))
                ->get()->sum(fn ($i) => Money::toCents($i->total_cost));
        }

        return $cents;
    }

    protected function header(RequestKind $kind, array $data): array
    {
        $fields = ['purpose', ...self::SIGNATORY_FIELDS];
        $fields = array_merge($fields, $kind === RequestKind::Jr ? ['jr_type'] : ['sai_no', 'sai_date']);

        return array_intersect_key($data, array_flip($fields));
    }

    protected function assertEditable(PurchaseRequest $request): void
    {
        if (! $request->isEditable()) {
            throw new ProcurementException("{$request->title()} is {$request->status->label()} and can no longer be changed.");
        }
    }
}
