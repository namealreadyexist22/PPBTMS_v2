{{-- PR Receiving: view the request as printed; while waiting, receive it or return it to the office. Expects $row. --}}
<button type="button" class="btn btn-sm btn-outline-secondary py-0 js-view-request" data-url="{{ route('procurement.receiving.request', $row) }}" data-title="{{ $row->title() }}">View</button>
@if (! $row->isReceived())
    <button type="button" class="btn btn-sm btn-primary py-0 js-receive" data-url="{{ route('procurement.receiving.receive', $row) }}" data-title="{{ $row->title() }}" data-submitted="{{ $row->submitted_at?->format('Y-m-d') }}"><i class="fas fa-inbox me-1"></i>Receive</button>
    <button type="button" class="btn btn-sm btn-link text-danger py-0 px-1 js-return" data-url="{{ route('procurement.receiving.return', $row) }}" data-title="{{ $row->title() }}" title="Return {{ $row->title() }} to the office" aria-label="Return {{ $row->title() }} to the office"><i class="fas fa-undo"></i></button>
@endif
