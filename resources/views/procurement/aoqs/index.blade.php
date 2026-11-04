@extends('BackEnd.layouts.master')

@section('content')
@php use App\Enums\AoqStatus; @endphp

<style>
    #aoq_pending .js-pending-row:hover > td { background-color: var(--bs-primary-bg-subtle); box-shadow: none; }
    #aoq_pending .js-pending-row:hover > td:first-child { box-shadow: inset 3px 0 0 var(--bs-primary); }
</style>

<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm" id="aoqs_card" style="border-radius: 12px; overflow: hidden;">
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

                <form class="d-flex flex-wrap align-items-center gap-2 mb-2" method="GET" id="aoqs_filter">
                    <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search RFQ no., title or supplier" style="width: 280px;">
                    <div class="d-flex align-items-center gap-2 ms-auto">
                        <span class="spinner-border spinner-border-sm text-primary list-spinner" role="status" aria-label="Loading"></span>
                        <select name="status" class="form-select form-select-sm" style="width: 160px;" aria-label="Status">
                            <option value="">All statuses</option>
                            @foreach (AoqStatus::cases() as $s)
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
@endsection

@push('script')
{{-- Before the table's own script, so the spinner shows for its first load too --}}
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function () {
    // Search, status, year and pages: the table asks the server; the URL follows. A row opens its abstract.
    serverList({ table: '#tblAoqs', card: '#aoqs_card', form: '#aoqs_filter', url: '{{ route("procurement.aoqs.index") }}' });

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
{{ $dataTable->scripts() }}
@endpush
