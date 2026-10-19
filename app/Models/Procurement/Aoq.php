<?php

namespace App\Models\Procurement;

use App\Enums\AoqStatus;
use App\Enums\Region;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

/**
 * Abstract of Quotations of an issued RFQ: the quotations received, item by item, and the
 * supplier each item is awarded to (its lowest responsive quote). Numbered like its RFQ.
 */
class Aoq extends Model
{
    public const MANAGE_PERMISSION = 'manage aoq';

    protected $fillable = [
        'uuid', 'rfq_id', 'region', 'status', 'opened_at', 'remarks', 'bac_chair_name', 'bac_vice_chair_name',
        'bac_members', 'hope_name', 'awarded_at', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'region'      => Region::class,
            'status'      => AoqStatus::class,
            'opened_at'   => 'datetime',
            'bac_members' => 'array',
            'awarded_at'  => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (Aoq $aoq) => $aoq->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** e.g. "AOQ 2026-0003" (its RFQ's number). */
    public function title(): string
    {
        return 'AOQ ' . ($this->rfq?->rfq_no ?? '');
    }

    public function isEditable(): bool
    {
        return $this->status === AoqStatus::Draft;
    }

    /** AOQs of the user's region (Super Admin: all). */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->hasRole('Super Admin') ? $query : $query->where('region', $user->region ?? Region::Lm);
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(AoqSupplier::class)->orderBy('sort_order')->orderBy('id');
    }

    public function quotes(): HasManyThrough
    {
        return $this->hasManyThrough(AoqQuote::class, AoqSupplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
