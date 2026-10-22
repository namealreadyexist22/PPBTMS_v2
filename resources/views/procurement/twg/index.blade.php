@extends('BackEnd.layouts.master')

@section('content')
<div class="mb-3">
    <h5 class="fw-bold mb-0"><i class="fas fa-clipboard-check text-muted me-2"></i>TWG Evaluation</h5>
    <div class="text-muted small">Abstracts of Quotations the BAC assigned to you. Check each supplier's offer against the item's specifications, with the quotation and spec PDFs, then complete the evaluation or return it to the BAC.</div>
</div>

<div class="card border-0 shadow-sm mb-3" style="border-radius: 12px; overflow: hidden;">
    <div class="card-header bg-white px-4 py-3 fw-bold" style="border-bottom: 1px solid #f1f5f9;">
        <i class="fas fa-hourglass-half text-warning me-2"></i>For evaluation <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle ms-1">{{ $pending->count() }}</span>
    </div>
    @include('procurement.twg.extras.list', ['aoqs' => $pending, 'empty' => 'Nothing to evaluate right now.'])
</div>

<div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
    <div class="card-header bg-white px-4 py-3 fw-bold" style="border-bottom: 1px solid #f1f5f9;">
        <i class="fas fa-check-double text-muted me-2"></i>Evaluated
    </div>
    @include('procurement.twg.extras.list', ['aoqs' => $done, 'empty' => 'No evaluations yet.'])
</div>
@endsection
