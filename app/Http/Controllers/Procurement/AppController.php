<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\AppStatus;
use App\Enums\AppType;
use App\Enums\Region;
use App\Exceptions\ProcurementException;
use App\Http\Controllers\Controller;
use App\Models\Procurement\AnnualProcurementPlan;
use App\Models\Procurement\AppItem;
use App\Models\Procurement\FundSource;
use App\Models\Procurement\ProcurementMode;
use App\Models\User;
use App\Services\Procurement\AppService;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Annual Procurement Plan screens: list per year and region, the BAC Secretariat's
 * workspace (unassigned PPMP projects -> APP lines, grouping), the Chair / HOPE
 * workflow, signatories and the printable form.
 */
class AppController extends Controller
{
    public function __construct(
        protected AppService $appService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $fiscalYear = (int) ($request->input('fy') ?: now()->year + 1);

        $regions = collect(Region::cases())->map(fn (Region $region) => [
            'region'      => $region,
            'versions'    => AnnualProcurementPlan::where('fiscal_year', $fiscalYear)->where('region', $region)->orderByDesc('version')->get(),
            'canManage'   => $this->appService->canManage($user, $region),
            'signatories' => collect(AppService::ROLES)->map(fn ($label, $role) => $this->appService->signatory($region, $role)),
        ]);

        return view('procurement.app.index', [
            'regions'    => $regions,
            'fiscalYear' => $fiscalYear,
            'years'      => range(now()->year - 1, now()->year + 2),
            'types'      => [AppType::Indicative, AppType::Final],
            'roles'      => AppService::ROLES,
            'users'      => User::where('is_activated', true)->orderBy('fname')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateJson($request, [
            'fiscal_year' => ['required', 'integer', 'between:2000,2100'],
            'region'      => ['required', Rule::enum(Region::class)],
            'type'        => ['required', Rule::in([AppType::Indicative->value, AppType::Final->value])],
        ]);
        $region = Region::from($data['region']);
        $this->authorizeManage($request, $region);

        try {
            $app = $this->appService->create((int) $data['fiscal_year'], $region, $request->user(), AppType::from($data['type']));

            return response()->json(['status' => 'success', 'message' => "{$app->title()} created.", 'url' => route('procurement.app.show', $app)]);
        } catch (ProcurementException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    public function show(Request $request, AnnualProcurementPlan $app)
    {
        $user = $request->user();
        $this->appService->syncWithPpmps($app);   // drafts follow the latest approved PPMPs
        $app->refresh()->load(['items.procurementMode', 'items.fundSource', 'items.ppmpItems.ppmp.office', 'signatories']);

        $canManage = $this->appService->canManage($user, $app->region);
        $isDraft = $app->status === AppStatus::Draft;
        $chair = $this->appService->signatory($app->region, 'recommended');
        $hope = $this->appService->signatory($app->region, 'approved');

        return view('procurement.app.show', [
            'app'          => $app,
            'lines'        => $this->orderedLines($app->items),
            'pool'         => $isDraft ? $this->appService->pool($app) : collect(),
            'canEdit'      => $canManage && $isDraft,
            'canRecommend' => $app->status === AppStatus::Submitted && $chair?->id === $user->id,
            'canApprove'   => $app->status === AppStatus::Recommended && $hope?->id === $user->id,
            'canUpdate'    => $canManage && $app->status === AppStatus::Approved,
            'versions'     => AnnualProcurementPlan::where('fiscal_year', $app->fiscal_year)->where('region', $app->region)->orderBy('version')->get(),
            'chair'        => $chair,
            'hope'         => $hope,
        ]);
    }

    public function generate(Request $request, AnnualProcurementPlan $app)
    {
        $this->authorizeManage($request, $app->region);
        $ids = $request->has('ppmp_item_ids') ? array_map('intval', (array) $request->input('ppmp_item_ids')) : null;

        return $this->attempt(fn () => $this->appService->generateLines($app, $ids) . ' APP line(s) added.');
    }

    public function group(Request $request, AnnualProcurementPlan $app)
    {
        $this->authorizeManage($request, $app->region);
        $data = $this->validateJson($request, ['line_ids' => ['required', 'array', 'min:2']], ['line_ids.min' => 'Select at least two APP lines to group.']);

        return $this->attempt(function () use ($app, $data) {
            $line = $this->appService->group($app, array_map('intval', $data['line_ids']));

            return "Grouped into \"{$line->project_title}\". Edit the line to adjust its title and description.";
        });
    }

    public function ungroup(Request $request, AnnualProcurementPlan $app)
    {
        $this->authorizeManage($request, $app->region);

        return $this->attempt(function () use ($request, $app) {
            $this->appService->ungroup($app->items()->findOrFail($request->integer('id')));

            return 'Line split back into one line per PPMP project.';
        });
    }

    public function lineEntry(Request $request, AnnualProcurementPlan $app)
    {
        $this->authorizeManage($request, $app->region);

        $line = $app->items()->with('ppmpItems.ppmp.office')->findOrFail($request->integer('id'));

        // Active entries, plus the one already on the line even if it was deactivated since
        return view('procurement.app.extras.app_line_entry', [
            'modalName'   => 'APP_LINE_MODAL',
            'app'         => $app,
            'line'        => $line,
            'modes'       => ProcurementMode::where('is_active', true)->orWhereKey($line->procurement_mode_id)->orderBy('name')->get(),
            'fundSources' => FundSource::where('is_active', true)->orWhereKey($line->fund_source_id)->orderBy('name')->get(),
        ]);
    }

    public function lineStore(Request $request, AnnualProcurementPlan $app)
    {
        $this->authorizeManage($request, $app->region);

        foreach (['proc_start', 'proc_end'] as $field) {
            if (preg_match('/^\d{4}-\d{2}$/', (string) $request->input($field))) {
                $request->merge([$field => $request->input($field) . '-01']);
            }
        }
        $request->merge(['is_cse' => $request->boolean('is_cse'), 'early_procurement' => $request->boolean('early_procurement')]);

        $data = $this->validateJson($request, [
            'id'                   => ['required', 'integer'],
            'pap_code'             => ['nullable', 'string', 'max:50'],
            'pap_title'            => ['nullable', 'string', 'max:255'],
            'is_cse'               => ['boolean'],
            'project_title'        => ['required', 'string', 'max:2000'],
            'end_user'             => ['required', 'string', 'max:2000'],
            'description'          => ['required', 'string', 'max:3000'],
            'procurement_mode_id'  => ['required', 'integer', 'exists:procurement_modes,id'],
            'early_procurement'    => ['boolean'],
            'bid_criteria'         => ['nullable', 'string', 'max:255'],
            'proc_start'           => ['required', 'date'],
            'proc_end'             => ['required', 'date', 'after_or_equal:proc_start'],
            'fund_source_id'       => ['required', 'integer', 'exists:fund_sources,id'],
            'procurement_strategy' => ['nullable', 'string', 'max:255'],
            'remarks'              => ['nullable', 'string', 'max:2000'],
        ], [], ['procurement_mode_id' => 'mode of procurement', 'fund_source_id' => 'source of fund', 'proc_end' => 'end of procurement activity']);

        return $this->attempt(function () use ($app, $data) {
            $this->appService->updateLine($app->items()->findOrFail($data['id']), collect($data)->except('id')->all());

            return 'APP line updated.';
        });
    }

    public function lineDestroy(Request $request, AnnualProcurementPlan $app)
    {
        $this->authorizeManage($request, $app->region);

        return $this->attempt(function () use ($request, $app) {
            $this->appService->removeLine($app->items()->findOrFail($request->integer('id')));

            return 'Line removed; its PPMP projects are back in the unassigned list.';
        });
    }

    public function submit(Request $request, AnnualProcurementPlan $app)
    {
        $this->authorizeManage($request, $app->region);

        return $this->attempt(function () use ($request, $app) {
            $this->appService->submit($app, $request->user(), $request->input('remarks'));

            return 'APP submitted to the BAC Chairperson for recommendation.';
        });
    }

    public function recommend(Request $request, AnnualProcurementPlan $app)
    {
        return $this->attempt(function () use ($request, $app) {
            $this->appService->recommend($app, $request->user(), $request->input('remarks'));

            return 'APP recommended to the Head of the Procuring Entity.';
        });
    }

    public function approve(Request $request, AnnualProcurementPlan $app)
    {
        return $this->attempt(function () use ($request, $app) {
            $this->appService->approve($app, $request->user(), $request->input('remarks'));

            return "{$app->title()} approved.";
        });
    }

    public function returnToSecretariat(Request $request, AnnualProcurementPlan $app)
    {
        $data = $this->validateJson($request, ['remarks' => ['required', 'string', 'max:1000']], ['remarks.required' => 'Please state the reason for returning the APP.']);

        return $this->attempt(function () use ($request, $app, $data) {
            $this->appService->returnToSecretariat($app, $request->user(), $data['remarks']);

            return 'APP returned to the BAC Secretariat.';
        });
    }

    public function createUpdated(Request $request, AnnualProcurementPlan $app)
    {
        $this->authorizeManage($request, $app->region);

        try {
            $copy = $this->appService->createUpdatedVersion($app, $request->user());

            return response()->json(['status' => 'success', 'message' => "Updated version {$copy->version} created as a draft.", 'url' => route('procurement.app.show', $copy)]);
        } catch (ProcurementException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    public function signatories(Request $request)
    {
        $data = $this->validateJson($request, [
            'region'      => ['required', Rule::enum(Region::class)],
            'prepared'    => ['nullable', 'integer', 'exists:users,id'],
            'recommended' => ['nullable', 'integer', 'exists:users,id'],
            'approved'    => ['nullable', 'integer', 'exists:users,id'],
        ]);
        $region = Region::from($data['region']);
        $this->authorizeManage($request, $region);

        foreach (array_keys(AppService::ROLES) as $role) {
            $this->appService->setSignatory($region, $role, isset($data[$role]) ? (int) $data[$role] : null);
        }

        activity()->causedBy($request->user())->log("updated {$region->label()} APP signatories");

        return response()->json(['status' => 'success', 'message' => "{$region->label()} APP signatories saved."]);
    }

    public function print(Request $request, AnnualProcurementPlan $app)
    {
        $app->load(['items.procurementMode', 'items.fundSource', 'signatories']);
        $lines = $this->orderedLines($app->items);

        $signer = function (string $role) use ($app) {
            $signed = $app->latestSignatory($role);
            $user = $signed ? null : $this->appService->signatory($app->region, $role);

            return [
                'name'     => $signed?->name_snapshot ?? $user?->fullname,
                'position' => $signed?->designation_snapshot ?? $user?->designation,
                'date'     => $signed?->signed_at,
            ];
        };

        return view('procurement.app.print', [
            'app'         => $app,
            'lines'       => $lines,
            'epaTotal'    => $app->items->where('early_procurement', true)->sum(fn ($l) => (float) $l->estimated_budget),
            'cseTotal'    => $app->items->where('is_cse', true)->sum(fn ($l) => (float) $l->estimated_budget),
            'prepared'    => $signer('prepared'),
            'recommended' => $signer('recommended'),
            'approved'    => $signer('approved'),
            'watermark'   => match ($app->status) {
                AppStatus::Approved   => null,
                AppStatus::Superseded => 'SUPERSEDED',
                default               => 'DRAFT',
            },
        ]);
    }

    /**
     * APP form order: lines without PAP first, then PAP groups (in code order), then the
     * Common-Use Supplies and Equipment (PS-DBM) section. Returns ['main' => [pap => lines], 'cse' => lines].
     */
    protected function orderedLines(Collection $items): array
    {
        $main = $items->where('is_cse', false)
            ->sortBy(fn (AppItem $line) => [$line->pap_code === null ? 0 : 1, (string) $line->pap_code, $line->sort_order])
            ->groupBy(fn (AppItem $line) => $line->pap_code ? "{$line->pap_code}: {$line->pap_title}" : '');

        return ['main' => $main, 'cse' => $items->where('is_cse', true)->sortBy('sort_order')->values()];
    }

    protected function attempt(Closure $action): JsonResponse
    {
        try {
            return response()->json(['status' => 'success', 'message' => $action()]);
        } catch (ProcurementException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    protected function authorizeManage(Request $request, Region $region): void
    {
        if (! $this->appService->canManage($request->user(), $region)) {
            $message = "Only the {$region->label()} BAC Secretariat can do this.";
            abort($request->expectsJson() ? response()->json(['status' => 'error', 'message' => $message], 403) : 403, $message);
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
