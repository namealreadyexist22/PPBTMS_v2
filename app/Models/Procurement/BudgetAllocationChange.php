<?php

namespace App\Models\Procurement;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetAllocationChange extends Model
{
    protected $fillable = ['budget_allocation_id', 'user_id', 'old_amount', 'new_amount', 'reason'];

    protected function casts(): array
    {
        return ['old_amount' => 'decimal:2', 'new_amount' => 'decimal:2'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
