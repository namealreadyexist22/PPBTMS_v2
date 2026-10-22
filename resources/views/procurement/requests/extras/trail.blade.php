{{-- Status trail of the request (and the revisions before it), latest first: through the RFQ, AOQ, TWG and award.
     Expects $events (PurchaseRequestEvent with user, request), $pr; $bare (optional): the list only, without the card (modal). --}}
@php
    $user = auth()->user();
    // A link only to a page the viewer can open
    $link = fn ($url) => match (true) {
        ! $url                              => null,
        str_contains($url, '/aoqs/')        => $user->canAccessPermission(\App\Models\Procurement\Aoq::MANAGE_PERMISSION) ? $url : null,
        str_contains($url, '/rfqs/')        => $user->canAccessPermission(\App\Models\Procurement\Rfq::MANAGE_PERMISSION) ? $url : null,
        default                             => null,
    };
    $latest = $events->last();
    $bare ??= false;
@endphp
<style>
    .trail { list-style: none; margin: 0; padding: 0; position: relative; }
    .trail::before { content: ''; position: absolute; left: 15px; top: 6px; bottom: 6px; width: 2px; background: var(--bs-border-color); }
    .trail li { position: relative; padding: 0 0 1rem 44px; }
    .trail li:last-child { padding-bottom: 0; }
    .trail .dot { position: absolute; left: 0; top: 0; width: 32px; height: 32px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: .8rem; border: 2px solid var(--bs-body-bg); }
</style>
@unless ($bare)
<div class="card border-0 shadow-sm pr-card overflow-hidden mb-3" id="pr_trail">
    <div class="card-header bg-white py-3 px-4 d-flex flex-wrap align-items-center gap-2" style="border-bottom: 1px solid #f1f5f9;">
        <h6 class="m-0 fw-bold"><i class="fas fa-route text-muted me-2"></i>Status Trail</h6>
        @if ($latest)
            <span class="small text-muted ms-md-2">Now: <span class="fw-semibold text-body">{{ $latest->description }}</span></span>
        @endif
    </div>
    <div class="card-body p-4">
@endunless
        @if ($events->isEmpty())
            <div class="text-muted small">Nothing recorded yet.</div>
        @else
            <ul class="trail">
                @foreach ($events->reverse() as $event)
                    <li>
                        <span class="dot bg-{{ $event->color() }}-subtle text-{{ $event->color() }}-emphasis"><i class="{{ $event->icon() }}"></i></span>
                        <div class="small">
                            <div class="fw-semibold">
                                @if ($url = $link($event->url))<a href="{{ $url }}" class="text-decoration-none">{{ $event->description }}</a>@else{{ $event->description }}@endif
                            </div>
                            @if ($event->remarks)<div class="fst-italic text-body-secondary">"{{ $event->remarks }}"</div>@endif
                            <div class="text-muted">
                                {{ $event->created_at?->format('M d, Y g:i A') }}
                                @if ($event->user) · {{ $event->user->fullname }}@endif
                                @if ($event->purchase_request_id !== $pr->id) · {{ $event->request->title() }}@endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
@unless ($bare)
    </div>
</div>
@endunless
