<?php

namespace App\Models\Procurement;

use App\Enums\PpmpType;
use App\Models\Procurement\Concerns\HasSignatories;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The combined PPMP a consolidating office (division) submits to BAC.
 * "PPMP NO. 1" is created when the division head approves the section PPMPs;
 * approving later amendments creates NO. 2, NO. 3, ... and supersedes the previous one.
 */
class DivisionPpmp extends Model
{
    use HasSignatories, LogsActivity;

    public const STATUS_APPROVED = 'approved';
    public const STATUS_SUPERSEDED = 'superseded';

    protected $fillable = [
        'uuid', 'office_id', 'region', 'fiscal_year', 'ppmp_number', 'type', 'status',
        'total_budget', 'remarks', 'approved_at', 'created_by',
        'prepared_by_name', 'prepared_by_designation', 'submitted_by_name', 'submitted_by_designation',
    ];

    /** Prepared by / Submitted by as printed: the names set on it, else the ones signed at approval. */
    public function printSignatories(): array
    {
        $out = [];
        foreach (['prepared', 'submitted'] as $role) {
            $signed = $this->latestSignatory($role);
            $out[$role] = [
                'name'     => $this->{"{$role}_by_name"} ?: $signed?->name_snapshot,
                'position' => $this->{"{$role}_by_name"} ? $this->{"{$role}_by_designation"} : $signed?->designation_snapshot,
                'date'     => null,
            ];
        }

        return $out;
    }

    /** The division head, or anyone assigned to the division office itself. */
    public function signatoriesEditableBy(User $user): bool
    {
        return $user->hasRole('Super Admin') || (int) $this->office->head_user_id === (int) $user->id || (int) $user->office_id === (int) $this->office_id;
    }

    protected function casts(): array
    {
        return [
            'fiscal_year'  => 'integer',
            'ppmp_number'  => 'integer',
            'type'         => PpmpType::class,
            'region'       => \App\Enums\Region::class,
            'total_budget' => 'decimal:2',
            'approved_at'  => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DivisionPpmp $divisionPpmp) {
            $divisionPpmp->uuid ??= (string) Str::uuid();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('ppmp')->logOnly(['status', 'ppmp_number', 'total_budget'])->logOnlyDirty();
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isCurrent(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /** Visible to: BAC/view-all, the consolidating office's head and members, offices combined into it. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->canAccessPermission(Ppmp::VIEW_ALL_PERMISSION)) {
            return $query;
        }

        $officeIds = Ppmp::visibleTo($user)->pluck('office_id')
            ->merge($user->prOfficeIds())
            ->merge(Office::where('head_user_id', $user->id)->pluck('id'))
            ->unique();

        $consolidating = Office::whereKey($officeIds)->with('parent')->get()
            ->map(fn (Office $office) => $office->consolidatingOffice()?->id)
            ->filter()
            ->unique();

        return $query->whereIn('office_id', $consolidating);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    /** The section PPMP versions combined in this number. */
    public function ppmps(): BelongsToMany
    {
        return $this->belongsToMany(Ppmp::class, 'division_ppmp_ppmp');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
