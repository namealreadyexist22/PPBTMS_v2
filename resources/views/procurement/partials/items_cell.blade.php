{{-- A list's Items cell: up to 5 items listed; 6 or more: hover the count to see them all.
     Expects $lines (each with description, quantity, unit). The list's script starts the .js-items-popover popovers on each draw. --}}
@php
    $itemText = fn ($l) => e($l->description) . ' <span class="text-muted">× ' . e(rtrim(rtrim(number_format((float) $l->quantity, 2), '0'), '.') . ' ' . $l->unit) . '</span>';
@endphp
<div style="min-width: 220px;">
    @if ($lines->isEmpty())
        <span class="text-muted">No items yet</span>
    @elseif ($lines->count() <= 5)
        <ul class="list-unstyled mb-0">
            @foreach ($lines as $l)<li class="text-truncate" style="max-width: 360px;">{!! $itemText($l) !!}</li>@endforeach
        </ul>
    @else
        <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis border border-primary-subtle js-items-popover" tabindex="0"
              data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="right" data-bs-html="true"
              data-bs-title="{{ $lines->count() }} items"
              data-bs-content="{{ '<ul class="ps-3 mb-0 small">' . $lines->map(fn ($l) => '<li>' . $itemText($l) . '</li>')->implode('') . '</ul>' }}">
            <i class="fas fa-list me-1"></i>{{ $lines->count() }} items
        </span>
        <span class="text-muted small ms-1 text-truncate d-inline-block align-middle" style="max-width: 240px;">{{ $lines->take(2)->pluck('description')->implode(', ') }}, …</span>
    @endif
</div>
