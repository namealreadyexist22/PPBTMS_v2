@extends('BackEnd.layouts.master')

@section('content')
@php
    use App\Enums\ItemRequestStatus;
    $tabUrl = fn ($t) => route('procurement.item-requests.index', array_filter(['tab' => $t === 'ours' ? null : $t, 'status' => $status?->value]));
@endphp

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h5 class="fw-bold mb-0">
            <i class="fas fa-hand-holding text-muted me-2"></i>Item Requests
            {{-- How it works: on hover / focus, as on the PPMP's budget --}}
            <a tabindex="0" role="button" class="text-muted ms-1 fs-6 align-middle js-help" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="right" data-bs-html="true"
               title="How item requests work"
               data-bs-content="<ol class='ps-3 mb-0'><li class='mb-1'>Ask the office that procures an item (e.g. MIS for ICT equipment) for what your office needs.</li><li class='mb-1'>It approves all, part or none of it, with its reason.</li><li>What it approves goes to your share in its PPMP project's distribution.</li></ol>"><i class="far fa-question-circle"></i></a>
        </h5>
    </div>
    <button class="btn btn-sm btn-success" id="btn_new_request"><i class="fas fa-plus me-1"></i> New Request</button>
</div>

<div class="card border-0 shadow-sm" id="ir_card" style="border-radius: 12px; overflow: hidden;">
    {{-- The tabs switch the table between our requests and the requests to us (the pending count follows) --}}
    <div class="card-header bg-white pt-3 pb-0 px-4" style="border-bottom: 1px solid #f1f5f9;">
        <ul class="nav nav-tabs border-0" id="ir_tabs">
            <li class="nav-item"><a class="nav-link {{ $tab === 'ours' ? 'active fw-semibold' : 'text-muted' }}" data-tab="ours" data-office-title="To" href="{{ $tabUrl('ours') }}">Our requests</a></li>
            @if ($procuring->isNotEmpty())
                <li class="nav-item">
                    <a class="nav-link {{ $tab === 'incoming' ? 'active fw-semibold' : 'text-muted' }}" data-tab="incoming" data-office-title="Department" href="{{ $tabUrl('incoming') }}">
                        Requests to us <span class="badge bg-warning text-dark ms-1 js-pending {{ $pending ? '' : 'd-none' }}">{{ $pending }}</span>
                    </a>
                </li>
            @endif
        </ul>
    </div>
    <div class="card-body px-4 py-3">
        <form method="GET" class="d-flex flex-wrap align-items-center gap-2 mb-3" id="ir_filter">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search request no. or item" style="width: 260px;">
            <div class="d-flex align-items-center gap-2 ms-auto">
                <span class="spinner-border spinner-border-sm text-primary list-spinner" role="status" aria-label="Loading"></span>
                <select name="status" class="form-select form-select-sm" style="width: 200px;" aria-label="Status">
                    <option value="">All statuses</option>
                    @foreach (ItemRequestStatus::cases() as $s)<option value="{{ $s->value }}" @selected($status === $s)>{{ $s->label() }}</option>@endforeach
                </select>
            </div>
        </form>

        {{-- Requests to us: per item, what offices are waiting for and what was approved (refreshed with the table) --}}
        <div id="ir_totals" class="{{ $tab === 'incoming' ? '' : 'd-none' }}">@include('procurement.item_requests.extras.totals')</div>

        {{-- Server-side DataTable: one page at a time; search, status and paging run in the database --}}
        <div class="position-relative">
            @include('procurement.partials.list_loading')
            {{ $dataTable->table(['class' => 'table table-sm table-hover align-middle small w-100 mb-0']) }}
        </div>
    </div>
</div>
<div id="modal-body"></div>
@endsection

@push('script')
{{-- Before the table's own script, so the spinner shows for its first load too --}}
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function () {
    const csrf = '{{ csrf_token() }}';
    let isModalOpen = false;
    document.querySelectorAll('.js-help').forEach((el) => bootstrap.Popover.getOrCreateInstance(el, { container: 'body' }));
    const errorText = (xhr) => {
        const res = xhr.responseJSON || {};
        return res.errors ? Object.values(res.errors)[0][0] : (res.message || 'Something went wrong.');
    };

    function openModal(url, id, focus) {
        if (isModalOpen) return;
        isModalOpen = true;
        $.get(url).done(function (html) {
            $('#modal-body').html(html);
            const el = document.getElementById(id);
            new bootstrap.Modal(el).show();
            if (focus) el.addEventListener('shown.bs.modal', () => $(el).find(focus).trigger('focus'));
            el.addEventListener('hidden.bs.modal', function () { isModalOpen = false; $('#modal-body').html(''); });
        }).fail(function (xhr) { isModalOpen = false; toastr.error(errorText(xhr), 'Error'); });
    }

    // Search, status and pages: the table asks the server; the URL follows (refresh / Back keep it)
    const list = serverList({ table: '#tblItemRequests', card: '#ir_card', form: '#ir_filter', url: '{{ route("procurement.item-requests.index") }}' });
    const reload = () => list.reload();

    // Each answer carries the pending count for the tab, and (requests to us) the per-item totals
    $('#tblItemRequests').on('xhr.dt', function (e, settings, json) {
        if (!json) return;
        if (json.pending !== undefined) $('#ir_tabs .js-pending').text(json.pending).toggleClass('d-none', !json.pending);
        if (json.totals !== undefined) $('#ir_totals').html(json.totals);
    });

    // Tabs: the same table for our requests or the requests to us (Requestor shown and the office column named for it)
    $(document).on('click', '#ir_tabs .nav-link', function (e) {
        if (e.ctrlKey || e.metaKey || e.shiftKey) return;
        e.preventDefault();
        if ($(this).hasClass('active')) return;
        const incoming = this.dataset.tab === 'incoming';
        $('#ir_tabs .nav-link').removeClass('active fw-semibold').addClass('text-muted');
        $(this).addClass('active fw-semibold').removeClass('text-muted');
        $('#ir_filter [name=tab]').val(this.dataset.tab);
        $('#ir_totals').toggleClass('d-none', !incoming);
        list.api().column('requestor:name').visible(incoming, false);
        $(list.api().column(3).header()).find('.dt-column-title').text(this.dataset.officeTitle);
        list.apply();
    });

    $('#btn_new_request').on('click', () => openModal('{{ route("procurement.item-requests.entry") }}', 'ITEM_REQUEST_MODAL', '[name=quantity]'));
    $(document).on('click', '.js-decide', function () { openModal($(this).data('url'), 'ITEM_DECIDE_MODAL', '[name=approved_quantity]'); });
    $(document).on('click', '.js-ir-details', function (e) { e.preventDefault(); openModal($(this).data('url'), 'ITEM_DETAILS_MODAL'); });

    // From the details: Decide / Cancel close it first, then go on as from the list
    $(document).on('click', '#ITEM_DETAILS_MODAL .js-then', function () {
        const btn = $(this);
        const el = document.getElementById('ITEM_DETAILS_MODAL');
        el.addEventListener('hidden.bs.modal', () => $('<button>').addClass(btn.data('then')).attr('data-url', btn.data('url')).attr('data-no', btn.data('no')).appendTo('body').trigger('click').remove(), { once: true });
        bootstrap.Modal.getInstance(el).hide();
    });

    // Both modals post their form to its data-action
    $(document).on('submit', '.js-ir-form', function (e) {
        e.preventDefault();
        const form = $(this);
        $.ajax({ url: form.data('action'), type: 'POST', data: form.serialize() + '&_token=' + csrf, headers: { Accept: 'application/json' } })
            .done((res) => {
                toastr.success(res.message, 'Saved');
                bootstrap.Modal.getInstance(form.closest('.modal')[0])?.hide();
                reload();
            })
            .fail((xhr) => toastr.error(errorText(xhr), 'Error'));
    });

    $(document).on('click', '.js-cancel', function () {
        const url = $(this).data('url');
        Swal.fire({ title: 'Cancel ' + $(this).data('no') + '?', text: 'The procuring office will no longer see it as pending.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Cancel request', cancelButtonText: 'Keep', reverseButtons: true })
            .then((r) => {
                if (!r.isConfirmed) return;
                $.ajax({ url: url, type: 'POST', data: { _token: csrf }, headers: { Accept: 'application/json' } })
                    .done((res) => { toastr.success(res.message); reload(); })
                    .fail((xhr) => toastr.error(errorText(xhr), 'Error'));
            });
    });
});
</script>
{{ $dataTable->scripts() }}
@endpush
