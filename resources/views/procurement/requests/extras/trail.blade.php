{{-- Status trail of the request (and the revisions before it), latest first: through the RFQ, AOQ, TWG and award.
     Expects $events (PurchaseRequestEvent with user, request), $pr; $bare (optional): the list only, without the card (modal). --}}
@php
    // The RFQ / AOQ of a step opens read-only in a modal, for anyone who can view the request: [url, modal id]
    $existing ??= null;   // uuids of the RFQs / AOQs still there (null: not checked)
    $view = function (?string $url) use ($pr, $existing) {
        if ($url && preg_match('#/(rfqs|aoqs)/([0-9a-f-]{36})#', $url, $m) && ($existing === null || in_array($m[2], $existing, true))) {
            return $m[1] === 'rfqs'
                ? [route('procurement.requests.rfq-view', [$pr, $m[2]]), 'RFQ_DETAILS_MODAL']
                : [route('procurement.requests.aoq-view', [$pr, $m[2]]), 'AOQ_DETAILS_MODAL'];
        }

        return null;
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
                                @if ($target = $view($event->url))
                                    <a href="#" class="text-decoration-none js-trail-view" data-url="{{ $target[0] }}" data-modal="{{ $target[1] }}" title="View">{{ $event->description }} <i class="fas fa-eye ms-1" style="font-size: .7rem;"></i></a>
                                @else
                                    {{ $event->description }}
                                @endif
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
