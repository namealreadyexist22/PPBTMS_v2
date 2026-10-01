<?php

namespace App\Models\Procurement;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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

    protected $fillable = ['code', 'acronym', 'name', 'parent_id', 'head_user_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Short name for lists: the acronym, or the office number if none. */
    public function shortName(): string
    {
        return $this->acronym ?: $this->code;
    }

    /** e.g. "05000 · PPSPD — PPSPD - MANAGER III" */
    public function label(): string
    {
        return $this->code . ($this->acronym ? ' · ' . $this->acronym : '') . ' — ' . $this->name;
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
        $query->active()->orderBy('code');

        return $user->hasRole('Super Admin') ? $query : $query->whereKey($user->office_id);
    }

    /**
     * Who approves this office's PPMP: the head of the nearest office above it
     * that has a head (a section goes to its division head, a division to its
     * department manager). A top-level office approves its own.
     */
    public function approverId(): ?int
    {
        $ancestor = $this->parent;

        while ($ancestor) {
            if ($ancestor->head_user_id) {
                return (int) $ancestor->head_user_id;
            }

            $ancestor = $ancestor->parent;
        }

        return $this->parent_id ? null : ($this->head_user_id ? (int) $this->head_user_id : null);
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
}
