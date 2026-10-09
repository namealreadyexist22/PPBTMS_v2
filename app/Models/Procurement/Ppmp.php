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
        'uuid', 'ppmp_no', 'fiscal_year', 'office_id', 'region', 'type', 'version', 'amended_from_id',
        'status', 'total_budget', 'remarks', 'submitted_at',
        'prepared_by_name', 'prepared_by_designation', 'submitted_by_name', 'submitted_by_designation', 'approved_at', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year'  => 'integer',
            'version'      => 'integer',
            'type'         => PpmpType::class,
            'region'       => \App\Enums\Region::class,
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

    public const SIGNATORY_FIELDS = ['prepared_by_name', 'prepared_by_designation', 'submitted_by_name', 'submitted_by_designation'];

    /**
     * Prepared by / Submitted by as printed: the names set on the PPMP, else the person who
     * prepared it and the approving head. ['name', 'position', 'date'].
     */
    public function printSignatories(): array
    {
        $prepared = $this->latestSignatory('prepared');
        $head = ($id = $this->office->approverId()) ? User::find($id) : null;

        return [
            'prepared'  => [
                'name'     => $this->prepared_by_name ?: $prepared?->name_snapshot,
                'position' => $this->prepared_by_name ? $this->prepared_by_designation : $prepared?->designation_snapshot,
                'date'     => $prepared?->signed_at,
            ],
            'submitted' => [
                'name'     => $this->submitted_by_name ?: $head?->fullname,
                'position' => $this->submitted_by_name ? $this->submitted_by_designation : $head?->designation,
                'date'     => null,
            ],
        ];
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

    /** The user who approves/returns this PPMP once submitted (nearest head above the office). */
    public function isApprovableBy(User $user): bool
    {
        return $this->status === PpmpStatus::Submitted
            && $this->office->approverId() === (int) $user->id;
    }

    /** Only the home office (or Super Admin) may edit or submit a PPMP. */
    public function isEditableBy(User $user): bool
    {
        return $user->hasRole('Super Admin') || (int) $user->office_id === (int) $this->office_id;
    }

    /**
     * PPMPs a user may see: their home and PR offices' (read-only for PR offices), everything at or
     * below an office they head (to approve or monitor), or all of them with the
     * view-all permission (BAC / consolidator; Super Admin passes too).
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->canAccessPermission(self::VIEW_ALL_PERMISSION)) {
            return $query;
        }

        $headed = Office::where('head_user_id', $user->id)->pluck('id');
        $officeIds = Office::withDescendantIds($headed)->merge($user->prOfficeIds())->unique();

        return $query->whereIn('office_id', $officeIds);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PpmpItem::class)->orderBy('sort_order');
    }

    public function paps(): HasMany
    {
        return $this->hasMany(PpmpPap::class)->orderBy('sort_order')->orderBy('code');
    }

    public function divisionPpmps(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(DivisionPpmp::class, 'division_ppmp_ppmp');
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
