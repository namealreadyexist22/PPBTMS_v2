<?php

namespace App\Models\Procurement;

use App\Enums\AppStatus;
use App\Enums\AppType;
use App\Enums\Region;
use App\Models\Procurement\Concerns\HasSignatories;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Annual Procurement Plan for one fiscal year and region, prepared by that region's
 * BAC Secretariat from the approved Division PPMPs, recommended by the BAC Chair and
 * approved by the HOPE. An approved APP is changed through an updated version.
 */
class AnnualProcurementPlan extends Model
{
    use HasSignatories, LogsActivity;

    protected $fillable = [
        'uuid', 'fiscal_year', 'region', 'version', 'type', 'status', 'updated_from_id',
        'total_budget', 'remarks', 'approved_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year'  => 'integer',
            'version'      => 'integer',
            'region'       => Region::class,
            'type'         => AppType::class,
            'status'       => AppStatus::class,
            'total_budget' => 'decimal:2',
            'approved_at'  => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AnnualProcurementPlan $app) {
            $app->uuid ??= (string) Str::uuid();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('app')->logOnly(['status', 'type', 'version', 'total_budget'])->logOnlyDirty();
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function title(): string
    {
        return "APP FY {$this->fiscal_year} - {$this->region->label()}" . ($this->version > 1 ? " (Version {$this->version})" : '');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AppItem::class)->orderBy('sort_order');
    }

    public function updatedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'updated_from_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recalculateTotal(): void
    {
        $this->update(['total_budget' => $this->items()->sum('estimated_budget')]);
    }
}
