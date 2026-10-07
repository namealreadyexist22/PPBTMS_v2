<?php

namespace App\Http\Controllers\Procurement;

use App\DataTables\Procurement\PpmpDataTable;
use App\Enums\PpmpStatus;
use App\Enums\PpmpType;
use App\Enums\ProjectType;
use App\Exceptions\ProcurementException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\StorePpmpItemRequest;
use App\Http\Requests\Procurement\StorePpmpRequest;
use App\Models\Procurement\FundSource;
use App\Models\Procurement\Item;
use App\Models\Procurement\Office;
use App\Models\Procurement\Ppmp;
use App\Models\Procurement\ProcurementMode;
use App\Models\Procurement\Unit;
use App\Models\Procurement\PpmpItem;
use App\Models\Procurement\PpmpItemAttachment;
use App\Services\Procurement\BudgetAllocationService;
use App\Services\Procurement\PpmpAttachmentService;
use Illuminate\Support\Facades\Storage;
use App\Services\Procurement\PpmpService;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PpmpController extends Controller
{
    public function __construct(
        protected PpmpService $ppmpService
    ) {}

    public function index(PpmpDataTable $dataTable)
    {
        return $dataTable->render('procurement.ppmp.index');
    }

    public function entry(Request $request)
    {
        return view('procurement.ppmp.extras.ppmp_entry', [
            'modalName' => 'PPMP_ENTRY_MODAL',
            'offices'   => Office::assignableTo($request->user())->get(),
            'types'     => PpmpType::cases(),
            'years'     => range(now()->year, now()->year + 2),
        ]);
    }

    public function store(StorePpmpRequest $request)
    {
        try {
            $ppmp = $this->ppmpService->create(
                Office::findOrFail($request->office_id),
                (int) $request->fiscal_year,
                $request->user(),
                PpmpType::tryFrom((string) $request->type) ?? PpmpType::Final,
                $request->remarks,
            );

            return response()->json([
                'status'  => 'success',
                'message' => "PPMP {$ppmp->ppmp_no} created.",
                'uuid'    => $ppmp->uuid,
                'url'     => route('procurement.ppmp.show', $ppmp),
            ]);
        } catch (ProcurementException $e) {
            // Business rule (e.g. office already has a PPMP for that year)
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /** PPMP details: header, procurement projects and workflow buttons. */
    public function show(Request $request, Ppmp $ppmp)
    {
        $this->authorizeView($request, $ppmp);

        $user = $request->user();
        $ppmp->load(['office.parent', 'paps.items.procurementMode', 'paps.items.fundSource', 'paps.items.unit', 'paps.items.attachments', 'items', 'signatories', 'divisionPpmps']);

        $hasOpenAmendment = Ppmp::where('amended_from_id', $ppmp->id)
            ->whereIn('status', [PpmpStatus::Draft, PpmpStatus::Submitted, PpmpStatus::Returned])
            ->exists();

        return view('procurement.ppmp.show', [
            'ppmp'       => $ppmp,
            'canEdit'    => $ppmp->status->isEditable() && $ppmp->isEditableBy($user),
            // Approval happens on the Division PPMP page; the head can return a section's PPMP here
            'canReturn'  => $ppmp->isApprovableBy($user),
            'canAmend'   => $ppmp->status === PpmpStatus::Approved && $ppmp->isEditableBy($user) && ! $hasOpenAmendment,
            'canDelete'  => $ppmp->status === PpmpStatus::Draft && $ppmp->isEditableBy($user)
                            && $user->canAccessPermission('menu.ppmp-destroy'),
            'versions'   => Ppmp::where('office_id', $ppmp->office_id)
                            ->where('fiscal_year', $ppmp->fiscal_year)
                            ->orderBy('version')
                            ->get(['uuid', 'ppmp_no', 'version', 'status']),
            'approver'   => ($id = $ppmp->office->approverId()) ? \App\Models\User::find($id) : null,
            'division'   => $ppmp->office->consolidatingOffice(),
            // Budget allocations from this office up, and what is left to plan per fund
            'budgetRows'   => $budgetRows = app(BudgetAllocationService::class)->checkPpmp($ppmp),
            'budgetLimits' => app(BudgetAllocationService::class)->limits($ppmp, $budgetRows),
        ]);
    }

    /** Printable PPMP in the GPPB revised format (browser Print / Save as PDF). */
    public function print(Request $request, Ppmp $ppmp)
    {
        $this->authorizeView($request, $ppmp);

        $ppmp->load(['office', 'paps.items.procurementMode', 'paps.items.fundSource', 'paps.items.unit', 'paps.items.attachments', 'signatories']);
        $approverId = $ppmp->office->approverId();
        $approver = $approverId ? \App\Models\User::find($approverId) : null;
        $prepared = $ppmp->latestSignatory('prepared');

        return view('procurement.ppmp.print', [
            'title'      => "PPMP {$ppmp->ppmp_no} (Section copy)",
            'number'     => null,
            'type'       => $ppmp->type,
            'fiscalYear' => $ppmp->fiscal_year,
            'endUser'    => $ppmp->office->name,
            'paps'       => $ppmp->paps,
            'total'      => $ppmp->total_budget,
            'watermark'  => $ppmp->status === PpmpStatus::Approved ? 'SECTION COPY' : ($ppmp->status === PpmpStatus::Superseded ? 'SUPERSEDED' : 'DRAFT'),
            'prepared'   => ['name' => $prepared?->name_snapshot, 'position' => $prepared?->designation_snapshot, 'date' => $prepared?->signed_at],
            'submitted'  => ['name' => $approver?->fullname, 'position' => $approver?->designation, 'date' => null],
            'footer'     => "Section PPMP {$ppmp->ppmp_no} · {$ppmp->status->label()}",
        ]);
    }

    /** Add / edit procurement project modal. */
    public function itemEntry(Request $request, Ppmp $ppmp)
    {
        $this->authorizeEdit($request, $ppmp);
        $item = $request->filled('id') ? $ppmp->items()->findOrFail($request->id) : null;

        // Active lookups, plus the ones already on the project even if deactivated since
        $activeOr = fn (string $model, ?int $current) => $model::where('is_active', true)
            ->when($current, fn ($q) => $q->orWhereKey($current))->orderBy('name')->get();

        // Standard items: active ones, plus the one already on the project even if deactivated since
        $catalog = Item::with(['unit', 'category'])
            ->where(fn ($q) => $q->where('is_active', true)->when($item?->item_id, fn ($q) => $q->orWhereKey($item->item_id)))
            ->orderBy('name')->get();

        return view('procurement.ppmp.extras.ppmp_item_entry', [
            'modalName'    => 'PPMP_ITEM_MODAL',
            'ppmp'         => $ppmp,
            'item'         => $item,
            'paps'         => $ppmp->paps,
            'selectedPap'  => $request->integer('pap_id') ?: null,
            'projectTypes' => ProjectType::cases(),
            'allotments'   => \App\Enums\AllotmentClass::cases(),
            'modes'        => $activeOr(ProcurementMode::class, $item?->procurement_mode_id),
            // Only the fund the office's department is budgeted under (COB, or SIDA for SIDA departments)
            'fundSources'  => $activeOr(FundSource::class, $item?->fund_source_id)
                ->filter(fn ($fund) => ! ($budgetFund = $ppmp->office->department?->budget_fund)
                    || $fund->fund_group === $budgetFund || $fund->id === $item?->fund_source_id)
                ->values(),
            'units'        => $activeOr(Unit::class, $item?->unit_id),
            'catalog'      => $catalog,
            // For the form script: what a standard item fills in (and locks)
            'standardItems' => $catalog->mapWithKeys(fn (Item $c) => [$c->id => [
                'name'    => $c->name,
                'unit_id' => $c->unit_id,
                'unit'    => $c->unit?->name,
                'cost'    => $c->standard_unit_cost !== null ? (float) $c->standard_unit_cost : null,
                'specs'   => $c->specifications,
                'type'    => $c->project_type?->value,
                'twg'     => trim(($c->twg_reference ?? '') . ($c->twg_approved_at ? ' (' . $c->twg_approved_at->format('M d, Y') . ')' : '')),
            ]]),
            'budgetBase'   => $this->budgetBase($ppmp, $item),
        ]);
    }

    public function itemStore(StorePpmpItemRequest $request, Ppmp $ppmp)
    {
        $this->authorizeEdit($request, $ppmp);
        $data = $request->safe()->except(['id', 'attachments', 'attachment_kinds']);

        return $this->attempt(function () use ($request, $ppmp, $data) {
            $item = $request->filled('id')
                ? $this->ppmpService->updateItem($ppmp->items()->findOrFail($request->id), $data)
                : $this->ppmpService->addItem($ppmp, $data);

            if ($request->hasFile('attachments')) {
                app(PpmpAttachmentService::class)->store($item, $request->file('attachments'), $request->input('attachment_kinds', []), $request->user());
            }

            return $request->filled('id') ? 'Procurement project updated.' : 'Procurement project added.';
        });
    }

    /** Download / view a project's attachment (anyone who can view the PPMP). */
    public function attachmentDownload(Request $request, Ppmp $ppmp, PpmpItemAttachment $attachment)
    {
        $this->authorizeView($request, $ppmp);
        abort_unless($attachment->item->ppmp_id === $ppmp->id, 404);

        $disk = Storage::disk(PpmpAttachmentService::DISK);
        abort_unless($disk->exists($attachment->path), 404, 'The file is missing.');

        // PDFs and images open in the browser; other files download
        return $disk->response($attachment->path, $attachment->original_name, [], str_starts_with((string) $attachment->mime_type, 'image/') || $attachment->mime_type === 'application/pdf' ? 'inline' : 'attachment');
    }

    public function attachmentDestroy(Request $request, Ppmp $ppmp)
    {
        $this->authorizeEdit($request, $ppmp);
        $this->validateJson($request, ['id' => ['required', 'integer']]);

        return $this->attempt(function () use ($request, $ppmp) {
            $attachment = PpmpItemAttachment::whereKey($request->id)->whereHas('item', fn ($q) => $q->where('ppmp_id', $ppmp->id))->firstOrFail();
            app(PpmpAttachmentService::class)->delete($attachment);

            return "Removed \"{$attachment->original_name}\".";
        });
    }

    /** GPPB Market Scoping Checklist of one procurement project, printable. */
    public function marketScopingPrint(Request $request, Ppmp $ppmp, PpmpItem $item)
    {
        $this->authorizeView($request, $ppmp);
        abort_unless($item->ppmp_id === $ppmp->id, 404);

        return $this->marketScopingView($ppmp, collect([$item->load('attachments')]));
    }

    /** Every project's Market Scoping Checklist, one per page, in PAP order. */
    public function marketScopingPrintAll(Request $request, Ppmp $ppmp)
    {
        $this->authorizeView($request, $ppmp);
        $ppmp->load('paps.items.attachments');

        return $this->marketScopingView($ppmp, $ppmp->paps->flatMap->items);
    }

    protected function marketScopingView(Ppmp $ppmp, \Illuminate\Support\Collection $items)
    {
        return view('procurement.ppmp.market_scoping_print', [
            'ppmp'     => $ppmp->load('office'),
            'items'    => $items,
            'prepared' => $ppmp->latestSignatory('prepared'),
            'head'     => ($id = $ppmp->office->approverId()) ? \App\Models\User::find($id) : null,
        ]);
    }

    /** Add / edit PAP modal (code + title). */
    public function papEntry(Request $request, Ppmp $ppmp)
    {
        $this->authorizeEdit($request, $ppmp);
        $pap = $request->filled('id') ? $ppmp->paps()->findOrFail($request->id) : null;

        return view('procurement.ppmp.extras.ppmp_pap_entry', [
            'modalName'     => 'PPMP_PAP_MODAL',
            'ppmp'          => $ppmp,
            'pap'           => $pap,
            'suggestedCode' => $pap ? $pap->code : $this->ppmpService->suggestPapCode($ppmp),
        ]);
    }

    public function papStore(Request $request, Ppmp $ppmp)
    {
        $this->authorizeEdit($request, $ppmp);
        $data = $this->validateJson($request, [
            'id'    => ['nullable', 'integer'],
            'code'  => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:255'],
        ], [], ['code' => 'PAP code', 'title' => 'PAP title']);

        return $this->attempt(function () use ($request, $ppmp, $data) {
            if ($request->filled('id')) {
                $this->ppmpService->updatePap($ppmp->paps()->findOrFail($request->id), $data['code'], $data['title']);

                return 'PAP updated.';
            }

            $this->ppmpService->addPap($ppmp, $data['code'], $data['title']);

            return 'PAP added.';
        });
    }

    public function papDestroy(Request $request, Ppmp $ppmp)
    {
        $this->authorizeEdit($request, $ppmp);
        $this->validateJson($request, ['id' => ['required', 'integer']]);

        return $this->attempt(function () use ($request, $ppmp) {
            $this->ppmpService->removePap($ppmp->paps()->findOrFail($request->id));

            return 'PAP removed.';
        });
    }

    public function itemDestroy(Request $request, Ppmp $ppmp)
    {
        $this->authorizeEdit($request, $ppmp);
        $this->validateJson($request, ['id' => ['required', 'integer']]);

        return $this->attempt(function () use ($request, $ppmp) {
            $this->ppmpService->removeItem($ppmp->items()->findOrFail($request->id));

            return 'Procurement project removed.';
        });
    }

    public function submit(Request $request, Ppmp $ppmp)
    {
        $this->authorizeEdit($request, $ppmp);

        return $this->attempt(function () use ($request, $ppmp) {
            $this->ppmpService->submit($ppmp, $request->user(), $request->input('remarks'));

            return "PPMP {$ppmp->ppmp_no} submitted for approval.";
        });
    }

    public function returnToOffice(Request $request, Ppmp $ppmp)
    {
        $this->authorizeView($request, $ppmp);
        $this->validateJson($request, ['remarks' => ['required', 'string', 'max:1000']], [
            'remarks.required' => 'Please state the reason for returning the PPMP.',
        ]);

        return $this->attempt(function () use ($request, $ppmp) {
            $this->ppmpService->returnToOffice($ppmp, $request->user(), $request->input('remarks'));

            return "PPMP {$ppmp->ppmp_no} returned to the office.";
        });
    }

    public function amend(Request $request, Ppmp $ppmp)
    {
        $this->authorizeEdit($request, $ppmp);

        try {
            $copy = $this->ppmpService->amend($ppmp, $request->user(), null, $request->input('remarks'));

            return response()->json([
                'status'  => 'success',
                'message' => "Amendment {$copy->ppmp_no} created as a draft.",
                'url'     => route('procurement.ppmp.show', $copy),
            ]);
        } catch (ProcurementException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    public function destroy(Request $request)
    {
        $this->validateJson($request, ['id' => ['required', 'string']]);
        $ppmp = Ppmp::where('uuid', $request->id)->firstOrFail();
        $this->authorizeEdit($request, $ppmp);

        return $this->attempt(function () use ($ppmp) {
            $this->ppmpService->deleteDraft($ppmp);

            return "PPMP {$ppmp->ppmp_no} deleted.";
        });
    }

    /** Run a service call; business-rule errors become a 422 message for the user. */
    /**
     * Per fund (COB / SIDA): centavos left for this PPMP before the project being edited,
     * i.e. the tightest allocation minus the rest of the PPMP. No key = no allocation.
     */
    protected function budgetBase(Ppmp $ppmp, ?\App\Models\Procurement\PpmpItem $item): array
    {
        $itemFund = $item?->fundSource?->fund_group?->value;

        return collect(app(BudgetAllocationService::class)->limits($ppmp))
            ->map(fn ($limit, $fund) => [
                'left'   => $limit['available'] - $limit['mine'] + ($fund === $itemFund ? \App\Support\Money::toCents($item->estimated_budget) : 0),
                'office' => $limit['department']->shortName(),
            ])->all();
    }

    protected function attempt(Closure $action): JsonResponse
    {
        try {
            return response()->json(['status' => 'success', 'message' => $action()]);
        } catch (ProcurementException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    protected function authorizeView(Request $request, Ppmp $ppmp): void
    {
        if (! Ppmp::visibleTo($request->user())->whereKey($ppmp->id)->exists()) {
            $this->deny($request, 'You do not have access to this PPMP.');
        }
    }

    /** Only the home office may change a PPMP (other offices only view it, e.g. for PRs). */
    protected function authorizeEdit(Request $request, Ppmp $ppmp): void
    {
        if (! $ppmp->isEditableBy($request->user())) {
            $this->deny($request, 'Only the home office can change this PPMP.');
        }
    }

    /** 403 as JSON for AJAX calls (the app only renders JSON errors for api/*), a normal error page otherwise. */
    protected function deny(Request $request, string $message): never
    {
        abort($request->expectsJson()
            ? response()->json(['status' => 'error', 'message' => $message], 403)
            : 403, $message);
    }

    /** Validate and answer with the same JSON shape as the FormRequests on failure. */
    protected function validateJson(Request $request, array $rules, array $messages = [], array $attributes = []): array
    {
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422));
        }

        return $validator->validated();
    }
}
