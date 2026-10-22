<?php

namespace App\Models\Procurement;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One step on a PR / JR's status trail (see RequestTrail). */
class PurchaseRequestEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['purchase_request_id', 'event', 'description', 'remarks', 'url', 'user_id', 'created_at'];

    /** event => [icon, color] */
    public const STYLES = [
        'created'           => ['fas fa-pen', 'secondary'],
        'submitted'         => ['fas fa-paper-plane', 'primary'],
        'revision'          => ['fas fa-code-branch', 'secondary'],
        'superseded'        => ['fas fa-layer-group', 'dark'],
        'cancelled'         => ['fas fa-ban', 'danger'],
        'rfq_added'         => ['fas fa-file-signature', 'secondary'],
        'rfq_removed'       => ['fas fa-minus-circle', 'secondary'],
        'rfq_issued'        => ['fas fa-file-signature', 'primary'],
        'rfq_cancelled'     => ['fas fa-ban', 'danger'],
        'aoq_started'       => ['fas fa-balance-scale', 'secondary'],
        'aoq_deleted'       => ['fas fa-trash-alt', 'secondary'],
        'twg_sent'          => ['fas fa-user-check', 'warning'],
        'twg_returned'      => ['fas fa-undo', 'danger'],
        'twg_evaluated'     => ['fas fa-clipboard-check', 'info'],
        'aoq_awarded'       => ['fas fa-trophy', 'success'],
        'aoq_reopened'      => ['fas fa-undo', 'warning'],
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function icon(): string
    {
        return self::STYLES[$this->event][0] ?? 'fas fa-circle';
    }

    public function color(): string
    {
        return self::STYLES[$this->event][1] ?? 'secondary';
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
