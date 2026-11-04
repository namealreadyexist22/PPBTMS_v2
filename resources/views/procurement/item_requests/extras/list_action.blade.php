{{-- Item request list: View, and while pending Decide (requests to us) or Cancel (ours). Expects $ir, $tab. --}}
<button class="btn btn-sm btn-outline-secondary py-0 js-ir-details" data-url="{{ route('procurement.item-requests.show', $ir) }}">View</button>
@if ($ir->isPending())
    @if ($tab === 'incoming')
        <button class="btn btn-sm btn-primary py-0 js-decide" data-url="{{ route('procurement.item-requests.decide-entry', $ir) }}">Decide</button>
    @else
        <button class="btn btn-sm btn-outline-danger py-0 js-cancel" data-url="{{ route('procurement.item-requests.cancel', $ir) }}" data-no="{{ $ir->request_no }}">Cancel</button>
    @endif
@endif
