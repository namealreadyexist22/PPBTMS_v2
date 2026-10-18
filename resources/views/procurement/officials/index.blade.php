@extends('BackEnd.layouts.master')

@section('content')
@php
    $userOption = fn ($u) => $u->fullname . ($u->designation ? ' — ' . $u->designation : '');
@endphp

<div class="mb-3">
    <h5 class="fw-bold mb-1"><i class="fas fa-user-tie text-muted me-2"></i>Signatories &amp; BAC</h5>
    <div class="text-muted small">
        The procurement officials of each region. They act in the system (the BAC Chairperson recommends and the HOPE approves the APP)
        and fill in the signatories of new documents. A document's own signatories can still be changed on it (e.g. Prepared by / Submitted by).
    </div>
</div>

<div class="row g-3">
    @foreach ($regions as $r)
        @php $region = $r['region']; $o = $r['officials']; @endphp
        <div class="col-xl-6">
            <form class="card border-0 shadow-sm h-100 form-officials" style="border-radius: 12px;" autocomplete="off">
                @csrf
                <input type="hidden" name="region" value="{{ $region->value }}">
                <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center" style="border-bottom: 1px solid #f1f5f9;">
                    <div>
                        <div class="small text-muted">{{ $region->bac() }}</div>
                        <h6 class="fw-bold mb-0">{{ $region->label() }}</h6>
                    </div>
                    @unless ($r['canManage'])
                        <span class="badge bg-light text-muted border">View only</span>
                    @endunless
                </div>
                <fieldset class="card-body px-4" @disabled(! $r['canManage'])>
                    @foreach (['hope', 'bac_chair', 'bac_vice_chair'] as $role)
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted mb-1">{{ $roles[$role][0] }}</label>
                            <select name="{{ $role }}" class="form-select form-select-sm js-official" data-placeholder="Not set">
                                <option value=""></option>
                                @foreach ($users as $u)
                                    <option value="{{ $u->id }}" @selected($o[$role]?->id === $u->id)>{{ $userOption($u) }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">{{ $roles[$role][1] }}</div>
                        </div>
                    @endforeach

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted mb-1">BAC Members</label>
                        <select name="bac_members[]" class="form-select form-select-sm js-official" multiple data-placeholder="Add members">
                            @foreach ($o['bac_members'] as $u)
                                <option value="{{ $u->id }}" selected>{{ $userOption($u) }}</option>
                            @endforeach
                            @foreach ($users->whereNotIn('id', $o['bac_members']->pluck('id')) as $u)
                                <option value="{{ $u->id }}">{{ $userOption($u) }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Besides the Chairperson and Vice-Chairperson; they sign the Abstract of Quotations (AOQ) in this order.</div>
                    </div>

                    <hr class="my-3">

                    @foreach (['secretariat_head', 'rfq_signatory'] as $role)
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted mb-1">{{ $roles[$role][0] }}</label>
                            <select name="{{ $role }}" class="form-select form-select-sm js-official" data-placeholder="Not set">
                                <option value=""></option>
                                @foreach ($users as $u)
                                    <option value="{{ $u->id }}" @selected($o[$role]?->id === $u->id)>{{ $userOption($u) }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">{{ $roles[$role][1] }}</div>
                        </div>
                    @endforeach

                    @if ($r['canManage'])
                        <div class="d-flex justify-content-end border-top pt-3">
                            <button type="submit" class="btn btn-sm btn-success px-3"><i class="fas fa-save me-1"></i> Save {{ $region->short() }}</button>
                        </div>
                    @endif
                </fieldset>
            </form>
        </div>
    @endforeach
</div>
@endsection

@push('script')
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function () {
    $('select.js-official').each(function () {
        $(this).select2({
            theme: 'bootstrap-5', width: '100%', allowClear: !this.multiple, placeholder: $(this).data('placeholder'),
            selectionCssClass: 'select2--small', dropdownCssClass: 'select2--small',
        });
    });

    $('.form-officials').on('submit', function (e) {
        e.preventDefault();
        $.ajax({ url: '{{ route("procurement.officials.update") }}', type: 'POST', data: $(this).serialize(), headers: { Accept: 'application/json' } })
            .done((res) => toastr.success(res.message, 'Saved'))
            .fail(function (xhr) {
                const res = xhr.responseJSON || {};
                toastr.error(res.errors ? Object.values(res.errors)[0][0] : (res.message || 'Could not save (error ' + xhr.status + ').'), 'Error');
            });
    });
});
</script>
@endpush
