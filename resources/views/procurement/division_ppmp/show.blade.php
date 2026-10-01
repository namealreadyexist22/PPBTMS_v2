@extends('BackEnd.layouts.master')

@section('content')
@php $prepared = $divisionPpmp->latestSignatory('prepared'); $submitted = $divisionPpmp->latestSignatory('submitted'); @endphp

<div class="mb-3">
    <a href="{{ route('procurement.division-ppmp.index', ['fy' => $divisionPpmp->fiscal_year]) }}" class="text-decoration-none small text-muted">
        <i class="fas fa-arrow-left me-1"></i> Back to Division PPMP
    </a>
</div>

<div class="card border-0 shadow-sm mb-3" style="border-radius: 12px;">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
            <div>
                <div class="small text-muted text-uppercase fw-semibold">Project Procurement Management Plan</div>
                <h4 class="fw-bold mb-1">
                    PPMP No. {{ $divisionPpmp->ppmp_number }}
                    <span class="badge {{ $divisionPpmp->isCurrent() ? 'bg-success' : 'bg-dark' }} align-middle ms-1" style="font-size: .7rem;">{{ $divisionPpmp->isCurrent() ? 'Current' : 'Superseded' }}</span>
                </h4>
                <div class="text-muted small">{{ $divisionPpmp->office->label() }}</div>
            </div>
            <a href="{{ route('procurement.division-ppmp.print', $divisionPpmp) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fas fa-print me-1"></i> Print</a>
        </div>
        <hr class="my-3">
        <div class="row g-3 small">
            <div class="col-6 col-md-2"><div class="text-muted">Fiscal Year</div><div class="fw-semibold">{{ $divisionPpmp->fiscal_year }}</div></div>
            <div class="col-6 col-md-2"><div class="text-muted">Type</div><div class="fw-semibold">{{ $divisionPpmp->type->label() }}</div></div>
            <div class="col-6 col-md-2"><div class="text-muted">Numbers</div>
                @foreach ($history as $h)
                    <a href="{{ route('procurement.division-ppmp.show', $h) }}" class="badge {{ $h->id === $divisionPpmp->id ? 'bg-dark' : 'bg-light text-dark border' }} text-decoration-none">{{ $h->ppmp_number }}</a>
                @endforeach
            </div>
            <div class="col-6 col-md-3"><div class="text-muted">Prepared by / Submitted by</div><div class="fw-semibold">{{ $prepared?->name_snapshot }} / {{ $submitted?->name_snapshot }}</div></div>
            <div class="col-12 col-md-3 text-md-end"><div class="text-muted">Total Budget</div><div class="fw-bold fs-5">₱ {{ number_format((float) $divisionPpmp->total_budget, 2) }}</div></div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3" style="border-radius: 12px; overflow: hidden;">
    <div class="card-header bg-white pt-3 pb-2 px-4" style="border-bottom: 1px solid #f1f5f9;">
        <h6 class="m-0 fw-bold"><i class="fas fa-sitemap text-muted me-2"></i>Section PPMPs included</h6>
    </div>
    <ul class="list-group list-group-flush small">
        @foreach ($divisionPpmp->ppmps->sortBy(fn ($p) => $p->office->code) as $ppmp)
            <li class="list-group-item px-4 d-flex justify-content-between">
                <span><a href="{{ route('procurement.ppmp.show', $ppmp) }}">{{ $ppmp->ppmp_no }}</a> — {{ $ppmp->office->name }}</span>
                <span class="fw-semibold">₱ {{ number_format((float) $ppmp->total_budget, 2) }}</span>
            </li>
        @endforeach
    </ul>
</div>

<div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
    <div class="card-header bg-white pt-3 pb-2 px-4" style="border-bottom: 1px solid #f1f5f9;">
        <h6 class="m-0 fw-bold"><i class="fas fa-list text-muted me-2"></i>PAPs and Procurement Projects</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle small mb-0">
            <thead class="table-light"><tr><th class="ps-4">Procurement Project</th><th>Mode</th><th>Proc. Period</th><th>Funds</th><th class="text-end pe-4">Estimated Budget</th></tr></thead>
            <tbody>
                @foreach ($paps as $pap)
                    <tr class="table-secondary"><td colspan="4" class="ps-4 fw-bold">PAP CODE: {{ $pap->code }} - {{ $pap->title }} <span class="text-muted fw-normal">({{ $pap->ppmp->office->shortName() }})</span></td>
                        <td class="text-end fw-bold pe-4">{{ number_format($pap->items->sum(fn ($i) => (float) $i->estimated_budget), 2) }}</td></tr>
                    @foreach ($pap->items as $item)
                        <tr>
                            <td class="ps-4">{{ $item->description }}</td>
                            <td title="{{ $item->procurementMode->name }}">{{ $item->procurementMode->code }}</td>
                            <td class="text-nowrap">{{ $item->proc_start->format('M Y') }}–{{ $item->proc_end->format('M Y') }}</td>
                            <td>{{ $item->fundSource->code }}</td>
                            <td class="text-end pe-4">{{ number_format((float) $item->estimated_budget, 2) }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
            <tfoot class="table-light"><tr><td colspan="4" class="text-end fw-bold">TOTAL BUDGET</td><td class="text-end fw-bold pe-4">{{ number_format((float) $divisionPpmp->total_budget, 2) }}</td></tr></tfoot>
        </table>
    </div>
</div>
@endsection
