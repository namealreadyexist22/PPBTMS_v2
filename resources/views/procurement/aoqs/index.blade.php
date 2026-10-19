@extends('BackEnd.layouts.master')

@section('content')
@php
    use App\Enums\AoqStatus;
    use App\Support\Money;
    use App\Support\Quantity;
@endphp

<style>
    #aoqs_view .js-aoq-row:hover > td, #aoq_pending .js-pending-row:hover > td { background-color: var(--bs-primary-bg-subtle); box-shadow: none; }
    #aoqs_view .js-aoq-row:hover > td:first-child, #aoq_pending .js-pending-row:hover > td:first-child { box-shadow: inset 3px 0 0 var(--bs-primary); }
</style>

<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
            <div class="card-header bg-white pt-4 pb-3 px-4" style="border-bottom: 1px solid #f1f5f9;">
                <h5 class="m-0 fw-bold text-dark" style="font-size: 1.05rem;">
                    <i class="fas fa-balance-scale text-muted me-2"></i>Abstract of Quotations
                </h5>
            </div>

            <div class="card-body px-4 pb-4 pt-3">
                {{-- Issued RFQs whose quotations are not abstracted yet --}}
                @if ($pending->isNotEmpty())
                    <div id="aoq_pending" class="mb-4">
                        <div class="small fw-semibold text-muted text-uppercase mb-2" style="letter-spacing: .5px;">Issued RFQs awaiting an abstract <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle ms-1">{{ $pending->count() }}</span></div>
                        <div class="table-responsive border rounded-3">
                            <table class="table table-hover align-middle small mb-0">
                                <thead class="table-light">
                                    <tr class="text-nowrap">
                                        <th>RFQ No.</th><th>Title</th><th>Mode</th><th>Deadline</th><th class="text-end">ABC</th><th class="text-end" style="width: 140px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($pending as $rfq)
                                        <tr class="js-pending-row">
                                            <td class="fw-semibold text-nowrap">{{ $rfq->rfq_no }}</td>
                                            <td class="text-truncate" style="max-width: 360px;" title="{{ $rfq->label() }}">{{ $rfq->label() }}</td>
                                            <td>{{ $rfq->procurementMode?->code ?? '—' }}</td>
                                            <td class="text-nowrap {{ $rfq->submission_deadline?->isFuture() ? 'text-muted' : '' }}">{{ $rfq->submission_deadline?->format('M d, Y g:i A') ?? '—' }}</td>
                                            <td class="text-end text-nowrap">{{ number_format((float) $rfq->total_abc, 2) }}</td>
                                            <td class="text-end"><button class="btn btn-sm btn-primary js-start" data-url="{{ route('procurement.aoqs.start', $rfq) }}"><i class="fas fa-plus me-1"></i> Start AOQ</button></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <form class="row g-2 mb-3" method="GET" id="aoqs_filter">
                    <div class="col-12 col-md-5">
                        <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="Search RFQ no., title or supplier">
                    </div>
                    <div class="col-12 col-md-3">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All</option>
                            @foreach (AoqStatus::cases() as $s)
                                <option value="{{ $s->value }}" @selected($status === $s)>{{ $s->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>

                <div id="aoqs_view">
                <div class="table-responsive">
                    <table class="table table-hover align-middle small mb-0">
                        <thead class="table-light">
                            <tr class="text-nowrap">
                                <th>AOQ No.</th>
                                <th>Title</th>
                                <th class="text-center">Items</th>
                                <th class="text-center">Quotations</th>
                                <th>Awarded to</th>
                                <th class="text-end">ABC</th>
                                <th class="text-end">Award</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($aoqs as $row)
                                @php
                                    $lines = $row->rfq->items->keyBy('id');
                                    $won = $row->quotes->where('is_awarded', true);
                                    $awardCents = $won->sum(fn ($q) => (int) round(Quantity::toHundredths($lines->get($q->rfq_item_id)?->line?->quantity) * Money::toCents($q->unit_price) / 100));
                                    $winners = $row->suppliers->whereIn('id', $won->pluck('aoq_supplier_id'))->pluck('name');
                                @endphp
                                <tr role="button" class="js-aoq-row" onclick="window.location='{{ route('procurement.aoqs.show', $row) }}'">
                                    <td class="fw-semibold text-nowrap">{{ $row->rfq->rfq_no }}</td>
                                    <td class="text-truncate" style="max-width: 320px;" title="{{ $row->rfq->label() }}">{{ $row->rfq->label() }}</td>
                                    <td class="text-center">{{ $row->rfq->items->count() }}</td>
                                    <td class="text-center">{{ $row->suppliers->count() }}</td>
                                    <td>{!! $winners->map(fn ($n) => e($n))->implode('<br>') ?: '<span class="text-muted">—</span>' !!}</td>
                                    <td class="text-end text-nowrap">{{ number_format((float) $row->rfq->total_abc, 2) }}</td>
                                    <td class="text-end text-nowrap fw-semibold">{{ $won->isNotEmpty() ? Money::format(Money::fromCents($awardCents)) : '—' }}</td>
                                    <td class="text-center"><span class="badge bg-{{ $row->status->color() }}">{{ $row->status->label() }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center text-muted py-4">{{ $search !== '' || $status ? 'No abstracts match the search.' : 'No abstracts yet. Start one from an issued RFQ once its quotations are in.' }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $aoqs->links() }}</div>
                </div>{{-- /#aoqs_view --}}
            </div>
        </div>
    </div>
</div>
@endsection

@push('script')
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function () {
    let pending = null;
    function loadList(url) {
        pending?.abort();
        $('#aoqs_view').css('opacity', .6);
        history.replaceState(null, '', url);
        pending = $.get(url).done(function (html) {
            const fresh = $('<div>').append($.parseHTML(html)).find('#aoqs_view');
            fresh.length ? $('#aoqs_view').replaceWith(fresh) : window.location.reload();
        }).fail((xhr) => { if (xhr.statusText !== 'abort') window.location.href = url; })
          .always(() => $('#aoqs_view').css('opacity', ''));
    }
    function applyFilter() {
        const params = new URLSearchParams();
        $('#aoqs_filter').serializeArray().forEach((f) => { if (f.value.trim()) params.set(f.name, f.value.trim()); });
        loadList('{{ route("procurement.aoqs.index") }}?' + params);
    }
    let typing = null;
    $('#aoqs_filter').on('submit', function (e) { e.preventDefault(); clearTimeout(typing); applyFilter(); });
    $('#aoqs_filter [name=q]').on('input', function () { clearTimeout(typing); typing = setTimeout(applyFilter, 300); });
    $('#aoqs_filter [name=status]').on('change', applyFilter);
    $(document).on('click', '#aoqs_view .pagination a', function (e) { e.preventDefault(); loadList(this.href); });

    $(document).on('click', '.js-start', function () {
        const btn = $(this).prop('disabled', true);
        $.ajax({ url: btn.data('url'), type: 'POST', data: { _token: '{{ csrf_token() }}' }, headers: { Accept: 'application/json' } })
            .done((res) => { toastr.success(res.message, 'Success'); window.location.href = res.url; })
            .fail(function (xhr) {
                btn.prop('disabled', false);
                const res = xhr.responseJSON || {};
                toastr.error(res.message && res.message !== 'Server Error' ? res.message : 'Something went wrong (error ' + xhr.status + ').', 'Error');
            });
    });
});
</script>
@endpush
