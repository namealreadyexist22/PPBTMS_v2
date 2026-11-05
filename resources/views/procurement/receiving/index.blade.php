@extends('BackEnd.layouts.master')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h5 class="fw-bold mb-0">
        <i class="fas fa-inbox text-muted me-2"></i>PR Receiving
        {{-- How it works: on hover / focus, as on Standard Items --}}
        <a tabindex="0" role="button" class="text-muted ms-1 fs-6 align-middle js-help" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="right" data-bs-html="true"
           title="Receiving PRs / JRs"
           data-bs-content="<ol class='ps-3 mb-0'><li class='mb-1'>The office submits its PR / JR in the system, then hands in the signed hard copy.</li><li class='mb-1'>The BAC receives it here, with the date received. It shows on the request's status trail.</li><li>Only received requests go on an RFQ, or (Competitive Bidding items) into a bidding. One with a problem is returned to the office instead.</li></ol>"><i class="far fa-question-circle"></i></a>
    </h5>
</div>

<div class="card border-0 shadow-sm" id="receiving_card" style="border-radius: 12px; overflow: hidden;">
    <div class="card-header bg-white pt-3 pb-0 px-4" style="border-bottom: 1px solid #f1f5f9;">
        <ul class="nav nav-tabs border-0" id="receiving_tabs">
            <li class="nav-item">
                <a class="nav-link {{ $tab === 'pending' ? 'active fw-semibold' : 'text-muted' }}" data-tab="pending" href="{{ route('procurement.receiving.index') }}">
                    To receive <span class="badge bg-warning text-dark ms-1 js-pending {{ $pending ? '' : 'd-none' }}">{{ $pending }}</span>
                </a>
            </li>
            <li class="nav-item"><a class="nav-link {{ $tab === 'received' ? 'active fw-semibold' : 'text-muted' }}" data-tab="received" href="{{ route('procurement.receiving.index', ['tab' => 'received']) }}">Received</a></li>
        </ul>
    </div>
    <div class="card-body px-4 py-3">
        <form method="GET" class="d-flex flex-wrap align-items-center gap-2 mb-2" id="receiving_filter">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search PR / JR no., purpose or office" style="width: 280px;">
            <div class="d-flex align-items-center gap-2 ms-auto">
                <span class="spinner-border spinner-border-sm text-primary list-spinner" role="status" aria-label="Loading"></span>
                @include('procurement.partials.year_filter')
            </div>
        </form>

        {{-- Server-side DataTable: one page at a time; search, year and paging run in the database --}}
        <div class="position-relative">
            @include('procurement.partials.list_loading')
            {{ $dataTable->table(['class' => 'table table-hover align-middle small w-100 mb-0']) }}
        </div>
    </div>
</div>

{{-- A PR / JR, as printed --}}
<div class="modal fade" id="REQUEST_VIEW_MODAL" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-fullscreen-lg-down">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-white py-3">
                <h5 class="modal-title fw-bold mb-0" id="request_view_title"></h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0 bg-body-tertiary">
                <iframe id="request_view_frame" title="Request" style="width: 100%; height: 75vh; border: 0; display: block;"></iframe>
            </div>
            <div class="modal-footer bg-body-tertiary border-0 py-2">
                <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script')
{{-- Before the table's own script, so the spinner shows for its first load too --}}
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function () {
    const csrf = '{{ csrf_token() }}';
    const today = '{{ now()->format('Y-m-d') }}';
    document.querySelectorAll('.js-help').forEach((el) => bootstrap.Popover.getOrCreateInstance(el, { container: 'body' }));
    const esc = (s) => $('<div>').text(s).html();
    const errorText = (xhr) => {
        const res = xhr.responseJSON || {};
        return res.errors ? Object.values(res.errors)[0][0] : (res.message || 'Something went wrong.');
    };

    // Search, year and pages: the table asks the server; the URL follows (refresh / Back keep it)
    const list = serverList({ table: '#tblPrReceiving', card: '#receiving_card', form: '#receiving_filter', url: '{{ route("procurement.receiving.index") }}' });

    // Each answer carries the count still to receive
    $('#tblPrReceiving').on('xhr.dt', function (e, settings, json) {
        if (json && json.pending !== undefined) $('#receiving_tabs .js-pending').text(json.pending).toggleClass('d-none', !json.pending);
    });

    // Tabs: the same table, to receive or received (the Received column shown for the latter)
    $(document).on('click', '#receiving_tabs .nav-link', function (e) {
        if (e.ctrlKey || e.metaKey || e.shiftKey) return;
        e.preventDefault();
        if ($(this).hasClass('active')) return;
        $('#receiving_tabs .nav-link').removeClass('active fw-semibold').addClass('text-muted');
        $(this).addClass('active fw-semibold').removeClass('text-muted');
        $('#receiving_filter [name=tab]').val(this.dataset.tab);
        list.api().column('received:name').visible(this.dataset.tab === 'received', false);
        list.apply();
    });

    function post(url, data) {
        return $.ajax({ url: url, type: 'POST', data: Object.assign({ _token: csrf }, data), headers: { Accept: 'application/json' } });
    }

    // Receive: the date the hard copy came in (today unless set), and any remarks
    $(document).on('click', '.js-receive', function () {
        const btn = $(this);
        Swal.fire({
            title: 'Receive ' + esc(btn.data('title')) + '?',
            html: '<div class="text-start small">'
                + '<p class="text-muted mb-2">Its items can then go on an RFQ or into a bidding. Receiving shows on its status trail.</p>'
                + '<label class="form-label fw-semibold mb-1" for="swal_received_on">Date received</label>'
                + '<input type="date" id="swal_received_on" class="form-control form-control-sm mb-2" value="' + today + '" min="' + (btn.data('submitted') || '') + '" max="' + today + '">'
                + '<label class="form-label fw-semibold mb-1" for="swal_received_remarks">Remarks <span class="fw-normal">(optional)</span></label>'
                + '<textarea id="swal_received_remarks" class="form-control form-control-sm" rows="2" maxlength="1000"></textarea></div>',
            icon: 'question', showCancelButton: true, confirmButtonText: 'Receive', reverseButtons: true,
            preConfirm: () => {
                const date = $('#swal_received_on').val();
                if (!date) { Swal.showValidationMessage('Set the date received.'); return false; }
                return { date: date, remarks: $('#swal_received_remarks').val().trim() };
            },
        }).then((r) => {
            if (!r.isConfirmed) return;
            post(btn.data('url'), r.value)
                .done((res) => { toastr.success(res.message, 'Received'); list.reload(); })
                .fail((xhr) => toastr.error(errorText(xhr), 'Error'));
        });
    });

    // Return to the office (e.g. an unsigned or incomplete hard copy): for revision or for cancellation, with the reason
    $(document).on('click', '.js-return', function () {
        const btn = $(this);
        Swal.fire({
            title: 'Return ' + esc(btn.data('title')) + ' to the office?',
            html: '<div class="text-start small">'
                + '<p class="text-muted mb-2">A submitted request is locked: returned, the office can revise it (for revision) or cancel it (for cancellation).</p>'
                + '<label class="form-label fw-semibold mb-1" for="swal_return_for">Returned</label>'
                + '<select id="swal_return_for" class="form-select form-select-sm mb-2"><option value="revision">For revision</option><option value="cancellation">For cancellation</option></select>'
                + '<label class="form-label fw-semibold mb-1" for="swal_return_reason">Reason</label>'
                + '<textarea id="swal_return_reason" class="form-control form-control-sm" rows="3" maxlength="1000" placeholder="e.g. The hard copy is not signed"></textarea></div>',
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc3545', confirmButtonText: 'Return', reverseButtons: true,
            preConfirm: () => {
                const reason = $('#swal_return_reason').val().trim();
                if (!reason) { Swal.showValidationMessage('State why it is returned.'); return false; }
                return { return_for: $('#swal_return_for').val(), reason: reason };
            },
        }).then((r) => {
            if (!r.isConfirmed) return;
            post(btn.data('url'), r.value)
                .done((res) => { toastr.success(res.message, 'Returned'); list.reload(); })
                .fail((xhr) => toastr.error(errorText(xhr), 'Error'));
        });
    });

    // View the request as printed
    const viewModal = document.getElementById('REQUEST_VIEW_MODAL');
    $(document).on('click', '.js-view-request', function () {
        $('#request_view_title').text($(this).data('title'));
        $('#request_view_frame').attr('src', $(this).data('url') + '?embed=1');
        bootstrap.Modal.getOrCreateInstance(viewModal).show();
    });
    viewModal.addEventListener('hidden.bs.modal', () => $('#request_view_frame').attr('src', 'about:blank'));
});
</script>
{{ $dataTable->scripts() }}
@endpush
