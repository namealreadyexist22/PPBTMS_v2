{{-- Latest steps on the PR / JR status trails the user can see. Expects $activity (PurchaseRequestEvent). --}}
<div class="card dash-card h-100">
    <div class="card-header"><h5 class="card-title"><i class="fas fa-history text-muted me-2"></i>Recent activity</h5></div>
    <div class="card-body pt-2">
        @forelse ($activity as $e)
            <div class="dash-row">
                <span class="d-flex align-items-start gap-2">
                    <i class="{{ $e->icon() }} text-{{ $e->color() }} mt-1" style="width: 14px;"></i>
                    <span>
                        <span class="fw-semibold">{{ $e->request?->title() }}</span>
                        <span class="text-muted">· {{ $e->description }}</span>
                        @if ($e->user)
                            <span class="d-block text-muted" style="font-size: .75rem;">{{ $e->user->fullname }}</span>
                        @endif
                    </span>
                </span>
                <span class="text-muted text-nowrap" style="font-size: .75rem;">{{ $e->created_at?->diffForHumans(short: true) }}</span>
            </div>
        @empty
            <p class="dash-empty mb-0">No PR / JR activity yet.</p>
        @endforelse
    </div>
</div>
