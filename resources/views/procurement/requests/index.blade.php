@extends('BackEnd.layouts.master')

@section('content')
@php use App\Enums\RequestKind; use App\Enums\RequestStatus; @endphp

<style>
    /* The row under the pointer stands out (the whole row opens the request) */
</style>

<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
            <div class="card-header bg-white pt-4 pb-0 px-4" style="border-bottom: 1px solid #f1f5f9;">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <h5 class="m-0 fw-bold text-dark" style="font-size: 1.05rem;">
                        <i class="fas fa-file-invoice text-muted me-2"></i>Purchase &amp; Job Requests
                    </h5>
                    <button id="btn_add" data-kind="{{ $kind->value }}" class="btn btn-sm text-white fw-medium shadow-sm px-3" style="background-color: #10b981; border-color: #10b981; border-radius: 6px;">
                        <i class="fas fa-plus me-1"></i>New <span id="btn_add_kind">{{ $kind->short() }}</span>
                    </button>
                </div>
                <ul class="nav nav-tabs border-0" id="requests_tabs">
                    @foreach (RequestKind::cases() as $tab)
                        <li class="nav-item">
                            <a class="nav-link request-tab {{ $tab === $kind ? 'active fw-semibold' : 'text-muted' }}" data-kind="{{ $tab->value }}" data-short="{{ $tab->short() }}" href="{{ route('procurement.requests.index', ['kind' => $tab->value]) }}">
                                {{ $tab->label() }} <span class="badge bg-light text-dark border ms-1">{{ $counts[$tab->value] ?? 0 }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="card-body px-4 pb-4 pt-3">
                <form class="row g-2 mb-3" method="GET" id="requests_filter">
                    <input type="hidden" name="kind" value="{{ $kind->value }}">
                    <div class="col-12 col-md-5">
                        <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="Search number or purpose">
                    </div>
                    <div class="col-12 col-md-3">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All (except superseded)</option>
                            @foreach (RequestStatus::cases() as $s)
                                <option value="{{ $s->value }}" @selected($status === $s)>{{ $s->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>

                {{-- Redrawn in place on tab, search, status and page changes (no full reload) --}}
                <div id="requests_view">
                <div class="table-responsive">
                    <table class="table table-hover align-middle small mb-0">
                        <thead class="table-light">
                            <tr class="text-nowrap">
                                <th>{{ $kind === RequestKind::Jr ? 'J.R. No.' : 'PR No.' }}</th>
                                <th>Date</th>
                                <th>Charge to</th>
                                <th>Items</th>
                                <th class="text-end">{{ $kind === RequestKind::Jr ? 'ABC' : 'Total' }}</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($requests as $row)
                                @php
                                    $itemText = fn ($i) => e($i->description) . ' <span class="text-muted">× ' . e(rtrim(rtrim(number_format((float) $i->quantity, 2), '0'), '.') . ' ' . $i->unit) . '</span>';
                                @endphp
                                <tr>
                                    <td class="fw-semibold text-nowrap">
                                        <a href="{{ route('procurement.requests.show', $row) }}" class="text-decoration-none" title="Open {{ $row->title() }}">
                                            <i class="fas fa-file-invoice me-1 small"></i>{{ $row->request_no ?? 'Draft' }}
                                        </a>
                                        @if ($row->revision) <span class="badge bg-info text-dark">Rev. {{ $row->revision }}</span>@endif
                                    </td>
                                    <td class="text-nowrap">{{ ($row->submitted_at ?? $row->created_at)->format('M d, Y') }}</td>
                                    <td class="text-nowrap">{{ $row->pap?->code }}</td>
                                    {{-- Up to 5 items listed; 6 or more: hover the count to see them all --}}
                                    <td style="min-width: 220px;">
                                        @if ($row->items->isEmpty())
                                            <span class="text-muted">No items yet</span>
                                        @elseif ($row->items->count() <= 5)
                                            <ul class="list-unstyled mb-0">
                                                @foreach ($row->items as $i)<li class="text-truncate" style="max-width: 360px;">{!! $itemText($i) !!}</li>@endforeach
                                            </ul>
                                        @else
                                            <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis border border-primary-subtle js-items-popover" tabindex="0"
                                                  data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="right" data-bs-html="true"
                                                  data-bs-title="{{ $row->items->count() }} items"
                                                  data-bs-content="{{ '<ul class="ps-3 mb-0 small">' . $row->items->map(fn ($i) => '<li>' . $itemText($i) . '</li>')->implode('') . '</ul>' }}">
                                                <i class="fas fa-list me-1"></i>{{ $row->items->count() }} items
                                            </span>
                                            <span class="text-muted small ms-1 text-truncate d-inline-block align-middle" style="max-width: 240px;">{{ $row->items->take(2)->pluck('description')->implode(', ') }}, …</span>
                                        @endif
                                    </td>
                                    <td class="text-end text-nowrap">{{ number_format((float) $row->total_amount, 2) }}</td>
                                    <td class="text-center"><span class="badge bg-{{ $row->status->color() }}">{{ $row->status->label() }}</span></td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 js-trail" data-url="{{ route('procurement.requests.trail', $row) }}" title="Status trail of {{ $row->title() }}">
                                            <i class="fas fa-route me-1"></i>Status
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted py-4">{{ $search !== '' || $status ? 'No ' . strtolower($kind->label()) . 's match the search.' : 'No ' . strtolower($kind->label()) . 's yet.' }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $requests->links() }}</div>
                </div>{{-- /#requests_view --}}
            </div>
        </div>
    </div>
    <div id="modal-body"></div>
</div>
@endsection

@push('script')
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function () {
    let isModalOpen = false;
    const modalName = 'REQUEST_ENTRY_MODAL';

    // Tabs, search, status and pages: redraw the list without reloading; the URL follows (refresh / Back keep it)
    let pending = null;
    function loadList(url) {
        pending?.abort();
        $('#requests_view').css('opacity', .6);
        history.replaceState(null, '', url);
        pending = $.get(url).done(function (html) {
            const fresh = $('<div>').append($.parseHTML(html)).find('#requests_view');
            $('.popover').remove();
            fresh.length ? $('#requests_view').replaceWith(fresh) : window.location.reload();
            initRowCards();
        }).fail((xhr) => { if (xhr.statusText !== 'abort') window.location.href = url; })
          .always(() => $('#requests_view').css('opacity', ''));
    }
    // 6 or more items: hovering the count lists them all
    function initRowCards() {
        document.querySelectorAll('#requests_view .js-items-popover').forEach((el) => bootstrap.Popover.getOrCreateInstance(el, { container: 'body' }));
    }
    initRowCards();

    function applyFilter() {
        const params = new URLSearchParams();
        $('#requests_filter').serializeArray().forEach((f) => { if (f.value.trim()) params.set(f.name, f.value.trim()); });
        loadList('{{ route("procurement.requests.index") }}?' + params);
    }
    let typing = null;
    $('#requests_filter').on('submit', function (e) { e.preventDefault(); clearTimeout(typing); applyFilter(); });
    $('#requests_filter [name=q]').on('input', function () { clearTimeout(typing); typing = setTimeout(applyFilter, 300); });
    $('#requests_filter [name=status]').on('change', applyFilter);

    $(document).on('click', '.request-tab', function (e) {
        if (e.ctrlKey || e.metaKey || e.shiftKey) return;
        e.preventDefault();
        if ($(this).hasClass('active')) return;
        $('.request-tab').removeClass('active fw-semibold').addClass('text-muted');
        $(this).addClass('active fw-semibold').removeClass('text-muted');
        $('#requests_filter [name=kind]').val($(this).data('kind'));
        $('#btn_add').data('kind', $(this).data('kind'));
        $('#btn_add_kind').text($(this).data('short'));
        applyFilter();
    });

    // Status trail in a modal
    $(document).on('click', '.js-trail', function () {
        if (isModalOpen) return;
        isModalOpen = true;
        $.get($(this).data('url'), function (html) {
            $('#modal-body').html(html);
            const el = document.getElementById('REQUEST_TRAIL_MODAL');
            new bootstrap.Modal(el).show();
            el.addEventListener('hidden.bs.modal', function () { isModalOpen = false; $('#modal-body').html(''); });
        }).fail(function () { isModalOpen = false; toastr.error('Could not load the status trail.', 'Error'); });
    });

    $(document).on('click', '#requests_view .pagination a', function (e) {
        e.preventDefault();
        loadList(this.href);
    });

    $('#btn_add').on('click', function (e) {
        e.preventDefault();
        if (isModalOpen) return;
        isModalOpen = true;
        $.get('{{ route("procurement.requests.entry") }}', { kind: $(this).data('kind') }, function (html) {
            $('#modal-body').html(html);
            const el = document.getElementById(modalName);
            new bootstrap.Modal(el).show();
            el.addEventListener('hidden.bs.modal', function () { isModalOpen = false; $('#modal-body').html(''); });
        }).fail(function (xhr) {
            isModalOpen = false;
            toastr.error(xhr.responseJSON?.message ?? 'Could not open form.', 'Error');
        });
    });

    $(document).off('submit', '#form_request_entry').on('submit', '#form_request_entry', function (e) {
        e.preventDefault();
        const form = $(this);
        const errorSummary = $('#request_error_summary');
        form.find('.is-invalid').removeClass('is-invalid');
        errorSummary.addClass('d-none');
        $('#btn_save_request').prop('disabled', true);

        $.post('{{ route("procurement.requests.store") }}', form.serialize())
            .done(function (res) {
                toastr.success(res.message, 'Success');
                window.location.href = res.url;
            })
            .fail(function (xhr) {
                $('#btn_save_request').prop('disabled', false);
                const res = xhr.responseJSON || {};
                errorSummary.removeClass('d-none');
                if (res.errors) {
                    errorSummary.find('span').text(Object.values(res.errors)[0][0]);
                    $.each(res.errors, (key) => form.find('[name="' + key + '"]').addClass('is-invalid'));
                } else {
                    errorSummary.find('span').text(res.message ?? 'Something went wrong.');
                }
            });
    });
});
</script>
@endpush
