@extends('BackEnd.layouts.master')

@section('content')
@php
    use App\Enums\ItemRequestStatus;
    $qty = fn ($q) => $q === null ? '—' : rtrim(rtrim(number_format((float) $q, 2), '0'), '.');
    $tabUrl = fn ($t) => route('procurement.item-requests.index', array_filter(['tab' => $t === 'ours' ? null : $t, 'status' => $status?->value]));
@endphp

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h5 class="fw-bold mb-0"><i class="fas fa-hand-holding text-muted me-2"></i>Item Requests</h5>
        <div class="text-muted small">Ask the office that procures an item (e.g. MIS for ICT equipment) for what your office needs. It approves all, part or none of it, and what it approves goes to your share in its PPMP project's distribution.</div>
    </div>
    <button class="btn btn-sm btn-success" id="btn_new_request"><i class="fas fa-plus me-1"></i> New Request</button>
</div>

<div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
    {{-- The tabs stay; only the list below is redrawn (the active tab and the pending count follow) --}}
    <div class="card-header bg-white pt-3 pb-0 px-4" style="border-bottom: 1px solid #f1f5f9;">
        <ul class="nav nav-tabs border-0" id="ir_tabs">
            <li class="nav-item"><a class="nav-link {{ $tab === 'ours' ? 'active fw-semibold' : 'text-muted' }}" data-tab="ours" href="{{ $tabUrl('ours') }}">Our requests</a></li>
            @if ($procuring->isNotEmpty())
                @php $pending = $incoming->getCollection()->filter->isPending()->count(); @endphp
                <li class="nav-item">
                    <a class="nav-link {{ $tab === 'incoming' ? 'active fw-semibold' : 'text-muted' }}" data-tab="incoming" href="{{ $tabUrl('incoming') }}">
                        Requests to us <span class="badge bg-warning text-dark ms-1 js-pending {{ $pending ? '' : 'd-none' }}">{{ $pending }}</span>
                    </a>
                </li>
            @endif
        </ul>
    </div>
    {{-- Redrawn in place on tab, status and page changes and after a request is sent, cancelled or decided --}}
    <div class="card-body px-4 py-3" id="ir_view">
        <form method="GET" class="d-flex flex-wrap gap-2 mb-3" id="ir_filter">
            @if ($tab === 'incoming')<input type="hidden" name="tab" value="incoming">@endif
            <select name="status" class="form-select form-select-sm" style="width: 200px;">
                <option value="">All statuses</option>
                @foreach (ItemRequestStatus::cases() as $s)<option value="{{ $s->value }}" @selected($status === $s)>{{ $s->label() }}</option>@endforeach
            </select>
        </form>

        @if ($tab === 'incoming')
            {{-- To size the projects: per item, what offices are waiting for and what was approved --}}
            @if ($totals->isNotEmpty())
                <div class="small fw-semibold mb-1">Requested from your office, per item</div>
                <div class="table-responsive border rounded-3 mb-4">
                    <table class="table table-sm align-middle small mb-0">
                        <thead class="table-light"><tr class="text-nowrap"><th class="ps-3">FY</th><th>Item</th><th class="text-center">Offices</th><th class="text-end">Pending</th><th class="text-end pe-3">Approved</th></tr></thead>
                        <tbody>
                            @foreach ($totals as $t)
                                <tr>
                                    <td class="ps-3">{{ $t['fiscal_year'] }}</td>
                                    <td><span class="fw-semibold">{{ $t['item']->code }}</span> {{ $t['item']->name }}</td>
                                    <td class="text-center">{{ $t['offices'] }}</td>
                                    <td class="text-end text-nowrap">{{ $qty($t['pending']) }} {{ $t['item']->unit?->name }}</td>
                                    <td class="text-end text-nowrap pe-3">{{ $qty($t['approved']) }} {{ $t['item']->unit?->name }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif

        @php $rows = $tab === 'incoming' ? $incoming : $ours; @endphp
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle small mb-0">
                <thead class="table-light">
                    <tr class="text-nowrap">
                        <th class="ps-3">Request</th><th>{{ $tab === 'incoming' ? 'From' : 'To' }}</th><th>Item</th><th class="text-end">Requested</th><th class="text-end">Approved</th>
                        <th class="text-center">Status</th><th class="pe-3 text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $ir)
                        <tr>
                            <td class="ps-3 text-nowrap">
                                {{-- The request's details (purpose, specs, the decision) in a modal --}}
                                <a href="#" class="fw-semibold text-decoration-none js-ir-details" data-url="{{ route('procurement.item-requests.show', $ir) }}" title="View the details">{{ $ir->request_no }} <i class="fas fa-eye ms-1" style="font-size: .7rem;"></i></a>
                                <div class="text-muted">FY {{ $ir->fiscal_year }} · {{ $ir->created_at->format('M d, Y') }}</div>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ ($tab === 'incoming' ? $ir->requestingOffice : $ir->procuringOffice)?->shortName() }}</div>
                                @if ($tab === 'incoming' && $ir->requester)<div class="text-muted">{{ $ir->requester->fullname }}</div>@endif
                            </td>
                            <td><span class="fw-semibold">{{ $ir->item?->code }}</span> {{ $ir->item?->name }}</td>
                            <td class="text-end text-nowrap">{{ $qty($ir->quantity) }} {{ $ir->item?->unit?->name }}</td>
                            <td class="text-end text-nowrap fw-semibold">{{ $ir->isPending() || $ir->status === ItemRequestStatus::Cancelled ? '—' : $qty($ir->approved_quantity) }}</td>
                            <td class="text-center"><span class="badge bg-{{ $ir->status->color() }}">{{ $ir->status->label() }}</span></td>
                            <td class="pe-3 text-end text-nowrap">
                                <button class="btn btn-sm btn-outline-secondary py-0 js-ir-details" data-url="{{ route('procurement.item-requests.show', $ir) }}">View</button>
                                @if ($ir->isPending())
                                    @if ($tab === 'incoming')
                                        <button class="btn btn-sm btn-primary py-0 js-decide" data-url="{{ route('procurement.item-requests.decide-entry', $ir) }}">Decide</button>
                                    @else
                                        <button class="btn btn-sm btn-outline-danger py-0 js-cancel" data-url="{{ route('procurement.item-requests.cancel', $ir) }}" data-no="{{ $ir->request_no }}">Cancel</button>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-5">{{ $tab === 'incoming' ? 'No requests to your office.' : 'Your office has not requested any item yet.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $rows->links() }}</div>
    </div>{{-- /#ir_view --}}
</div>
<div id="modal-body"></div>
@endsection

@push('script')
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function () {
    const csrf = '{{ csrf_token() }}';
    let isModalOpen = false;
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

    // Tabs, status and pages: redraw the list without reloading; the URL follows (refresh / Back keep it)
    let pending = null;
    function loadView(url, push = true) {
        pending?.abort();
        $('#ir_view').css('opacity', .6);
        if (push) history.replaceState(null, '', url);
        pending = $.get(url).done(function (html) {
            const page = $('<div>').append($.parseHTML(html));
            const fresh = page.find('#ir_view');
            if (!fresh.length) { window.location.reload(); return; }
            $('#ir_view').replaceWith(fresh);
            // The tabs stay: their links (they carry the status) and the pending count follow
            page.find('#ir_tabs .nav-link').each(function () {
                const tab = $('#ir_tabs .nav-link[data-tab="' + this.dataset.tab + '"]');
                tab.attr('href', this.getAttribute('href')).attr('class', this.className);
                tab.find('.js-pending').replaceWith($(this).find('.js-pending'));
            });
        }).fail((xhr) => { if (xhr.statusText !== 'abort') window.location.href = url; })
          .always(() => $('#ir_view').css('opacity', ''));
        return pending;
    }

    $(document).on('click', '#ir_tabs .nav-link, #ir_view .pagination a', function (e) {
        if (e.ctrlKey || e.metaKey || e.shiftKey || !this.href) return;
        e.preventDefault();
        // The tab shows as chosen right away
        if (this.dataset.tab) {
            $('#ir_tabs .nav-link').removeClass('active fw-semibold').addClass('text-muted');
            $(this).addClass('active fw-semibold').removeClass('text-muted');
        }
        loadView(this.href);
    });
    $(document).on('change', '#ir_filter select', function () {
        const params = new URLSearchParams();
        $('#ir_filter').serializeArray().forEach((f) => { if (f.value) params.set(f.name, f.value); });
        loadView('{{ route("procurement.item-requests.index") }}' + (params.toString() ? '?' + params : ''));
    });
    $(document).on('submit', '#ir_filter', (e) => e.preventDefault());

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
                loadView(window.location.href, false);
            })
            .fail((xhr) => toastr.error(errorText(xhr), 'Error'));
    });

    $(document).on('click', '.js-cancel', function () {
        const url = $(this).data('url');
        Swal.fire({ title: 'Cancel ' + $(this).data('no') + '?', text: 'The procuring office will no longer see it as pending.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Cancel request', cancelButtonText: 'Keep', reverseButtons: true })
            .then((r) => {
                if (!r.isConfirmed) return;
                $.ajax({ url: url, type: 'POST', data: { _token: csrf }, headers: { Accept: 'application/json' } })
                    .done((res) => { toastr.success(res.message); loadView(window.location.href, false); })
                    .fail((xhr) => toastr.error(errorText(xhr), 'Error'));
            });
    });
});
</script>
@endpush
