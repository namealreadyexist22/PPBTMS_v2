{{-- An AOQ, read-only in a modal (from a request's status trail): the suppliers' quotations, the TWG's findings and the award.
     Expects $aoq (with rfq.items.line.request, suppliers.quotes, evaluator), $rows (AoqService::evaluate), $lot (lotStanding or null),
     $mine (ids of the request and its revisions: their items are marked), $canOpen (may open the AOQ page). --}}
@php
    use App\Enums\AoqStatus;
    use App\Support\Money;
    $qty = fn ($q) => rtrim(rtrim(number_format((float) $q, 2), '0'), '.');
    $cents = fn (?int $c) => $c === null ? '—' : Money::format(Money::fromCents($c));
    $rfq = $aoq->rfq;
    $suppliers = $aoq->suppliers;
    $awarded = $aoq->status === AoqStatus::Awarded;
    // The ranking and the award once the TWG has evaluated
    $ranked = $awarded || $aoq->status === AoqStatus::Evaluated;
    $c = $aoq->status->color();
    $details = [
        'RFQ'           => $rfq->title(),
        'Award'         => $rfq->award_basis?->label(),
        'Opened'        => $aoq->opened_at?->format('M d, Y g:i A'),
        'TWG evaluator' => $aoq->evaluated_by_name,
        'Evaluated'     => $aoq->evaluated_at?->format('M d, Y g:i A'),
        'Awarded'       => $aoq->awarded_at?->format('M d, Y g:i A'),
    ];
@endphp
<div class="modal fade" id="AOQ_DETAILS_MODAL" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl modal-fullscreen-lg-down">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-white py-3">
                <div>
                    <h5 class="modal-title fw-bold mb-0">
                        {{ $aoq->title() }}
                        <span class="badge rounded-pill bg-{{ $c }}-subtle text-{{ $c }}-emphasis border border-{{ $c }}-subtle align-middle" style="font-size: .7rem;">{{ $aoq->status->label() }}</span>
                    </h5>
                    <div class="small text-muted">Abstract of Quotations · {{ $rfq->label() }} · {{ $rfq->procurementMode?->name ?? '—' }}</div>
                </div>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 small">
                <div class="row g-3">
                    @foreach ($details as $label => $value)
                        <div class="col-6 col-md-4 col-xl-2">
                            <div class="text-uppercase text-muted fw-semibold" style="font-size: .68rem; letter-spacing: .4px;">{{ $label }}</div>
                            <div class="fw-semibold">{{ filled($value) ? $value : '—' }}</div>
                        </div>
                    @endforeach
                    @if ($aoq->technical_comment)
                        <div class="col-12">
                            <div class="text-uppercase text-muted fw-semibold" style="font-size: .68rem; letter-spacing: .4px;">Technical comment (TWG)</div>
                            <div>{{ $aoq->technical_comment }}</div>
                        </div>
                    @endif
                </div>

                @if ($suppliers->isEmpty())
                    <div class="text-muted text-center border rounded-3 py-4 mt-4">No quotations recorded yet.</div>
                @else
                    <div class="table-responsive border rounded-3 mt-4">
                        <table class="table table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3" style="width: 36px;">#</th>
                                    <th style="min-width: 200px;">Item</th>
                                    <th class="text-end text-nowrap">ABC</th>
                                    @foreach ($suppliers as $s)
                                        <th style="min-width: 150px;">
                                            <div class="fw-bold">{{ $s->name }}</div>
                                            @if ($s->address)<div class="fw-normal text-muted">{{ $s->address }}</div>@endif
                                            @unless ($s->is_responsive)<span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle">Not responsive</span>@endunless
                                        </th>
                                    @endforeach
                                    @if ($ranked)<th style="min-width: 140px;">Award</th>@endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $i => $row)
                                    @php $line = $row['item']->line; $isMine = in_array($line->purchase_request_id, $mine, true); @endphp
                                    <tr>
                                        <td class="ps-3 text-muted">{{ $i + 1 }}</td>
                                        <td>
                                            <div class="fw-semibold">{{ $line->description }}</div>
                                            <div class="text-muted">{{ $qty($line->quantity) }} {{ $line->unit }}</div>
                                            @if ($isMine)<span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">this request</span>@endif
                                        </td>
                                        <td class="text-end text-nowrap">{{ $cents($row['abc_cents']) }}</td>
                                        @foreach ($suppliers as $s)
                                            @php $q = $row['quotes'][$s->id]; $won = $row['awarded'] === $s->id; @endphp
                                            <td class="{{ $won ? 'bg-success-subtle' : '' }}">
                                                @if ($q['issue'] === 'No quote')
                                                    <span class="text-muted">No quote</span>
                                                @else
                                                    <div class="d-flex justify-content-between gap-2">
                                                        <span class="text-muted">@ {{ number_format((float) $q['quote']->unit_price, 2) }}</span>
                                                        <span class="fw-semibold text-nowrap">{{ $cents($q['total_cents']) }}</span>
                                                    </div>
                                                    @if ($q['quote']->brand)<div class="text-muted">{{ $q['quote']->brand }}</div>@endif
                                                    @if ($won)
                                                        <span class="badge bg-success"><i class="fas fa-trophy me-1"></i>Awarded</span>
                                                    @elseif ($ranked && $q['issue'])
                                                        <span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle">{{ $q['issue'] }}</span>
                                                    @endif
                                                    @if ($q['quote']->is_compliant === true)
                                                        <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">Meets specs</span>
                                                    @elseif ($q['quote']->is_compliant === false)
                                                        <span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle">Does not meet specs</span>
                                                    @endif
                                                    @if ($q['quote']->twg_remarks)<div class="fst-italic text-muted mt-1"><i class="fas fa-comment-dots me-1"></i>{{ $q['quote']->twg_remarks }}</div>@endif
                                                @endif
                                            </td>
                                        @endforeach
                                        @if ($ranked)
                                            <td>
                                                @if ($row['awarded'])
                                                    <span class="fw-semibold">{{ $suppliers->firstWhere('id', $row['awarded'])->name }}</span>
                                                @elseif ($row['lowest'] === [])
                                                    <span class="text-danger"><i class="fas fa-times-circle me-1"></i>Failed</span>
                                                @elseif (! $awarded)
                                                    <span class="text-muted">To be awarded</span>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                            @if ($lot && $ranked)
                                <tfoot>
                                    <tr class="table-light fw-bold">
                                        <td colspan="3" class="text-end">Lot total</td>
                                        @foreach ($suppliers as $s)
                                            @php $st = $lot['suppliers'][$s->id]; @endphp
                                            <td class="text-end text-nowrap">{{ $cents($st['total_cents']) }}@if ($st['issue'])<div class="fw-normal"><span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle">{{ $st['issue'] }}</span></div>@endif</td>
                                        @endforeach
                                        <td></td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                    @unless ($ranked)
                        <div class="text-muted mt-2"><i class="fas fa-info-circle me-1"></i>The ranking and the award are shown once the TWG has evaluated the offers.</div>
                    @endunless
                @endif
            </div>
            <div class="modal-footer bg-body-tertiary border-0 py-2">
                @if ($canOpen)
                    <a href="{{ route('procurement.aoqs.show', $aoq) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-external-link-alt me-1"></i> Open AOQ page</a>
                @endif
                <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
