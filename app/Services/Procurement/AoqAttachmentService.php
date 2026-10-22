<?php

namespace App\Services\Procurement;

use App\Models\Procurement\Aoq;
use App\Models\Procurement\AoqQuote;
use App\Models\Procurement\AoqSupplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * PDFs of the suppliers' quotations on an AOQ, on the private "local" disk: one of the supplier's
 * whole quotation / proposal, and one per item offer (its brand and specs), for the TWG to evaluate.
 */
class AoqAttachmentService
{
    public const DISK = 'local';

    public const MAX_KB = 10240;   // 10 MB per file

    /** Attach (replacing any earlier file) to an AoqSupplier or AoqQuote. */
    public function attach(Aoq $aoq, AoqSupplier|AoqQuote $owner, UploadedFile $file): void
    {
        $old = $owner->attachment_path;

        $owner->update([
            'attachment_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'attachment_path' => $file->store("aoq-attachments/{$aoq->uuid}", self::DISK),
            'attachment_size' => $file->getSize(),
        ]);

        $this->deleteFiles([$old]);
    }

    public function detach(AoqSupplier|AoqQuote $owner): void
    {
        $old = $owner->attachment_path;
        $owner->update(['attachment_name' => null, 'attachment_path' => null, 'attachment_size' => null]);
        $this->deleteFiles([$old]);
    }

    /** Files of a supplier (its own and its quotes'), to delete after its rows are gone. */
    public function pathsOfSupplier(AoqSupplier $supplier): array
    {
        return collect([$supplier->attachment_path])->merge($supplier->quotes()->pluck('attachment_path'))->filter()->all();
    }

    public function pathsOfAoq(Aoq $aoq): array
    {
        return collect($aoq->suppliers()->pluck('attachment_path'))->merge($aoq->quotes()->pluck('aoq_quotes.attachment_path'))->filter()->all();
    }

    public function deleteFiles(array $paths): void
    {
        foreach (array_filter($paths) as $path) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    public function response(Model $owner)
    {
        $disk = Storage::disk(self::DISK);
        abort_unless($owner->attachment_path && $disk->exists($owner->attachment_path), 404, 'The file is missing.');

        return $disk->response($owner->attachment_path, $owner->attachment_name, ['Content-Type' => 'application/pdf'], 'inline');
    }
}
