<?php

namespace App\Services\Procurement;

use App\Exceptions\ProcurementException;
use App\Models\Procurement\PpmpItem;
use App\Models\Procurement\PpmpItemAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Files attached to PPMP procurement projects, on the private "local" disk.
 * An amendment copies the rows (same file); a file is deleted only when no row uses it,
 * so an approved version keeps its attachments when the amendment removes them.
 */
class PpmpAttachmentService
{
    public const DISK = 'local';

    /** @param  UploadedFile[]  $files */
    public function store(PpmpItem $item, array $files, array $kinds, User $user): void
    {
        $this->assertEditable($item);

        foreach (array_values($files) as $i => $file) {
            $kind = $kinds[$i] ?? 'other';

            $item->attachments()->create([
                'kind'          => array_key_exists($kind, config('market_scoping.attachment_kinds')) ? $kind : 'other',
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'path'          => $file->store("ppmp-attachments/{$item->ppmp->uuid}", self::DISK),
                'mime_type'     => $file->getMimeType(),
                'size'          => $file->getSize(),
                'uploaded_by'   => $user->id,
            ]);
        }
    }

    public function delete(PpmpItemAttachment $attachment): void
    {
        $this->assertEditable($attachment->item);

        $path = $attachment->path;
        $attachment->delete();
        $this->removeFileIfUnused($path);
    }

    /** Copy an item's attachment rows to its copy in an amendment (same files). */
    public function copy(PpmpItem $from, PpmpItem $to): void
    {
        foreach ($from->attachments as $attachment) {
            $to->attachments()->create($attachment->only(['kind', 'original_name', 'path', 'mime_type', 'size', 'uploaded_by']));
        }
    }

    /** Files of items about to be deleted: call with their paths after the rows are gone. */
    public function pathsOf(iterable $items): array
    {
        return PpmpItemAttachment::whereIn('ppmp_item_id', collect($items)->pluck('id'))->pluck('path')->unique()->all();
    }

    public function removeFilesIfUnused(array $paths): void
    {
        foreach ($paths as $path) {
            $this->removeFileIfUnused($path);
        }
    }

    protected function removeFileIfUnused(string $path): void
    {
        if (! PpmpItemAttachment::where('path', $path)->exists()) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    protected function assertEditable(PpmpItem $item): void
    {
        if (! $item->ppmp->status->isEditable()) {
            throw new ProcurementException("Attachments can only be changed while the PPMP is a draft or returned. Amend {$item->ppmp->ppmp_no} to change them.");
        }
    }
}
