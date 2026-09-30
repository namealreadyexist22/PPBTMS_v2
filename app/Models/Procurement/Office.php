<?php

namespace App\Models\Procurement;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Office extends Model
{
    use SoftDeletes;

    protected $fillable = ['code', 'name', 'division', 'section', 'parent_id', 'head_user_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
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

    /**
     * Who approves this office's PPMP: a section's PPMP goes to its division
     * head (the parent office's head); a division without a parent approves its own.
     */
    public function approverId(): ?int
    {
        $headId = $this->parent_id ? $this->parent?->head_user_id : $this->head_user_id;

        return $headId ? (int) $headId : null;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function ppmps(): HasMany
    {
        return $this->hasMany(Ppmp::class);
    }
}
