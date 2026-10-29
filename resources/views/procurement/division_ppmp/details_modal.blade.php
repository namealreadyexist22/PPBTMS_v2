{{-- A numbered Division PPMP, read-only: its details, the section PPMPs it combines and the projects by PAP.
     Expects $divisionPpmp (with office, ppmps.office), $paps, $canOpenPpmps, $printSignatories. --}}
@php
    $money = fn ($v) => number_format((float) $v, 2);
    $qty = fn ($q) => rtrim(rtrim(number_format((float) $q, 2), '0'), '.');
    $signer = fn ($s) => trim(($s['name'] ?? '') . (! empty($s['position']) ? ', ' . $s['position'] : ''));
    $details = [
        'Fiscal year'  => $divisionPpmp->fiscal_year,
        'Type'         => $divisionPpmp->type->label(),
        'Approved'     => $divisionPpmp->approved_at?->format('M d, Y'),
        'Total budget' => '₱' . $money($divisionPpmp->total_budget),
        'Prepared by'  => $signer($printSignatories['prepared']),
        'Submitted by' => $signer($printSignatories['submitted']),
    ];
@endphp
<div class="modal fade" id="DIVISION_PPMP_DETAILS_MODAL" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl modal-fullscreen-lg-down">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-white py-3">
                <div>
                    <h5 class="modal-title fw-bold mb-0">
                        PPMP No. {{ $divisionPpmp->ppmp_number }}
                        <span class="badge {{ $divisionPpmp->isCurrent() ? 'bg-success' : 'bg-dark' }} align-middle ms-1" style="font-size: .7rem;">{{ $divisionPpmp->isCurrent() ? 'Current' : 'Superseded' }}</span>
                    </h5>
                    <div class="small text-muted">{{ $divisionPpmp->office->label() }} · {{ $divisionPpmp->region->label() }} ({{ $divisionPpmp->region->bac() }})</div>
                </div>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 small">
                <div class="row g-3">
                    @foreach ($details as $label => $value)
                        <div class="col-6 col-md-4 col-lg-2">
                            <div class="text-uppercase text-muted fw-semibold" style="font-size: .68rem; letter-spacing: .4px;">{{ $label }}</div>
                            <div class="fw-semibold">{{ filled($value) ? $value : '—' }}</div>
                        </div>
                    @endforeach
                </div>

                <div class="fw-bold mt-4 mb-2">Section PPMPs included</div>
                <div class="table-responsive border rounded-3">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th class="ps-3">PPMP</th><th>Unit</th><th class="text-end pe-3">Total</th></tr></thead>
                        <tbody>
                            @foreach ($divisionPpmp->ppmps->sortBy(fn ($p) => $p->office->code) as $ppmp)
                                <tr>
                                    <td class="ps-3 text-nowrap">
                                        @if ($canOpenPpmps)<a href="{{ route('procurement.ppmp.show', $ppmp) }}">{{ $ppmp->ppmp_no }}</a>@else{{ $ppmp->ppmp_no }}@endif
                                    </td>
                                    <td>{{ $ppmp->office->name }}</td>
                                    <td class="text-end text-nowrap pe-3">{{ $money($ppmp->total_budget) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="fw-bold mt-4 mb-2">PAPs and procurement projects</div>
                <div class="table-responsive border rounded-3">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr class="text-nowrap"><th class="ps-3">Project</th><th class="text-center">Qty</th><th>Mode</th><th>Schedule</th><th>Fund</th><th class="text-end pe-3">Estimated budget</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($paps as $pap)
                                <tr class="table-secondary">
                                    <td colspan="5" class="ps-3 fw-bold">{{ $pap->code }} — {{ $pap->title }} <span class="text-muted fw-normal">({{ $pap->ppmp->office->shortName() }})</span></td>
                                    <td class="text-end fw-bold text-nowrap pe-3">{{ $money($pap->items->sum(fn ($i) => (float) $i->estimated_budget)) }}</td>
                                </tr>
                                @foreach ($pap->items as $item)
                                    <tr>
                                        <td class="ps-3">
                                            <div class="fw-semibold">{{ $item->description }}</div>
                                            @if ($item->quantity_size)<div class="text-muted text-truncate" style="max-width: 360px;" title="{{ $item->quantity_size }}">{{ $item->quantity_size }}</div>@endif
                                        </td>
                                        <td class="text-center text-nowrap">{{ $qty($item->quantity) }} {{ $item->unit?->name }}</td>
                                        <td>{{ $item->procurementMode?->name ?? '—' }}</td>
                                        <td class="text-nowrap">{{ $item->proc_start?->format('M Y') }}{{ $item->proc_end ? ' – ' . $item->proc_end->format('M Y') : '' }}</td>
                                        <td>{{ $item->fundSource?->code ?? '—' }}</td>
                                        <td class="text-end text-nowrap pe-3">{{ $money($item->estimated_budget) }}</td>
                                    </tr>
                                @endforeach
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-3">No projects.</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="table-light fw-bold">
                                <td colspan="5" class="text-end">Total budget</td>
                                <td class="text-end text-nowrap pe-3">{{ $money($divisionPpmp->total_budget) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-body-tertiary border-0 py-2">
                <a href="{{ route('procurement.division-ppmp.print', $divisionPpmp) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fas fa-print me-1"></i> Print</a>
                <a href="{{ route('procurement.division-ppmp.show', $divisionPpmp) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-external-link-alt me-1"></i> Open page</a>
                <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
