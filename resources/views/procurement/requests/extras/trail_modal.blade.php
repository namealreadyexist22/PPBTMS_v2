{{-- A request's status trail in a modal (from the list and the request's page). Expects $pr, $events, $existing (uuids of the RFQs / AOQs still there). --}}
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
                @include('procurement.requests.extras.trail', ['events' => $events, 'pr' => $pr, 'bare' => true, 'existing' => $existing])
            </div>
            <div class="modal-footer bg-body-tertiary border-0 py-2">
                <a href="{{ route('procurement.requests.show', $pr) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-external-link-alt me-1"></i> Open {{ $pr->kind->short() }}</a>
                <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<script>
// An RFQ / AOQ on the trail: the trail steps aside for its read-only modal and comes back when that closes
(function () {
    $(document).off('click.trailView').on('click.trailView', '.js-trail-view', function (e) {
        e.preventDefault();
        const trail = document.getElementById('REQUEST_TRAIL_MODAL');
        const holder = $(trail).parent();
        const modalId = $(this).data('modal');
        $.get($(this).data('url')).done(function (html) {
            const trailHtml = trail.outerHTML;
            trail.addEventListener('hidden.bs.modal', function () {
                holder.html(html);
                const view = document.getElementById(modalId);
                bootstrap.Modal.getOrCreateInstance(view).show();
                view.addEventListener('hidden.bs.modal', function () {
                    holder.html(trailHtml);
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('REQUEST_TRAIL_MODAL')).show();
                }, { once: true });
            }, { once: true });
            bootstrap.Modal.getInstance(trail).hide();
        }).fail(function () { if (window.toastr) toastr.error('Could not load it.', 'Error'); });
    });
})();
</script>
