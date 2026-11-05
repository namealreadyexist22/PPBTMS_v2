<?php

namespace App\Models\Procurement;

use App\Enums\BiddingStep;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One step recorded on a bidding, with what was set then (dates, links, a re-set bidding date and why). */
class BiddingEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['bidding_id', 'step', 'happened_at', 'details', 'remarks', 'user_id', 'created_at'];

    protected function casts(): array
    {
        return [
            'step'        => BiddingStep::class,
            'happened_at' => 'datetime',
            'details'     => 'array',
            'created_at'  => 'datetime',
        ];
    }

    public function bidding(): BelongsTo
    {
        return $this->belongsTo(Bidding::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
