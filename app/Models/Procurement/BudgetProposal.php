<?php

namespace App\Models\Procurement;

use App\Enums\BudgetProposalStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A department's Budget Proposal for a fiscal year: each of its offices' PAPs (title, and the code the
 * evaluator gives), with their budget lines (MOOE not procured) and performance targets (COB Form A).
 * The PPMPs pick their PAPs from it.
 */
class BudgetProposal extends Model
{
    protected $fillable = ['uuid', 'fiscal_year', 'department_id', 'status', 'remarks', 'submitted_at', 'submitted_by', 'evaluated_at', 'evaluated_by', 'created_by'];

    protected function casts(): array
    {
        return [
            'fiscal_year'  => 'integer',
            'status'       => BudgetProposalStatus::class,
            'submitted_at' => 'datetime',
            'evaluated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (BudgetProposal $p) => $p->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'department_id');
    }

    public function paps(): HasMany
    {
        return $this->hasMany(BudgetPap::class)->orderBy('sort_order')->orderBy('id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }
}
