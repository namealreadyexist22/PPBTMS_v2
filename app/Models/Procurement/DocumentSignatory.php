<?php

namespace App\Models\Procurement;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DocumentSignatory extends Model
{
    protected $fillable = [
        'role', 'user_id', 'name_snapshot', 'designation_snapshot', 'signed_at', 'remarks',
    ];

    protected function casts(): array
    {
        return ['signed_at' => 'datetime'];
    }

    public function signable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
