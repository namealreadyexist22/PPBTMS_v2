{{-- Requested by / Approved by: name (searchable, or type any name) + designation. $prefix keeps the print dialog's inputs apart from the details form. --}}
@foreach (['requested_by' => 'Requested by', 'approved_by' => 'Approved by'] as $who => $label)
    <div class="col-md-3">
        <label class="form-label small fw-semibold text-muted mb-1">{{ $label }} — printed name</label>
        @include('procurement.partials.signatory_select', [
            'name' => "{$prefix}{$who}_name", 'value' => $pr->{$who . '_name'}, 'designationField' => "{$prefix}{$who}_designation",
            'people' => $people, 'placeholder' => 'Search or type a name',
        ])
    </div>
    <div class="col-md-3">
        <label class="form-label small fw-semibold text-muted mb-1">Designation</label>
        <input type="text" name="{{ $prefix }}{{ $who }}_designation" class="form-control form-control-sm" value="{{ $pr->{$who . '_designation'} }}" maxlength="255">
    </div>
@endforeach
