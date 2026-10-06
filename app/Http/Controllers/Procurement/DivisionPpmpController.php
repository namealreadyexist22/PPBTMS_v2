<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\PpmpStatus;
use App\Enums\PpmpType;
use App\Enums\Region;
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

        // One card per division and region (LM -> BAC, Visayas -> Regional BAC)
        $divisions = $this->divisionsFor($user, $fiscalYear)->flatMap(function (Office $office) use ($user, $fiscalYear) {
            $regions = Ppmp::whereIn('office_id', $office->consolidatedOfficeIds())->where('fiscal_year', $fiscalYear)
                ->distinct()->pluck('region')
                ->merge(DivisionPpmp::where('office_id', $office->id)->where('fiscal_year', $fiscalYear)->distinct()->pluck('region'))
                ->map(fn ($region) => $region instanceof Region ? $region : Region::from($region))
                ->unique(fn (Region $region) => $region->value);

            if ($regions->isEmpty()) {
                $regions = collect([$user->region ?? Region::Lm]);
            }

            return $regions->sortBy(fn (Region $region) => $region->value)->map(fn (Region $region) => [
                'office'   => $office,
                'region'   => $region,
                'current'  => $this->divisionService->current($office, $fiscalYear, $region),
                'history'  => DivisionPpmp::where('office_id', $office->id)->where('fiscal_year', $fiscalYear)->where('region', $region)->orderByDesc('ppmp_number')->get(),
                'sections' => $this->divisionService->sectionPpmps($office, $fiscalYear, $region),
                // Every unit whose PPMP this head approves, started or not
                'units'    => Office::whereKey($office->consolidatedOfficeIds())->whereNotNull('code')->orderBy('code')->get(),
                'department' => $office->department,
                'pending'  => $this->divisionService->pending($office, $fiscalYear, $region),
                'isHead'   => (int) $office->head_user_id === (int) $user->id,
                'members'  => User::whereIn('office_id', $office->consolidatedOfficeIds())->orWhere('id', $office->head_user_id)->orderBy('fname')->get(),
            ]);
        })
            // Grouped by department (in office-number order), each approver's card under it
            ->sortBy(fn ($d) => [$this->departmentSortKey($d['department']), $d['office']->code ?? '', $d['region']->value])
            ->values();

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
            'history'      => DivisionPpmp::where('office_id', $divisionPpmp->office_id)->where('fiscal_year', $divisionPpmp->fiscal_year)->where('region', $divisionPpmp->region)->orderBy('ppmp_number')->get(),
        ]);
    }

    public function approve(Request $request)
    {
        $data = $this->validateJson($request, [
            'office_id'      => ['required', 'integer', 'exists:offices,id'],
            'fiscal_year'    => ['required', 'integer'],
            'region'         => ['required', Rule::enum(Region::class)],
            'type'           => ['required', Rule::enum(PpmpType::class)],
            'prepared_by_id' => ['required', 'integer', 'exists:users,id'],
            'remarks'        => ['nullable', 'string', 'max:1000'],
        ], [], ['prepared_by_id' => 'prepared by']);

        try {
            $divisionPpmp = $this->divisionService->approve(
                Office::findOrFail($data['office_id']),
                (int) $data['fiscal_year'],
                Region::from($data['region']),
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
            'endUser'    => $this->endUser($divisionPpmp->office, $divisionPpmp->region),
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
        $region = Region::tryFrom((string) $request->input('region')) ?? Region::Lm;
        abort_unless($this->divisionsFor($request->user(), $fiscalYear)->contains('id', $office->id), 403);

        $ppmps = $this->divisionService->preview($office, $fiscalYear, $region);
        $head = $office->head;
        $next = ($this->divisionService->current($office, $fiscalYear, $region)?->ppmp_number ?? 0) + 1;

        return view('procurement.ppmp.print', [
            'title'      => "PPMP No. {$next} (preview) - {$office->name}",
            'number'     => $next,
            'type'       => PpmpType::Final,
            'fiscalYear' => $fiscalYear,
            'endUser'    => $this->endUser($office, $region),
            'paps'       => $this->papsOf($ppmps),
            'total'      => $ppmps->sum(fn (Ppmp $ppmp) => (float) $ppmp->total_budget),
            'watermark'  => 'FOR APPROVAL',
            'prepared'   => ['name' => null, 'position' => null, 'date' => null],
            'submitted'  => ['name' => $head?->fullname, 'position' => $head?->designation, 'date' => null],
            'footer'     => "Preview of PPMP No. {$next} · not yet approved",
        ]);
    }

    /** Office name, marked VISAYAS for a Visayas Division PPMP. */
    protected function endUser(Office $office, Region $region): string
    {
        return $region === Region::Vis ? "{$office->name} - VISAYAS" : $office->name;
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

    /** A department's place in office-number order: its own number, or its first unit's. */
    protected function departmentSortKey(?Office $department): string
    {
        if (! $department) {
            return 'zzzzz';
        }

        return $department->code ?? (Office::whereKey($department->departmentOfficeIds())->whereNotNull('code')->min('code') ?? 'zzzzz');
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
