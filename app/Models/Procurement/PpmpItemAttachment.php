<?php

namespace App\Models\Procurement;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A file attached to a PPMP procurement project (market survey, specifications, ...). */
class PpmpItemAttachment extends Model
{
    protected $fillable = ['ppmp_item_id', 'kind', 'original_name', 'path', 'mime_type', 'size', 'uploaded_by'];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function kindLabel(): string
    {
        return config("market_scoping.attachment_kinds.{$this->kind}", ucfirst($this->kind));
    }

    /** e.g. "1.2 MB" */
    public function sizeLabel(): string
    {
        return $this->size >= 1048576 ? round($this->size / 1048576, 1) . ' MB' : max(1, round($this->size / 1024)) . ' KB';
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(PpmpItem::class, 'ppmp_item_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
