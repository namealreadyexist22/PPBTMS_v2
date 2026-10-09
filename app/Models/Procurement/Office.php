<?php

namespace App\Models\Procurement;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * An office in the org tree (any depth), e.g.
 * 06000 Deputy Admin > 06010 AFD-LM Manager III > 06020 GAD > 06021 HRRS.
 */
class Office extends Model
{
    use SoftDeletes;

    protected $fillable = ['code', 'acronym', 'name', 'type', 'parent_id', 'head_user_id', 'is_consolidating', 'budget_fund', 'region', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_consolidating' => 'boolean', 'budget_fund' => \App\Enums\FundGroup::class, 'region' => \App\Enums\Region::class];
    }

    /** Short name for lists: the acronym, or the office number if none. */
    public function shortName(): string
    {
        return $this->acronym ?: ($this->code ?: $this->name);
    }

    /**
     * Everyone who can be picked as a signatory: all active users, those of this office and the
     * offices above it (and their heads) first, then the rest by name. Each ['name', 'designation', 'office'].
     */
    public function signatoryPeople(array $extraOfficeIds = []): array
    {
        $chain = $extraOfficeIds;
        $heads = [];
        for ($office = $this; $office; $office = $office->parent) {
            $chain[] = $office->id;
            $heads[] = $office->head_user_id;
        }

        return \App\Models\User::where('is_activated', true)->with('office')->orderBy('fname')->orderBy('lname')
            ->get(['id', 'fullname', 'designation', 'office_id'])
            ->sortBy(fn ($u) => in_array($u->office_id, $chain) || in_array($u->id, $heads) ? 0 : 1)
            ->map(fn ($u) => ['name' => $u->fullname, 'designation' => $u->designation, 'office' => $u->office?->acronym ?: $u->office?->name])
            ->values()->all();
    }

    /** For dropdowns, without the office number: "MIS — MIS SECTION", or just the name. */
    public function displayName(): string
    {
        return $this->acronym ? "{$this->acronym} — {$this->name}" : $this->name;
    }

    /** e.g. "05000 · PPSPD — PPSPD - MANAGER III" */
    public function label(): string
    {
        return implode(' · ', array_filter([$this->code, $this->acronym])) . ' — ' . $this->name;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Offices a user may prepare a PPMP for: their home office only.
     * (Extra offices are for PRs.) Super Admin may pick any active office.
     */
    public function scopeAssignableTo(Builder $query, User $user): Builder
    {
        // Only units with an office number prepare PPMPs (a department with divisions uses its Office of the Manager)
        $query->active()->whereNotNull('code')->orderBy('code');

        return $user->hasRole('Super Admin') ? $query : $query->whereKey($user->office_id);
    }

    /**
     * The office whose Division PPMP this office's PPMP is combined into: the nearest
     * office at or above this one marked "consolidates PPMPs". If none is marked, the
     * nearest office above with a head (a top-level office combines its own).
     */
    public function consolidatingOffice(): ?Office
    {
        for ($office = $this; $office; $office = $office->parent) {
            if ($office->is_consolidating) {
                return $office;
            }
        }

        for ($office = $this->parent; $office; $office = $office->parent) {
            if ($office->head_user_id) {
                return $office;
            }
        }

        return $this->parent_id ? null : $this;
    }

    /** Where the unit sits, e.g. "05012 · PPSPD › PPPD › MIS — MIS SECTION". */
    /** Region of the nearest unit up the tree that has one set; Luzon/Mindanao when none has. */
    public function effectiveRegion(): \App\Enums\Region
    {
        for ($office = $this; $office; $office = $office->parent) {
            if ($office->region) {
                return $office->region;
            }
        }

        return \App\Enums\Region::Lm;
    }

    /**
     * Active units of one region, grouped under their department, top down:
     * [['department' => Office, 'units' => [['office' => Office, 'depth' => int], ...]], ...].
     * A sub-department (e.g. AFD-LM under ODA-AF) is a group of its own; units under no
     * department come last with 'department' => null.
     */
    public static function groupedByDepartment(?\App\Enums\Region $region = null): array
    {
        $all = static::active()->with('parent')->orderBy('code')->orderBy('name')->get();
        $byId = $all->keyBy('id');
        $childrenOf = $all->groupBy('parent_id');
        $regionOf = function (Office $office) use ($byId) {
            for ($o = $office; $o; $o = $o->parent_id ? $byId->get($o->parent_id) : null) {
                if ($o->region) {
                    return $o->region;
                }
            }

            return \App\Enums\Region::Lm;
        };

        $groups = [];
        foreach ($all->where('type', 'department')->sortBy(fn ($d) => $d->code ?? ($childrenOf->get($d->id)?->min('code') ?? $d->acronym)) as $department) {
            if ($region && $regionOf($department) !== $region) {
                continue;
            }

            $units = [['office' => $department, 'depth' => 0]];
            $walk = function ($parentId, $depth) use (&$walk, &$units, $childrenOf) {
                foreach ($childrenOf->get($parentId, collect())->reject->isDepartment() as $child) {
                    $units[] = ['office' => $child, 'depth' => $depth];
                    $walk($child->id, $depth + 1);
                }
            };
            $walk($department->id, 1);
            $groups[] = ['department' => $department, 'units' => $units];
        }

        // Units not under any department (not yet placed in the tree)
        $placed = collect($groups)->flatMap(fn ($g) => collect($g['units'])->pluck('office.id'));
        $loose = $all->reject(fn ($o) => $placed->contains($o->id) || $o->isDepartment() || ($region && $regionOf($o) !== $region));
        if ($loose->isNotEmpty()) {
            $groups[] = ['department' => null, 'units' => $loose->map(fn ($o) => ['office' => $o, 'depth' => 1])->values()->all()];
        }

        return $groups;
    }

    public function pathLabel(): string
    {
        $path = [];
        for ($office = $this; $office; $office = $office->parent) {
            array_unshift($path, $office->shortName());
        }

        return ($this->code ? "{$this->code} · " : '') . implode(' › ', $path) . " — {$this->name}";
    }

    /** Unit types of the organization tree, top down. */
    public const TYPES = ['department' => 'Department', 'division' => 'Division', 'section' => 'Section'];

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst((string) $this->type);
    }

    public function isDepartment(): bool
    {
        return $this->type === 'department';
    }

    /** The department whose budget this unit shares: itself, or the nearest department above it. */
    protected function department(): Attribute
    {
        return Attribute::get(function (): ?Office {
            for ($office = $this; $office; $office = $office->parent) {
                if ($office->isDepartment()) {
                    return $office;
                }
            }

            return null;
        });
    }

    /**
     * Units that share this department's budget: itself and everything below it,
     * except units under another department further down (e.g. AFD-LM under ODA-AF).
     */
    public function departmentOfficeIds(): array
    {
        $childrenOf = static::query()->whereNotNull('parent_id')->get(['id', 'parent_id', 'type'])->groupBy('parent_id');

        $result = [$this->id];
        $queue = [$this->id];

        while ($queue) {
            foreach ($childrenOf->get(array_shift($queue), collect()) as $child) {
                if ($child->type !== 'department') {
                    $result[] = (int) $child->id;
                    $queue[] = $child->id;
                }
            }
        }

        return $result;
    }

    /** Who approves this office's PPMP: the head of its consolidating office. */
    public function approverId(): ?int
    {
        $headId = $this->consolidatingOffice()?->head_user_id;

        return $headId ? (int) $headId : null;
    }

    /** Offices whose PPMPs are combined into this office's Division PPMP (including itself). */
    public function consolidatedOfficeIds(): array
    {
        return static::whereKey(static::withDescendantIds([$this->id]))
            ->with('parent')
            ->get()
            ->filter(fn (Office $office) => $office->consolidatingOffice()?->id === $this->id)
            ->pluck('id')
            ->all();
    }

    /** Ids of the given offices and everything below them, at any depth. */
    public static function withDescendantIds(iterable $ids): Collection
    {
        $childrenOf = static::query()->whereNotNull('parent_id')->pluck('parent_id', 'id')
            ->groupBy(fn ($parentId) => $parentId, preserveKeys: true)
            ->map(fn ($group) => $group->keys());

        $result = collect();
        $queue = collect($ids)->map(fn ($id) => (int) $id);

        while ($queue->isNotEmpty()) {
            $id = $queue->shift();

            if ($result->contains($id)) {
                continue;
            }

            $result->push($id);
            $queue = $queue->merge($childrenOf->get($id, collect()));
        }

        return $result->values();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    /** Users whose home office this is. */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Users given extra access to this office. */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function ppmps(): HasMany
    {
        return $this->hasMany(Ppmp::class);
    }

    public function divisionPpmps(): HasMany
    {
        return $this->hasMany(DivisionPpmp::class);
    }
}
