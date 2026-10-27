{{-- An item request in detail: the request (who, what, why), the item's specs as when requested (folded) and the decision
     (approved / declined, by whom, why, into which project).
     Expects $ir (with item.unit, offices, requester, decider, ppmpItem.ppmp), $canDecide, $canCancel. --}}
@php
    use App\Enums\ItemRequestStatus;
    $qty = fn ($q) => $q === null ? '—' : rtrim(rtrim(number_format((float) $q, 2), '0'), '.');
    $unit = $ir->unitName();   // as when requested
    $label = fn ($t) => '<div class="text-uppercase text-muted fw-semibold" style="font-size: .68rem; letter-spacing: .4px;">' . e($t) . '</div>';
    $c = $ir->status->color();
    $decided = $ir->decided_at !== null;
@endphp
<div class="modal fade" id="ITEM_DETAILS_MODAL" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-white py-3">
                <div>
                    <h5 class="modal-title fw-bold mb-0">
                        {{ $ir->request_no }}
                        <span class="badge rounded-pill bg-{{ $c }}-subtle text-{{ $c }}-emphasis border border-{{ $c }}-subtle align-middle" style="font-size: .7rem;">{{ $ir->status->label() }}</span>
                    </h5>
                    <div class="small text-muted">Item request · FY {{ $ir->fiscal_year }} · {{ $ir->requestingOffice->shortName() }} → {{ $ir->procuringOffice->shortName() }}</div>
                </div>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 small">
                {{-- The request --}}
                <div class="row g-3">
                    <div class="col-6 col-sm-6">{!! $label('Requested by') !!}<div class="fw-semibold">{{ $ir->requester?->fullname ?? '—' }}</div><div class="text-muted">{{ $ir->requestingOffice->shortName() }}</div></div>
                    <div class="col-6 col-sm-6">{!! $label('Date') !!}<div class="fw-semibold">{{ $ir->created_at->format('M d, Y g:i A') }}</div></div>
                    <div class="col-12">{!! $label('Item') !!}<div class="fw-semibold">{{ $ir->itemLabel() }}</div>
                        @if ($ir->unit_cost)<div class="text-muted">Standard cost when requested: ₱{{ number_format((float) $ir->unit_cost, 2) }} per {{ $unit ?? 'unit' }}</div>@endif
                    </div>
                    <div class="col-6 col-sm-6">{!! $label('Requested') !!}<div class="fw-semibold fs-6">{{ $qty($ir->quantity) }} <span class="small fw-normal">{{ $unit }}</span></div></div>
                    <div class="col-6 col-sm-6">{!! $label('Approved') !!}<div class="fw-semibold fs-6 {{ $ir->status === ItemRequestStatus::Declined ? 'text-danger' : '' }}">{{ $decided ? $qty($ir->approved_quantity) : '—' }} @if ($decided)<span class="small fw-normal">{{ $unit }}</span>@endif</div></div>
                    <div class="col-12">{!! $label('Purpose') !!}<div style="white-space: pre-line;">{{ $ir->purpose ?: '—' }}</div></div>
                </div>

                {{-- The decision --}}
                <div class="border rounded-3 p-3 mt-4 {{ match ($ir->status) { ItemRequestStatus::Declined => 'border-danger-subtle bg-danger-subtle', ItemRequestStatus::Pending => 'bg-body-tertiary', ItemRequestStatus::Cancelled => 'bg-body-tertiary', default => 'border-success-subtle bg-success-subtle' } }}">
                    @if ($ir->status === ItemRequestStatus::Pending)
                        <div class="fw-semibold"><i class="fas fa-hourglass-half text-warning me-1"></i>Waiting for {{ $ir->procuringOffice->shortName() }}'s decision.</div>
                    @elseif ($ir->status === ItemRequestStatus::Cancelled)
                        <div class="fw-semibold"><i class="fas fa-ban me-1"></i>Cancelled by {{ $ir->requestingOffice->shortName() }} {{ $ir->cancelled_at?->format('M d, Y g:i A') }}.</div>
                    @else
                        <div class="fw-semibold mb-2">
                            @if ($ir->status === ItemRequestStatus::Declined)
                                <i class="fas fa-times-circle text-danger me-1"></i>Declined by {{ $ir->procuringOffice->shortName() }}
                            @elseif ($ir->status === ItemRequestStatus::Partial)
                                <i class="fas fa-adjust text-info me-1"></i>{{ $qty($ir->approved_quantity) }} of {{ $qty($ir->quantity) }} approved by {{ $ir->procuringOffice->shortName() }}
                            @else
                                <i class="fas fa-check-circle text-success me-1"></i>Approved in full by {{ $ir->procuringOffice->shortName() }}
                            @endif
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                {!! $label('Decided by') !!}
                                <div>{{ $ir->decider?->fullname ?? '—' }}</div>
                                <div class="text-muted">{{ $ir->decided_at?->format('M d, Y g:i A') }}</div>
                            </div>
                            <div class="col-md-8">{!! $label($ir->status === ItemRequestStatus::Declined ? 'Reason' : 'Remarks') !!}<div style="white-space: pre-line;">{{ $ir->decision_remarks ?: '—' }}</div></div>
                            @if ($ir->ppmpItem)
                                <div class="col-12">{!! $label('Added to the distribution of') !!}
                                    <div><i class="fas fa-diagram-project me-1"></i>{{ $ir->ppmpItem->description }} · PPMP {{ $ir->ppmpItem->ppmp?->ppmp_no }}</div>
                                    <div class="text-muted">{{ $ir->requestingOffice->shortName() }}'s share went up by {{ $qty($ir->approved_quantity) }} {{ $unit }}.</div>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- The specs as when requested (kept with the request); the catalog's current ones too if the TWG changed them since --}}
                @if ($ir->specifications || $ir->specsChanged())
                    <div class="mt-4 d-grid gap-2">
                        @include('procurement.partials.specs_collapsible', [
                            'text' => $ir->specifications, 'title' => 'Specifications (TWG) when requested',
                            'note' => 'As set by the TWG on ' . $ir->created_at->format('M d, Y') . ', when the request was made.',
                        ])
                        @if ($ir->specsChanged())
                            <div class="text-warning-emphasis"><i class="fas fa-info-circle me-1"></i>The TWG has changed this item's specifications since the request.</div>
                            @include('procurement.partials.specs_collapsible', ['text' => $ir->item->specifications, 'title' => 'Current specifications (TWG)'])
                        @endif
                    </div>
                @endif
            </div>
            <div class="modal-footer bg-body-tertiary border-0 py-2">
                @if ($canCancel)
                    <button type="button" class="btn btn-sm btn-outline-danger me-auto js-then" data-then="js-cancel" data-url="{{ route('procurement.item-requests.cancel', $ir) }}" data-no="{{ $ir->request_no }}">Cancel request</button>
                @endif
                <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Close</button>
                @if ($canDecide)
                    <button type="button" class="btn btn-sm btn-primary px-3 js-then" data-then="js-decide" data-url="{{ route('procurement.item-requests.decide-entry', $ir) }}"><i class="fas fa-reply me-1"></i> Decide</button>
                @endif
            </div>
        </div>
    </div>
</div>
