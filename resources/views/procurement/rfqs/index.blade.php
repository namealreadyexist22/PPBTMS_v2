@extends('BackEnd.layouts.master')

@section('content')
@php use App\Enums\RfqStatus; @endphp

<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm" id="rfqs_card" style="border-radius: 12px; overflow: hidden;">
            <div class="card-header bg-white pt-4 pb-3 px-4" style="border-bottom: 1px solid #f1f5f9;">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <h5 class="m-0 fw-bold text-dark" style="font-size: 1.05rem;">
                        <i class="fas fa-file-signature text-muted me-2"></i>Requests for Quotation
                    </h5>
                    <button id="btn_add" class="btn btn-sm text-white fw-medium shadow-sm px-3" style="background-color: #10b981; border-color: #10b981; border-radius: 6px;">
                        <i class="fas fa-plus me-1"></i>New RFQ
                    </button>
                </div>
            </div>

            <div class="card-body px-4 pb-4 pt-3">
                <form class="d-flex flex-wrap align-items-center gap-2 mb-2" method="GET" id="rfqs_filter">
                    <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search RFQ no., title or PR / JR no." style="width: 280px;">
                    <div class="d-flex align-items-center gap-2 ms-auto">
                        <span class="spinner-border spinner-border-sm text-primary list-spinner" role="status" aria-label="Loading"></span>
                        <select name="status" class="form-select form-select-sm" style="width: 160px;" aria-label="Status">
                            <option value="">All statuses</option>
                            @foreach (RfqStatus::cases() as $s)
                                <option value="{{ $s->value }}" @selected($status === $s)>{{ $s->label() }}</option>
                            @endforeach
                        </select>
                        @include('procurement.partials.year_filter')
                    </div>
                </form>

                {{-- Server-side DataTable: one page at a time; search, filters and paging run in the database --}}
                <div class="position-relative">
                    @include('procurement.partials.list_loading')
                    {{ $dataTable->table(['class' => 'table table-hover align-middle small w-100 mb-0']) }}
                </div>
            </div>
        </div>
    </div>
</div>

{{-- New RFQ --}}
<div class="modal fade" id="RFQ_NEW_MODAL" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-white py-3">
                <h5 class="modal-title fw-bold mb-0">New Request for Quotation</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form_rfq_new" autocomplete="off">
                @csrf
                <div class="modal-body p-4">
                    <div class="alert alert-danger d-none py-2 px-3 small" id="rfq_new_error"></div>
                    <label class="form-label small fw-semibold text-muted mb-1">Title <span class="fw-normal">(optional)</span></label>
                    <input type="text" name="title" class="form-control form-control-sm" maxlength="500" placeholder="e.g. ICT equipment and supplies">
                    <div class="form-text">A name to find it by in the list; not printed. Left blank, the RFQ is named after its items. Items from several submitted PRs / JRs of one procurement mode can go on it.</div>
                    @if ($regions)
                        <label class="form-label small fw-semibold text-muted mb-1 mt-3">Region</label>
                        <select name="region" class="form-select form-select-sm">
                            @foreach ($regions as $region)
                                <option value="{{ $region->value }}" @selected($region === (auth()->user()->region ?? \App\Enums\Region::Lm))>{{ $region->label() }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
                <div class="modal-footer bg-body-tertiary border-0 py-2">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary px-3" id="btn_rfq_new">Start RFQ</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('script')
{{-- Before the table's own script, so the spinner shows for its first load too --}}
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function () {
    // Search, status, year and pages: the table asks the server; the URL follows. A row opens its RFQ.
    serverList({ table: '#tblRfqs', card: '#rfqs_card', form: '#rfqs_filter', url: '{{ route("procurement.rfqs.index") }}' });

    const newModal = document.getElementById('RFQ_NEW_MODAL');
    $('#btn_add').on('click', function () {
        bootstrap.Modal.getOrCreateInstance(newModal).show();
        newModal.addEventListener('shown.bs.modal', () => setTimeout(() => $('#form_rfq_new [name=title]').trigger('focus')), { once: true });
    });
    $('#form_rfq_new').on('submit', function (e) {
        e.preventDefault();
        $('#btn_rfq_new').prop('disabled', true);
        $('#rfq_new_error').addClass('d-none');
        $.ajax({ url: '{{ route("procurement.rfqs.store") }}', type: 'POST', data: $(this).serialize(), headers: { Accept: 'application/json' } })
            .done((res) => { toastr.success(res.message, 'Success'); window.location.href = res.url; })
            .fail(function (xhr) {
                $('#btn_rfq_new').prop('disabled', false);
                const res = xhr.responseJSON || {};
                // Not a validation message: say what failed (e.g. 500 when a migration has not been run)
                $('#rfq_new_error').removeClass('d-none').text(res.errors ? Object.values(res.errors)[0][0]
                    : (res.message && res.message !== 'Server Error' ? res.message : 'Could not start the RFQ (error ' + xhr.status + ' ' + (xhr.statusText || '') + '). Please tell the system administrator.'));
            });
    });
});
</script>
{{ $dataTable->scripts() }}
@endpush
