<?php

namespace App\Services\Procurement;

use App\Core\Models\Setting;
use App\Enums\AppStatus;
use App\Enums\AppType;
use App\Enums\FundGroup;
use App\Enums\PpmpStatus;
use App\Enums\Region;
use App\Exceptions\ProcurementException;
use App\Models\Procurement\AnnualProcurementPlan;
use App\Models\Procurement\AppItem;
use App\Models\Procurement\DivisionPpmp;
use App\Models\Procurement\PpmpItem;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Annual Procurement Plan, per fiscal year and region:
 * the BAC Secretariat turns the projects of the approved Division PPMPs into APP lines
 * (grouping similar ones), submits; the BAC Chair recommends; the HOPE approves.
 */
class AppService
{
    /** Hidden gate (menu "APP Manage") for the BAC Secretariat. */
    public const MANAGE_PERMISSION = 'menu.app-manage';

    /** Signatory roles, set per region in the APP signatories settings. */
    public const ROLES = [
        'prepared'    => 'Prepared by (Head - BAC Secretariat)',
        'recommended' => 'Recommended by (BAC Chairperson)',
        'approved'    => 'Approved by (Head of the Procuring Entity)',
    ];

    public static function signatoryKey(Region $region, string $role): string
    {
        return "app_{$region->value}_{$role}_user_id";
    }

    public function signatory(Region $region, string $role): ?User
    {
        $id = Setting::get(self::signatoryKey($region, $role));

        return $id ? User::find($id) : null;
    }

    public function setSignatory(Region $region, string $role, ?int $userId): void
    {
        Setting::set(self::signatoryKey($region, $role), $userId);
    }

    /** BAC Secretariat of the APP's region (Super Admin: any region). */
    public function canManage(User $user, Region $region): bool
    {
        return $user->hasRole('Super Admin')
            || ($user->canAccessPermission(self::MANAGE_PERMISSION) && ($user->region ?? Region::Lm) === $region);
    }

    public function create(int $fiscalYear, Region $region, User $user, AppType $type = AppType::Final, FundGroup $fund = FundGroup::Regular): AnnualProcurementPlan
    {
        if (AnnualProcurementPlan::where('fiscal_year', $fiscalYear)->where('region', $region)->where('fund_group', $fund)->exists()) {
            throw new ProcurementException("There is already a {$region->label()} {$fund->label()} APP for FY {$fiscalYear}. Open it, or create an updated version.");
        }

        return AnnualProcurementPlan::create([
            'fiscal_year' => $fiscalYear,
            'region'      => $region,
            'fund_group'  => $fund,
            'version'     => 1,
            'type'        => $type === AppType::Updated ? AppType::Final : $type,
            'status'      => AppStatus::Draft,
            'created_by'  => $user->id,
        ]);
    }

    /** Projects of the region's current approved Division PPMPs whose fund source belongs to this APP (Regular / SIDA). */
    public function sourceItems(AnnualProcurementPlan $app): Collection
    {
        return DivisionPpmp::with(['ppmps.items.pap', 'ppmps.items.procurementMode', 'ppmps.items.fundSource', 'ppmps.office'])
            ->where('fiscal_year', $app->fiscal_year)
            ->where('region', $app->region)
            ->where('status', DivisionPpmp::STATUS_APPROVED)
            ->get()
            ->flatMap(fn (DivisionPpmp $division) => $division->ppmps->flatMap(
                fn ($ppmp) => $ppmp->items->each(fn (PpmpItem $item) => $item->setRelation('ppmp', $ppmp))
            ))
            ->filter(fn (PpmpItem $item) => ($item->fundSource->fund_group ?? FundGroup::Regular) === $app->fund_group)
            ->values();
    }

    /** Source projects not yet in any line of this APP. */
    public function pool(AnnualProcurementPlan $app): Collection
    {
        $linked = DB::table('app_item_ppmp_item')->where('annual_procurement_plan_id', $app->id)->pluck('ppmp_item_id')->all();

        return $this->sourceItems($app)->reject(fn (PpmpItem $item) => in_array($item->id, $linked, true))->values();
    }

    /** Make one APP line per PPMP project (all unassigned ones, or the given ids). */
    public function generateLines(AnnualProcurementPlan $app, ?array $ppmpItemIds = null): int
    {
        $this->assertEditable($app);

        $items = $this->pool($app)->when($ppmpItemIds !== null, fn ($pool) => $pool->whereIn('id', $ppmpItemIds));

        DB::transaction(function () use ($app, $items) {
            $order = (int) $app->items()->max('sort_order');

            foreach ($items as $item) {
                $line = $app->items()->create($this->lineFrom($item) + ['sort_order' => ++$order]);
                $this->link($line, [$item->id]);
                $this->refreshLine($line);
            }

            $app->recalculateTotal();
        });

        return $items->count();
    }

    public function updateLine(AppItem $line, array $data): AppItem
    {
        $this->assertEditable($line->plan);
        unset($data['estimated_budget'], $data['annual_procurement_plan_id']);

        if (! empty($data['proc_start']) && ! empty($data['proc_end']) && strtotime($data['proc_end']) < strtotime($data['proc_start'])) {
            throw new ProcurementException('End of procurement activity cannot be before its start.');
        }

        $line->update($data);

        return $line;
    }

    /** Group similar lines into the first one: budgets add up, end-users are listed, timeline widens. */
    public function group(AnnualProcurementPlan $app, array $lineIds): AppItem
    {
        $this->assertEditable($app);
        $lines = $app->items()->whereKey($lineIds)->orderBy('sort_order')->get();

        if ($lines->count() < 2) {
            throw new ProcurementException('Select at least two APP lines to group.');
        }

        return DB::transaction(function () use ($app, $lines) {
            $keep = $lines->first();

            foreach ($lines->slice(1) as $line) {
                DB::table('app_item_ppmp_item')->where('app_item_id', $line->id)->update(['app_item_id' => $keep->id]);
                $line->delete();
            }

            $keep->update([
                'proc_start' => $lines->min('proc_start'),
                'proc_end'   => $lines->max('proc_end'),
            ]);
            $this->refreshLine($keep, updateEndUser: true);
            $app->recalculateTotal();

            return $keep->fresh();
        });
    }

    /** Split a grouped line back into one line per PPMP project. */
    public function ungroup(AppItem $line): void
    {
        $app = $line->plan;
        $this->assertEditable($app);
        $items = $line->ppmpItems()->with(['pap', 'ppmp.office'])->get();

        if ($items->count() < 2) {
            throw new ProcurementException('This line has only one PPMP project.');
        }

        DB::transaction(function () use ($app, $line, $items) {
            foreach ($items->slice(1) as $item) {
                $new = $app->items()->create($this->lineFrom($item) + ['sort_order' => $line->sort_order]);
                DB::table('app_item_ppmp_item')->where('app_item_id', $line->id)->where('ppmp_item_id', $item->id)->update(['app_item_id' => $new->id]);
                $this->refreshLine($new);
            }

            $this->refreshLine($line, updateEndUser: true);
            $app->recalculateTotal();
        });
    }

    /** Remove a line; its PPMP projects go back to the unassigned list. */
    public function removeLine(AppItem $line): void
    {
        $app = $line->plan;
        $this->assertEditable($app);
        $line->delete();
        $app->recalculateTotal();
    }

    /**
     * Point lines at the current version of each PPMP project (after a section's amendment
     * was approved into a new Division PPMP number), drop removed projects and empty lines,
     * and recompute budgets.
     */
    public function syncWithPpmps(AnnualProcurementPlan $app): void
    {
        if ($app->status !== AppStatus::Draft) {
            return;
        }

        $current = $this->sourceItems($app)->keyBy('line_uuid');

        DB::transaction(function () use ($app, $current) {
            foreach ($app->items()->with('ppmpItems')->get() as $line) {
                foreach ($line->ppmpItems as $item) {
                    $now = $current->get($item->line_uuid);

                    if (! $now) {
                        DB::table('app_item_ppmp_item')->where('app_item_id', $line->id)->where('ppmp_item_id', $item->id)->delete();
                    } elseif ($now->id !== $item->id) {
                        DB::table('app_item_ppmp_item')->where('app_item_id', $line->id)->where('ppmp_item_id', $item->id)->update(['ppmp_item_id' => $now->id]);
                    }
                }

                $line->unsetRelation('ppmpItems');
                $line->ppmpItems()->exists() ? $this->refreshLine($line) : $line->delete();
            }

            $app->recalculateTotal();
        });
    }

    public function submit(AnnualProcurementPlan $app, User $user, ?string $remarks = null): AnnualProcurementPlan
    {
        $this->assertStatus($app, AppStatus::Draft);

        if (! $app->items()->exists()) {
            throw new ProcurementException('Add APP lines before submitting.');
        }

        return DB::transaction(function () use ($app, $user, $remarks) {
            $app->update(['status' => AppStatus::Submitted]);
            $app->sign($this->signatory($app->region, 'prepared') ?? $user, 'prepared', $remarks);

            return $app;
        });
    }

    public function recommend(AnnualProcurementPlan $app, User $user, ?string $remarks = null): AnnualProcurementPlan
    {
        $this->assertStatus($app, AppStatus::Submitted);
        $this->assertSignatory($app, 'recommended', $user);

        return DB::transaction(function () use ($app, $user, $remarks) {
            $app->update(['status' => AppStatus::Recommended]);
            $app->sign($user, 'recommended', $remarks);

            return $app;
        });
    }

    public function approve(AnnualProcurementPlan $app, User $user, ?string $remarks = null): AnnualProcurementPlan
    {
        $this->assertStatus($app, AppStatus::Recommended);
        $this->assertSignatory($app, 'approved', $user);

        return DB::transaction(function () use ($app, $user, $remarks) {
            $app->updatedFrom?->update(['status' => AppStatus::Superseded]);
            $app->update(['status' => AppStatus::Approved, 'approved_at' => now()]);
            $app->sign($user, 'approved', $remarks);

            return $app;
        });
    }

    /** BAC Chair (while for recommendation) or HOPE (while for approval) sends it back to the Secretariat. */
    public function returnToSecretariat(AnnualProcurementPlan $app, User $user, string $remarks): AnnualProcurementPlan
    {
        $role = match ($app->status) {
            AppStatus::Submitted   => 'recommended',
            AppStatus::Recommended => 'approved',
            default                => throw new ProcurementException('Only an APP waiting for recommendation or approval can be returned.'),
        };
        $this->assertSignatory($app, $role, $user);

        return DB::transaction(function () use ($app, $user, $remarks) {
            $app->update(['status' => AppStatus::Draft]);
            $app->sign($user, 'returned', $remarks);

            return $app;
        });
    }

    /** Start "UPDATED [Version No. n]" from an approved APP, following the latest PPMPs. */
    public function createUpdatedVersion(AnnualProcurementPlan $app, User $user): AnnualProcurementPlan
    {
        $this->assertStatus($app, AppStatus::Approved);

        $open = AnnualProcurementPlan::where('fiscal_year', $app->fiscal_year)->where('region', $app->region)->where('fund_group', $app->fund_group)
            ->whereIn('status', [AppStatus::Draft, AppStatus::Submitted, AppStatus::Recommended])->exists();

        if ($open) {
            throw new ProcurementException('An updated version of this APP is already in progress.');
        }

        return DB::transaction(function () use ($app, $user) {
            $copy = AnnualProcurementPlan::create([
                'fiscal_year'     => $app->fiscal_year,
                'region'          => $app->region,
                'fund_group'      => $app->fund_group,
                'version'         => (int) AnnualProcurementPlan::where('fiscal_year', $app->fiscal_year)->where('region', $app->region)->where('fund_group', $app->fund_group)->max('version') + 1,
                'type'            => AppType::Updated,
                'status'          => AppStatus::Draft,
                'updated_from_id' => $app->id,
                'total_budget'    => $app->total_budget,
                'created_by'      => $user->id,
            ]);

            foreach ($app->items()->with('ppmpItems')->get() as $line) {
                $new = $copy->items()->create($line->only($line->getFillable()));
                $this->link($new, $line->ppmpItems->pluck('id')->all());
            }

            $this->syncWithPpmps($copy);

            return $copy->fresh();
        });
    }

    /** Line fields prefilled from a PPMP project (the BAC can edit them afterwards). */
    protected function lineFrom(PpmpItem $item): array
    {
        $isCse = $item->procurementMode?->code === 'PS';

        return [
            'pap_code'             => $isCse ? null : $item->pap?->code,
            'pap_title'            => $isCse ? null : $item->pap?->title,
            'is_cse'               => $isCse,
            'project_title'        => $item->description,
            'end_user'             => $item->ppmp->office->name,
            'description'          => $item->description . ' - ' . $item->project_type->label(),
            'procurement_mode_id'  => $item->procurement_mode_id,
            'early_procurement'    => false,
            'bid_criteria'         => $isCse ? 'N/A' : 'LCRB',
            'proc_start'           => $item->proc_start,
            'proc_end'             => $item->proc_end,
            'fund_source_id'       => $item->fund_source_id,
            'procurement_strategy' => 'N/A',
        ];
    }

    protected function link(AppItem $line, array $ppmpItemIds): void
    {
        foreach ($ppmpItemIds as $id) {
            $line->ppmpItems()->attach($id, ['annual_procurement_plan_id' => $line->annual_procurement_plan_id]);
        }
    }

    /** Budget = sum of its PPMP projects; optionally list their offices as the end-user. */
    protected function refreshLine(AppItem $line, bool $updateEndUser = false): void
    {
        $items = $line->ppmpItems()->with('ppmp.office')->get();
        $data = ['estimated_budget' => Money::fromCents($items->sum(fn ($i) => Money::toCents($i->estimated_budget)))];

        if ($updateEndUser) {
            $data['end_user'] = $items->map(fn ($i) => $i->ppmp->office->name)->unique()->join('; ');
        }

        $line->update($data);
    }

    protected function assertEditable(AnnualProcurementPlan $app): void
    {
        if ($app->status !== AppStatus::Draft) {
            throw new ProcurementException("The APP is {$app->status->label()} and can no longer be edited.");
        }
    }

    protected function assertStatus(AnnualProcurementPlan $app, AppStatus $expected): void
    {
        if ($app->status !== $expected) {
            throw new ProcurementException("The APP must be {$expected->label()} (currently {$app->status->label()}).");
        }
    }

    protected function assertSignatory(AnnualProcurementPlan $app, string $role, User $user): void
    {
        $signatory = $this->signatory($app->region, $role);

        if (! $signatory) {
            throw new ProcurementException('No ' . self::ROLES[$role] . " is set for {$app->region->label()}. Set the APP signatories first.");
        }

        if ($signatory->id !== $user->id) {
            throw new ProcurementException("Only {$signatory->fullname} can do this.");
        }
    }
}
