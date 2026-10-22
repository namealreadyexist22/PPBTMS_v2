<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\AoqStatus;
use App\Enums\RfqStatus;
use App\Exceptions\ProcurementException;
use App\Http\Controllers\Controller;
use App\Models\Procurement\Aoq;
use App\Models\Procurement\Rfq;
use App\Models\Procurement\Supplier;
use App\Models\User;
use App\Services\Procurement\AoqAttachmentService;
use App\Services\Procurement\AoqService;
use Illuminate\Support\Collection;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** Abstract of Quotations: list, workspace (suppliers and their quotes), award / reopen, print. */
class AoqController extends Controller
{
    public function __construct(
        protected AoqService $service
    ) {}

    /** Issued RFQs still without an abstract, then the abstracts. */
    public function index(Request $request)
    {
        $user = $request->user();
        $status = AoqStatus::tryFrom((string) $request->query('status'));
        $search = trim((string) $request->query('q'));

        $pending = Rfq::visibleTo($user)->with(['procurementMode', 'items.line'])
            ->where('status', RfqStatus::Issued)->whereDoesntHave('aoq')
            ->orderBy('series_year')->orderBy('series')->get();

        $aoqs = Aoq::visibleTo($user)
            ->with(['rfq.procurementMode', 'rfq.items.line', 'suppliers', 'quotes'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->whereHas('rfq', fn ($r) => $r->where('rfq_no', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%"))
                ->orWhereHas('suppliers', fn ($s) => $s->where('name', 'like', "%{$search}%"))))
            ->latest('id')
            ->paginate(25)->withQueryString();

        return view('procurement.aoqs.index', compact('pending', 'aoqs', 'status', 'search'));
    }

    public function start(Request $request, Rfq $rfq)
    {
        if (! Rfq::visibleTo($request->user())->whereKey($rfq->id)->exists()) {
            $this->deny($request, 'This RFQ belongs to another region.');
        }

        try {
            $aoq = $this->service->start($rfq, $request->user());
        } catch (ProcurementException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => 'success', 'message' => 'Abstract started. Add the suppliers\' quotations.', 'url' => route('procurement.aoqs.show', $aoq)]);
    }

    public function show(Request $request, Aoq $aoq)
    {
        $this->authorizeView($request, $aoq);
        $aoq->load(['rfq.procurementMode', 'rfq.items.line.request.office', 'suppliers.quotes', 'creator', 'evaluators', 'evaluator']);

        return view('procurement.aoqs.show', [
            'aoq'         => $aoq,
            'rows'        => $this->service->evaluate($aoq),
            'canEdit'     => $aoq->isEditable(),
            'people'      => $this->people(),
            'twg'         => $aoq->isEditable() ? $this->twgMembers() : collect(),
            'signatories' => $this->service->signatories($aoq),
            'defaults'    => $this->service->defaultSignatories($aoq),
            'lot'         => $aoq->rfq->isPerLot() ? $this->service->lotStanding($aoq) : null,
        ]);
    }

    public function header(Request $request, Aoq $aoq)
    {
        $this->authorizeView($request, $aoq);
        $data = $this->validateJson($request, [
            'opened_at'                => ['nullable', 'date'],
            'remarks'                  => ['nullable', 'string', 'max:2000'],
            'evaluator_id'             => ['nullable', 'integer'],
            'prepared_by_name'         => ['nullable', 'string', 'max:255'],
            'prepared_by_designation'  => ['nullable', 'string', 'max:255'],
            'certified_by_name'        => ['nullable', 'string', 'max:255'],
            'certified_by_designation' => ['nullable', 'string', 'max:255'],
            'bac_chair_name'           => ['nullable', 'string', 'max:255'],
            'bac_vice_chair_name'      => ['nullable', 'string', 'max:255'],
            'bac_members'              => ['nullable', 'array', 'max:10'],
            'bac_members.*'            => ['nullable', 'string', 'max:255'],
            'hope_name'                => ['nullable', 'string', 'max:255'],
        ]);
        return $this->attempt(function () use ($request, $aoq, $data) {
            $this->service->updateHeader($aoq, $request->user(), $data);

            return 'Saved.';
        });
    }

    /** Supplier modal: its details and a unit price per RFQ item (?id= to change one). */
    public function supplierEntry(Request $request, Aoq $aoq)
    {
        $this->authorizeView($request, $aoq);

        if (! $aoq->isEditable()) {
            $this->deny($request, "{$aoq->title()} is {$aoq->status->label()}; reopen it to make changes.");
        }

        $aoq->load(['rfq.items.line']);
        $supplier = $request->filled('id') ? $aoq->suppliers()->with('quotes')->whereKey($request->integer('id'))->firstOrFail() : null;

        return view('procurement.aoqs.extras.supplier_entry', [
            'modalName' => 'AOQ_SUPPLIER_MODAL',
            'aoq'       => $aoq,
            'supplier'  => $supplier,
            // The supplier directory, to pick from
            'known'     => Supplier::where('is_active', true)->orderBy('name')->get(['name', 'address', 'tin', 'contact']),
        ]);
    }

    public function supplierStore(Request $request, Aoq $aoq)
    {
        $this->authorizeView($request, $aoq);
        $pdf = ['nullable', 'file', 'mimes:pdf', 'max:' . AoqAttachmentService::MAX_KB];
        $data = $this->validateJson($request, [
            'id'                    => ['nullable', 'integer'],
            'name'                  => ['required', 'string', 'max:255'],
            'address'               => ['nullable', 'string', 'max:500'],
            'tin'                   => ['nullable', 'string', 'max:50'],
            'contact'               => ['nullable', 'string', 'max:255'],
            'is_responsive'         => ['required', 'boolean'],
            'remarks'               => ['nullable', 'string', 'max:500'],
            'attachment'            => $pdf,
            'remove_attachment'     => ['nullable', 'boolean'],
            'quotes'                => ['nullable', 'array'],
            'quotes.*.unit_price'   => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'quotes.*.brand'        => ['nullable', 'string', 'max:500'],
            'quotes.*.file'         => $pdf,
            'quotes.*.remove_file'  => ['nullable', 'boolean'],
        ], [
            'name.required'        => 'Enter the supplier\'s name.',
            'attachment.mimes'     => 'Attach the quotation as a PDF.',
            'quotes.*.file.mimes'  => 'Attach the brand / specs as a PDF.',
        ], ['quotes.*.unit_price' => 'unit price', 'quotes.*.file' => 'specs PDF', 'attachment' => 'quotation PDF']);

        return $this->attempt(function () use ($request, $aoq, $data) {
            $supplier = $this->service->saveSupplier($aoq, $request->user(), $data, $data['quotes'] ?? [], $data['id'] ?? null);

            return "{$supplier->name} saved.";
        });
    }

    public function supplierDestroy(Request $request, Aoq $aoq)
    {
        $this->authorizeView($request, $aoq);
        $this->validateJson($request, ['id' => ['required', 'integer']]);

        return $this->attempt(function () use ($request, $aoq) {
            $this->service->removeSupplier($aoq, $request->user(), $request->integer('id'));

            return 'Supplier removed.';
        });
    }

    /** To the TWG evaluator chosen under "Evaluated by". */
    public function sendToTwg(Request $request, Aoq $aoq)
    {
        $this->authorizeView($request, $aoq);

        return $this->attempt(function () use ($request, $aoq) {
            $aoq = $this->service->sendToTwg($aoq, $request->user());

            return "{$aoq->title()} sent to {$aoq->evaluated_by_name} for TWG evaluation.";
        });
    }

    /** The AOQ's RFQ, read-only in a modal: the BAC and the assigned TWG. */
    public function rfq(Request $request, Aoq $aoq)
    {
        abort_unless($this->canSee($request->user(), $aoq), 403);
        $rfq = $aoq->rfq->load(['procurementMode', 'items.line.request']);

        return view('procurement.rfqs.extras.details_modal', [
            'rfq'     => $rfq,
            'canOpen' => $request->user()->canAccessPermission(\App\Models\Procurement\Rfq::MANAGE_PERMISSION),
        ]);
    }

    /** The BAC of the AOQ's region, Super Admin, or its TWG evaluator. */
    protected function canSee(User $user, Aoq $aoq): bool
    {
        return ($user->canAccessPermission(Aoq::MANAGE_PERMISSION) && Aoq::visibleTo($user)->whereKey($aoq->id)->exists())
            || $user->hasRole('Super Admin') || $aoq->isEvaluator($user);
    }

    /** A supplier's quotation PDF ($type 'supplier') or an offer's specs PDF ('quote'): the BAC and the assigned TWG. */
    public function attachment(Request $request, Aoq $aoq, string $type, int $id)
    {
        abort_unless($this->canSee($request->user(), $aoq), 403);

        $owner = match ($type) {
            'supplier' => $aoq->suppliers()->whereKey($id)->firstOrFail(),
            'quote'    => $aoq->quotes()->where('aoq_quotes.id', $id)->firstOrFail(),
            default    => abort(404),
        };

        return app(AoqAttachmentService::class)->response($owner);
    }

    public function award(Request $request, Aoq $aoq)
    {
        $this->authorizeView($request, $aoq);
        $data = $this->validateJson($request, ['ties' => ['nullable', 'array'], 'ties.*' => ['nullable', 'integer']]);

        return $this->attempt(function () use ($request, $aoq, $data) {
            $aoq = $this->service->award($aoq, $request->user(), $data['ties'] ?? []);

            return "{$aoq->title()} awarded. Print it for the BAC's signatures.";
        });
    }

    public function reopen(Request $request, Aoq $aoq)
    {
        $this->authorizeView($request, $aoq);

        return $this->attempt(function () use ($request, $aoq) {
            $this->service->reopen($aoq, $request->user());

            return 'Back to draft. Correct the quotations, then send it to the TWG again.';
        });
    }

    public function destroy(Request $request, Aoq $aoq)
    {
        $this->authorizeView($request, $aoq);

        return $this->attempt(function () use ($aoq) {
            $this->service->deleteDraft($aoq);

            return 'Abstract deleted.';
        });
    }

    public function print(Request $request, Aoq $aoq)
    {
        $this->authorizeView($request, $aoq);
        $aoq->load(['rfq.procurementMode', 'rfq.items.line.request.office', 'rfq.items.line.request.pap', 'suppliers.quotes']);

        return view('procurement.aoqs.print', [
            'aoq'         => $aoq,
            'rows'        => $this->service->evaluate($aoq),
            'signatories' => $this->service->signatories($aoq),
            'lot'         => $aoq->rfq->isPerLot() ? $this->service->lotStanding($aoq) : null,
        ]);
    }

    /** Signatory picker: active users. */
    protected function people(): array
    {
        return User::where('is_activated', true)->with('office')->orderBy('fname')->orderBy('lname')->get()->toBase()
            ->map(fn ($u) => ['name' => $u->fullname, 'designation' => $u->designation, 'office' => $u->office?->acronym ?: $u->office?->name])
            ->all();
    }

    /** Active users who may evaluate (TWG members), to assign. */
    protected function twgMembers(): Collection
    {
        return User::where('is_activated', true)->with('office')->orderBy('fname')->orderBy('lname')->get()
            ->filter(fn (User $u) => $u->canAccessPermission(Aoq::EVALUATE_PERMISSION))->values();
    }

    protected function attempt(Closure $action): JsonResponse
    {
        try {
            return response()->json(['status' => 'success', 'message' => $action()]);
        } catch (ProcurementException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    protected function authorizeView(Request $request, Aoq $aoq): void
    {
        if (! Aoq::visibleTo($request->user())->whereKey($aoq->id)->exists()) {
            $this->deny($request, 'This abstract belongs to another region.');
        }
    }

    protected function deny(Request $request, string $message): never
    {
        abort($request->expectsJson() ? response()->json(['status' => 'error', 'message' => $message], 403) : 403, $message);
    }

    protected function validateJson(Request $request, array $rules, array $messages = [], array $attributes = []): array
    {
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json(['status' => 'error', 'errors' => $validator->errors()], 422));
        }

        return $validator->validated();
    }
}
