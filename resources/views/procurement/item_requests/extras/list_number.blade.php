{{-- Item request list: the number, opening its details (purpose, specs, the decision) in a modal. Expects $ir. --}}
<a href="#" class="fw-semibold text-decoration-none js-ir-details" data-url="{{ route('procurement.item-requests.show', $ir) }}" title="View the details">{{ $ir->request_no }} <i class="fas fa-eye ms-1" style="font-size: .7rem;"></i></a>
