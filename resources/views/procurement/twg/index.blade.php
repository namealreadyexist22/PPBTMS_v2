@extends('BackEnd.layouts.master')

@section('content')
<div class="mb-3">
    <h5 class="fw-bold mb-0">
        <i class="fas fa-clipboard-check text-muted me-2"></i>TWG Evaluation
        {{-- What the page is for: on hover / focus, as on Standard Items --}}
        <a tabindex="0" role="button" class="text-muted ms-1 fs-6 align-middle js-help" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="right"
           title="What the TWG evaluates"
           data-bs-content="Abstracts of Quotations the BAC assigned to you. Check each supplier's offer against the item's specifications, with the quotation and spec PDFs, then complete the evaluation or return it to the BAC."><i class="far fa-question-circle"></i></a>
    </h5>
</div>

<div class="card border-0 shadow-sm" id="twg_card" style="border-radius: 12px; overflow: hidden;">
    <div class="card-header bg-white px-4 py-3" style="border-bottom: 1px solid #f1f5f9;">
        <div class="d-flex align-items-center gap-2 mb-2">
            <span class="fw-bold"><i class="fas fa-list-check text-muted me-2"></i>Assigned abstracts</span>
            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle"><i class="fas fa-hourglass-half me-1"></i>{{ $pending }} for evaluation</span>
        </div>
        <form method="GET" class="d-flex flex-wrap align-items-center gap-2" id="twg_filter">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search AOQ no. or title" style="width: 260px;">
            <div class="d-flex align-items-center gap-2 ms-auto">
                <span class="spinner-border spinner-border-sm text-primary list-spinner" role="status" aria-label="Loading"></span>
                <select name="show" class="form-select form-select-sm" style="width: 170px;" aria-label="Show">
                    <option value="">All</option>
                    <option value="pending" @selected(request('show') === 'pending')>For evaluation</option>
                    <option value="done" @selected(request('show') === 'done')>Evaluated</option>
                </select>
                @include('procurement.partials.year_filter')
            </div>
        </form>
    </div>
    {{-- Server-side DataTable: one page at a time; waiting for the evaluation first, then the evaluated ones --}}
    <div class="position-relative px-4 pb-3 pt-2">
        @include('procurement.partials.list_loading')
        {{ $dataTable->table(['class' => 'table table-sm table-hover align-middle small w-100 mb-0']) }}
    </div>
</div>
@endsection

@push('script')
{{-- Before the table's own script, so the spinner shows for its first load too --}}
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-help').forEach((el) => bootstrap.Popover.getOrCreateInstance(el, { container: 'body' }));

    // Search, show, year and pages: the table asks the server; the URL follows. A row opens the evaluation.
    serverList({ table: '#tblTwg', card: '#twg_card', form: '#twg_filter', url: '{{ route("procurement.twg.index") }}' });
});
</script>
{{ $dataTable->scripts() }}
@endpush
