{{-- A request's status trail in a modal (from the list). Expects $pr, $events. --}}
<div class="modal fade" id="REQUEST_TRAIL_MODAL" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-white py-3">
                <div>
                    <h5 class="modal-title fw-bold mb-0"><i class="fas fa-route text-muted me-2"></i>Status Trail</h5>
                    <div class="small text-muted">
                        {{ $pr->title() }} · <span class="badge bg-{{ $pr->status->color() }}">{{ $pr->status->label() }}</span>
                        @if ($events->isNotEmpty()) · Now: <span class="fw-semibold text-body">{{ $events->last()->description }}</span>@endif
                    </div>
                </div>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                @include('procurement.requests.extras.trail', ['events' => $events, 'pr' => $pr, 'bare' => true])
            </div>
            <div class="modal-footer bg-body-tertiary border-0 py-2">
                <a href="{{ route('procurement.requests.show', $pr) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-external-link-alt me-1"></i> Open {{ $pr->kind->short() }}</a>
                <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
