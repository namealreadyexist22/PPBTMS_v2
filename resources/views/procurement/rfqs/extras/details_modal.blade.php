{{-- An RFQ, read-only in a modal (from the AOQ and the PR / JR pages): its terms and items. Expects $rfq (with procurementMode, items.line.request), $canOpen (may open the RFQ page). --}}
@php
    $qty = fn ($q) => rtrim(rtrim(number_format((float) $q, 2), '0'), '.');
    $days = fn ($v) => is_numeric($v) ? (int) $v . ' calendar days' : ($v ?: '—');
    $requests = $rfq->requests();
    $terms = [
        'Procurement mode'      => $rfq->procurementMode?->name,
        'Award'                 => $rfq->award_basis?->label(),
        'Issued'                => $rfq->issued_at?->format('M d, Y g:i A'),
        'Quotations due'        => $rfq->submission_deadline?->format('M d, Y g:i A'),
        'Delivery place'        => $rfq->delivery_place,
        'Delivery period'       => $days($rfq->delivery_period),
        'Price validity'        => $days($rfq->price_validity),
        'Warranty'              => $rfq->warranty,
        'Payment terms'         => $rfq->payment_terms,
        'Signed by'             => trim($rfq->signatory_name . ($rfq->signatory_designation ? ', ' . $rfq->signatory_designation : '')),
        'Canvassed by'          => $rfq->canvassed_by_name,
    ];
@endphp
<div class="modal fade" id="RFQ_DETAILS_MODAL" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl modal-fullscreen-lg-down">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-white py-3">
                <div>
                    <h5 class="modal-title fw-bold mb-0">
                        {{ $rfq->title() }}
                        <span class="badge rounded-pill bg-{{ $rfq->status->color() }}-subtle text-{{ $rfq->status->color() }}-emphasis border border-{{ $rfq->status->color() }}-subtle align-middle" style="font-size: .7rem;">{{ $rfq->status->label() }}</span>
                    </h5>
                    <div class="small text-muted">{{ $rfq->label() }} · {{ $rfq->region->label() }}</div>
                </div>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 small">
                <div class="row g-3">
                    @foreach ($terms as $label => $value)
                        <div class="col-6 col-md-4 col-xl-3">
                            <div class="text-uppercase text-muted fw-semibold" style="font-size: .68rem; letter-spacing: .4px;">{{ $label }}</div>
                            <div class="fw-semibold">{{ filled($value) ? $value : '—' }}</div>
                        </div>
                    @endforeach
                    <div class="col-12">
                        <div class="text-uppercase text-muted fw-semibold" style="font-size: .68rem; letter-spacing: .4px;">Purchase requests</div>
                        <div class="fw-semibold">
                            @forelse ($requests as $pr)
                                {{ $pr->title() }} <span class="text-muted fw-normal">({{ $pr->requested_by_name ?: '—' }})</span>@unless ($loop->last) · @endunless
                            @empty
                                —
                            @endforelse
                        </div>
                    </div>
                    @if ($rfq->remarks)
                        <div class="col-12">
                            <div class="text-uppercase text-muted fw-semibold" style="font-size: .68rem; letter-spacing: .4px;">Remarks</div>
                            <div style="white-space: pre-line;">{{ $rfq->remarks }}</div>
                        </div>
                    @endif
                </div>

                <div class="table-responsive border rounded-3 mt-4">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr class="text-nowrap"><th class="ps-3" style="width: 40px;">#</th><th>Item &amp; description</th><th class="text-center">Qty</th><th>Unit</th><th class="text-end">Unit cost</th><th class="text-end pe-3">ABC</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($rfq->items as $i => $item)
                                @php $line = $item->line; @endphp
                                <tr>
                                    <td class="ps-3 text-muted align-top">{{ $i + 1 }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $line->description }}</div>
                                        @if ($line->specifications)<div class="mt-1">@include('procurement.partials.specs_collapsible', ['text' => $line->specifications, 'title' => 'Specifications'])</div>@endif
                                        <div class="text-muted">{{ $line->request?->title() }}</div>
                                    </td>
                                    <td class="text-center align-top">{{ $qty($line->quantity) }}</td>
                                    <td class="align-top">{{ $line->unit }}</td>
                                    <td class="text-end text-nowrap align-top">{{ number_format((float) $line->unit_cost, 2) }}</td>
                                    <td class="text-end text-nowrap align-top pe-3">{{ number_format((float) $line->total_cost, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="table-light fw-bold"><td colspan="5" class="text-end">Total ABC</td><td class="text-end text-nowrap pe-3">{{ number_format((float) $rfq->total_abc, 2) }}</td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-body-tertiary border-0 py-2">
                @if ($canOpen)
                    <a href="{{ route('procurement.rfqs.print', $rfq) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fas fa-print me-1"></i> Print RFQ</a>
                    <a href="{{ route('procurement.rfqs.show', $rfq) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-external-link-alt me-1"></i> Open RFQ page</a>
                @endif
                <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
