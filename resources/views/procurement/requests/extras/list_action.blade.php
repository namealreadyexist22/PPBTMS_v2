{{-- PR / JR list: the status trail, in a modal. Expects $row. --}}
<button type="button" class="btn btn-sm btn-outline-secondary py-0 js-trail" data-url="{{ route('procurement.requests.trail', $row) }}" title="Status trail of {{ $row->title() }}">
    <i class="fas fa-route me-1"></i>Status
</button>
