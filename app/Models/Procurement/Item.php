<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A standard item (article) the agency regularly buys: unit, standard unit cost and the
 * specifications set by the TWG. A PPMP project that uses it takes these as they are.
 */
class Item extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'name', 'description', 'unit_id', 'item_category_id', 'project_type', 'standard_unit_cost',
        'specifications', 'twg_reference', 'twg_approved_at', 'is_active', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active'          => 'boolean',
            'project_type'       => \App\Enums\ProjectType::class,
            'standard_unit_cost' => 'decimal:2',
            'twg_approved_at'    => 'date',
        ];
    }

    /** Has a standard cost, so a project using it is priced and specified by the catalog. */
    /** Code prefix: the category code (e.g. ICT), or ITEM when uncategorized. */
    public static function codePrefix(?ItemCategory $category): string
    {
        return strtoupper($category?->code ?: 'ITEM');
    }

    /** Next code in the prefix's series: ICT-0001, ICT-0002, ... (ignores hand-made codes like ICT-TV-55). */
    public static function nextCode(?ItemCategory $category): string
    {
        $prefix = static::codePrefix($category);
        $last = static::where('code', 'like', $prefix . '-%')->pluck('code')
            ->map(fn ($code) => preg_match('/^' . preg_quote($prefix, '/') . '-(\d+)$/', $code, $m) ? (int) $m[1] : 0)
            ->max() ?? 0;

        return sprintf('%s-%04d', $prefix, $last + 1);
    }

    public function isStandard(): bool
    {
        return $this->standard_unit_cost !== null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'item_category_id');
    }

    public function ppmpItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PpmpItem::class);
    }
}
