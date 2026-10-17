@extends('BackEnd.layouts.master')

@section('content')
@php use App\Enums\RfqStatus; @endphp

<style>
    /* As on the PR / JR list: the row under the pointer stands out (the whole row opens the RFQ) */
    #rfqs_view .js-rfq-row:hover > td { background-color: var(--bs-primary-bg-subtle); box-shadow: none; }
    #rfqs_view .js-rfq-row:hover > td:first-child { box-shadow: inset 3px 0 0 var(--bs-primary); }
</style>

<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
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
                <form class="row g-2 mb-3" method="GET" id="rfqs_filter">
                    <div class="col-12 col-md-5">
                        <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="Search RFQ no., title or PR / JR no.">
                    </div>
                    <div class="col-12 col-md-3">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All</option>
                            @foreach (RfqStatus::cases() as $s)
                                <option value="{{ $s->value }}" @selected($status === $s)>{{ $s->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>

                {{-- Redrawn in place on search, status and page changes (no full reload) --}}
                <div id="rfqs_view">
                <div class="table-responsive">
                    <table class="table table-hover align-middle small mb-0">
                        <thead class="table-light">
                            <tr class="text-nowrap">
                                <th>RFQ No.</th>
                                <th>Date</th>
                                <th>Title</th>
                                <th>Items</th>
                                <th>PR / JR</th>
                                <th>Mode</th>
                                <th class="text-end">ABC</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rfqs as $row)
                                @php
                                    $lines = $row->items->map->line->filter();
                                    $itemText = fn ($l) => e($l->description) . ' <span class="text-muted">× ' . e(rtrim(rtrim(number_format((float) $l->quantity, 2), '0'), '.') . ' ' . $l->unit) . '</span>';
                                @endphp
                                <tr role="button" class="js-rfq-row" onclick="window.location='{{ route('procurement.rfqs.show', $row) }}'">
                                    <td class="fw-semibold text-nowrap">{{ $row->rfq_no ?? 'Draft' }}</td>
                                    <td class="text-nowrap">{{ ($row->issued_at ?? $row->created_at)->format('M d, Y') }}</td>
                                    <td class="text-truncate" style="max-width: 320px;" title="{{ $row->title }}">{{ $row->title }}</td>
                                    {{-- Up to 5 items listed; 6 or more: hover the count to see them all --}}
                                    <td style="min-width: 220px;">
                                        @if ($lines->isEmpty())
                                            <span class="text-muted">No items yet</span>
                                        @elseif ($lines->count() <= 5)
                                            <ul class="list-unstyled mb-0">
                                                @foreach ($lines as $l)<li class="text-truncate" style="max-width: 360px;">{!! $itemText($l) !!}</li>@endforeach
                                            </ul>
                                        @else
                                            <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis border border-primary-subtle js-items-popover" tabindex="0"
                                                  data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="right" data-bs-html="true"
                                                  data-bs-title="{{ $lines->count() }} items"
                                                  data-bs-content="{{ '<ul class="ps-3 mb-0 small">' . $lines->map(fn ($l) => '<li>' . $itemText($l) . '</li>')->implode('') . '</ul>' }}">
                                                <i class="fas fa-list me-1"></i>{{ $lines->count() }} items
                                            </span>
                                            <span class="text-muted small ms-1 text-truncate d-inline-block align-middle" style="max-width: 240px;">{{ $lines->take(2)->pluck('description')->implode(', ') }}, …</span>
                                        @endif
                                    </td>
                                    <td class="text-nowrap">{!! $row->requests()->map(fn ($pr) => e($pr->title()))->implode('<br>') ?: '—' !!}</td>
                                    <td class="text-nowrap">{{ $row->procurementMode?->code ?? '—' }}</td>
                                    <td class="text-end text-nowrap">{{ number_format((float) $row->total_abc, 2) }}</td>
                                    <td class="text-center"><span class="badge bg-{{ $row->status->color() }}">{{ $row->status->label() }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center text-muted py-4">{{ $search !== '' || $status ? 'No RFQs match the search.' : 'No RFQs yet. Start one and add items from submitted PRs / JRs.' }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $rfqs->links() }}</div>
                </div>{{-- /#rfqs_view --}}
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
                    <label class="form-label small fw-semibold text-muted mb-1">Title</label>
                    <input type="text" name="title" class="form-control form-control-sm" maxlength="500" placeholder="e.g. ICT equipment and supplies" required>
                    <div class="form-text">What the RFQ is for. Items from several submitted PRs / JRs of one procurement mode can go on it.</div>
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
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function () {
    // Search, status and pages: redraw the list without reloading; the URL follows
    let pending = null;
    function loadList(url) {
        pending?.abort();
        $('#rfqs_view').css('opacity', .6);
        history.replaceState(null, '', url);
        pending = $.get(url).done(function (html) {
            const fresh = $('<div>').append($.parseHTML(html)).find('#rfqs_view');
            $('.popover').remove();
            fresh.length ? $('#rfqs_view').replaceWith(fresh) : window.location.reload();
            initItemPopovers();
        }).fail((xhr) => { if (xhr.statusText !== 'abort') window.location.href = url; })
          .always(() => $('#rfqs_view').css('opacity', ''));
    }
    // 6 or more items: hovering the count lists them all
    function initItemPopovers() {
        document.querySelectorAll('#rfqs_view .js-items-popover').forEach((el) => bootstrap.Popover.getOrCreateInstance(el, { container: 'body' }));
    }
    initItemPopovers();

    function applyFilter() {
        const params = new URLSearchParams();
        $('#rfqs_filter').serializeArray().forEach((f) => { if (f.value.trim()) params.set(f.name, f.value.trim()); });
        loadList('{{ route("procurement.rfqs.index") }}?' + params);
    }
    let typing = null;
    $('#rfqs_filter').on('submit', function (e) { e.preventDefault(); clearTimeout(typing); applyFilter(); });
    $('#rfqs_filter [name=q]').on('input', function () { clearTimeout(typing); typing = setTimeout(applyFilter, 300); });
    $('#rfqs_filter [name=status]').on('change', applyFilter);
    $(document).on('click', '#rfqs_view .pagination a', function (e) { e.preventDefault(); loadList(this.href); });

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
@endpush
