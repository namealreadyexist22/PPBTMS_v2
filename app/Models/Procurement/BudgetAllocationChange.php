<?php

namespace App\Models\Procurement;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetAllocationChange extends Model
{
    protected $fillable = ['budget_allocation_id', 'user_id', 'old_mooe', 'old_co', 'new_mooe', 'new_co', 'reason'];

    protected function casts(): array
    {
        return ['old_mooe' => 'decimal:2', 'old_co' => 'decimal:2', 'new_mooe' => 'decimal:2', 'new_co' => 'decimal:2'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
