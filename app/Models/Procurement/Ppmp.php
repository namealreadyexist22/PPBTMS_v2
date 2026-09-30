<?php

namespace App\Models\Procurement;

use App\Enums\PpmpStatus;
use App\Enums\PpmpType;
use App\Models\Procurement\Concerns\HasSignatories;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Ppmp extends Model
{
    use HasSignatories, LogsActivity, SoftDeletes;

    protected $fillable = [
        'uuid', 'ppmp_no', 'fiscal_year', 'office_id', 'type', 'version', 'amended_from_id',
        'status', 'total_budget', 'remarks', 'submitted_at', 'approved_at', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year'  => 'integer',
            'version'      => 'integer',
            'type'         => PpmpType::class,
            'status'       => PpmpStatus::class,
            'total_budget' => 'decimal:2',
            'submitted_at' => 'datetime',
            'approved_at'  => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Ppmp $ppmp) {
            $ppmp->uuid ??= (string) Str::uuid();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('ppmp')
            ->logOnly(['status', 'type', 'total_budget', 'remarks'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', PpmpStatus::Approved);
    }

    /** Permission (a hidden "PPMP View All" submenu) for BAC / consolidators. */
    public const VIEW_ALL_PERMISSION = 'menu.ppmp-view-all';

    /**
     * PPMPs a user may see: their own office's, the ones they approve as division
     * head, or all of them with the view-all permission (Super Admin passes too).
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->canAccessPermission(self::VIEW_ALL_PERMISSION)) {
            return $query;
        }

        return $query->whereHas('office', fn (Builder $q) => $q
            ->where('id', $user->office_id)
            ->orWhere(fn (Builder $q) => $q->whereNull('parent_id')->where('head_user_id', $user->id))
            ->orWhereHas('parent', fn (Builder $q) => $q->where('head_user_id', $user->id)));
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PpmpItem::class)->orderBy('sort_order');
    }

    public function amendedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'amended_from_id');
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
