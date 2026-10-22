{{-- A PDF on the AOQ supplier form: the current file (view / remove) or a file to attach.
     Expects $aoq, $owner (AoqSupplier / AoqQuote or null), $type ('supplier' / 'quote'), $input, $remove (field names). --}}
@if ($owner?->attachment_path)
    <div class="d-flex align-items-center gap-2 js-attached">
        <a href="{{ route('procurement.aoqs.attachment', [$aoq, $type, $owner->id]) }}" target="_blank" class="text-truncate small" style="max-width: 200px;" title="{{ $owner->attachment_name }}">
            <i class="fas fa-file-pdf text-danger me-1"></i>{{ $owner->attachment_name }}
        </a>
        <label class="small text-muted text-nowrap mb-0" title="Remove or replace the file">
            <input type="checkbox" class="form-check-input js-replace" name="{{ $remove }}" value="1"> remove
        </label>
    </div>
    <input type="file" name="{{ $input }}" accept="application/pdf,.pdf" class="form-control form-control-sm mt-1 d-none js-file">
@else
    <input type="file" name="{{ $input }}" accept="application/pdf,.pdf" class="form-control form-control-sm js-file">
@endif
