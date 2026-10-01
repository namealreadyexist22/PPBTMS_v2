<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\PpmpStatus;
use App\Enums\PpmpType;
use App\Exceptions\ProcurementException;
use App\Http\Controllers\Controller;
use App\Models\Procurement\DivisionPpmp;
use App\Models\Procurement\Office;
use App\Models\Procurement\Ppmp;
use App\Models\Procurement\PpmpPap;
use App\Models\User;
use App\Services\Procurement\DivisionPpmpService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Division PPMP: the section PPMPs under a consolidating office, combined into
 * "PPMP NO. 1, 2, 3..." that the division head approves and submits to BAC.
 */
class DivisionPpmpController extends Controller
{
    public function __construct(
        protected DivisionPpmpService $divisionService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $fiscalYear = (int) ($request->input('fy') ?: now()->year + 1);

        $divisions = $this->divisionsFor($user, $fiscalYear)->map(fn (Office $office) => [
            'office'   => $office,
            'current'  => $this->divisionService->current($office, $fiscalYear),
            'history'  => DivisionPpmp::where('office_id', $office->id)->where('fiscal_year', $fiscalYear)->orderByDesc('ppmp_number')->get(),
            'sections' => $this->divisionService->sectionPpmps($office, $fiscalYear),
            'pending'  => $this->divisionService->pending($office, $fiscalYear),
            'isHead'   => (int) $office->head_user_id === (int) $user->id,
            'members'  => User::whereIn('office_id', $office->consolidatedOfficeIds())->orWhere('id', $office->head_user_id)->orderBy('fname')->get(),
        ]);

        return view('procurement.division_ppmp.index', [
            'divisions'  => $divisions,
            'fiscalYear' => $fiscalYear,
            'years'      => range(now()->year - 1, now()->year + 2),
            'types'      => PpmpType::cases(),
        ]);
    }

    public function show(Request $request, DivisionPpmp $divisionPpmp)
    {
        $this->authorizeView($request, $divisionPpmp);
        $divisionPpmp->load(['office', 'ppmps.office', 'signatories']);

        return view('procurement.division_ppmp.show', [
            'divisionPpmp' => $divisionPpmp,
            'paps'         => $this->papsOf($divisionPpmp->ppmps),
            'history'      => DivisionPpmp::where('office_id', $divisionPpmp->office_id)->where('fiscal_year', $divisionPpmp->fiscal_year)->orderBy('ppmp_number')->get(),
        ]);
    }

    public function approve(Request $request)
    {
        $data = $this->validateJson($request, [
            'office_id'      => ['required', 'integer', 'exists:offices,id'],
            'fiscal_year'    => ['required', 'integer'],
            'type'           => ['required', Rule::enum(PpmpType::class)],
            'prepared_by_id' => ['required', 'integer', 'exists:users,id'],
            'remarks'        => ['nullable', 'string', 'max:1000'],
        ], [], ['prepared_by_id' => 'prepared by']);

        try {
            $divisionPpmp = $this->divisionService->approve(
                Office::findOrFail($data['office_id']),
                (int) $data['fiscal_year'],
                $request->user(),
                User::findOrFail($data['prepared_by_id']),
                PpmpType::from($data['type']),
                $data['remarks'] ?? null,
            );

            return response()->json([
                'status'  => 'success',
                'message' => "PPMP No. {$divisionPpmp->ppmp_number} approved and ready for BAC.",
                'url'     => route('procurement.division-ppmp.show', $divisionPpmp),
            ]);
        } catch (ProcurementException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /** Official print of an approved Division PPMP number. */
    public function print(Request $request, DivisionPpmp $divisionPpmp)
    {
        $this->authorizeView($request, $divisionPpmp);
        $divisionPpmp->load(['office', 'ppmps', 'signatories']);

        $prepared = $divisionPpmp->latestSignatory('prepared');
        $submitted = $divisionPpmp->latestSignatory('submitted');

        return view('procurement.ppmp.print', [
            'title'      => "PPMP No. {$divisionPpmp->ppmp_number} - {$divisionPpmp->office->name}",
            'number'     => $divisionPpmp->ppmp_number,
            'type'       => $divisionPpmp->type,
            'fiscalYear' => $divisionPpmp->fiscal_year,
            'endUser'    => $divisionPpmp->office->name,
            'paps'       => $this->papsOf($divisionPpmp->ppmps),
            'total'      => $divisionPpmp->total_budget,
            'watermark'  => $divisionPpmp->isCurrent() ? null : 'SUPERSEDED',
            'prepared'   => ['name' => $prepared?->name_snapshot, 'position' => $prepared?->designation_snapshot, 'date' => null],
            'submitted'  => ['name' => $submitted?->name_snapshot, 'position' => $submitted?->designation_snapshot, 'date' => null],
            'footer'     => "Division PPMP No. {$divisionPpmp->ppmp_number} · approved " . $divisionPpmp->approved_at?->format('m/d/Y'),
        ]);
    }

    /** What the next PPMP number will look like if the pending section PPMPs are approved. */
    public function preview(Request $request, Office $office)
    {
        $fiscalYear = (int) $request->input('fy');
        abort_unless($this->divisionsFor($request->user(), $fiscalYear)->contains('id', $office->id), 403);

        $ppmps = $this->divisionService->preview($office, $fiscalYear);
        $head = $office->head;
        $next = ($this->divisionService->current($office, $fiscalYear)?->ppmp_number ?? 0) + 1;

        return view('procurement.ppmp.print', [
            'title'      => "PPMP No. {$next} (preview) - {$office->name}",
            'number'     => $next,
            'type'       => PpmpType::Final,
            'fiscalYear' => $fiscalYear,
            'endUser'    => $office->name,
            'paps'       => $this->papsOf($ppmps),
            'total'      => $ppmps->sum(fn (Ppmp $ppmp) => (float) $ppmp->total_budget),
            'watermark'  => 'FOR APPROVAL',
            'prepared'   => ['name' => null, 'position' => null, 'date' => null],
            'submitted'  => ['name' => $head?->fullname, 'position' => $head?->designation, 'date' => null],
            'footer'     => "Preview of PPMP No. {$next} · not yet approved",
        ]);
    }

    /** PAP groups of the given section PPMPs, in section order, with their projects. */
    protected function papsOf(Collection $ppmps): Collection
    {
        return PpmpPap::with(['items.procurementMode', 'items.fundSource', 'items.unit', 'ppmp.office'])
            ->whereIn('ppmp_id', $ppmps->pluck('id'))
            ->get()
            ->sortBy(fn (PpmpPap $pap) => [$pap->ppmp->office->code, $pap->sort_order, $pap->code])
            ->values();
    }

    /** Consolidating offices this user works with for the year (as head, member, section or BAC). */
    protected function divisionsFor(User $user, int $fiscalYear): Collection
    {
        if ($user->canAccessPermission(Ppmp::VIEW_ALL_PERMISSION)) {
            $officeIds = Ppmp::where('fiscal_year', $fiscalYear)->pluck('office_id')
                ->merge(Office::where('is_consolidating', true)->pluck('id'));
        } else {
            $officeIds = collect($user->prOfficeIds())
                ->merge(Office::where('head_user_id', $user->id)->pluck('id'));
        }

        return Office::whereKey($officeIds->unique())->with('parent')->get()
            ->map(fn (Office $office) => $office->consolidatingOffice())
            ->filter()
            ->unique('id')
            ->sortBy('code')
            ->values();
    }

    protected function authorizeView(Request $request, DivisionPpmp $divisionPpmp): void
    {
        if (! DivisionPpmp::visibleTo($request->user())->whereKey($divisionPpmp->id)->exists()) {
            abort(403, 'You do not have access to this PPMP.');
        }
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
