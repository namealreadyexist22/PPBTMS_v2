{{-- Requested by / Approved by: name + designation. $prefix keeps the print dialog's inputs apart from the details form. --}}
@foreach (['requested_by' => 'Requested by', 'approved_by' => 'Approved by'] as $who => $label)
    <div class="col-md-3">
        <label class="form-label small fw-semibold text-muted mb-1">{{ $label }} — printed name</label>
        <input type="text" name="{{ $prefix }}{{ $who }}_name" class="form-control form-control-sm signatory-name" list="signatory_names"
               data-designation="{{ $prefix }}{{ $who }}_designation" value="{{ $pr->{$who . '_name'} }}" maxlength="255">
    </div>
    <div class="col-md-3">
        <label class="form-label small fw-semibold text-muted mb-1">Designation</label>
        <input type="text" name="{{ $prefix }}{{ $who }}_designation" class="form-control form-control-sm" value="{{ $pr->{$who . '_designation'} }}" maxlength="255">
    </div>
@endforeach
