{{-- Specifications folded away: a header to click (with how many rows), opening to the specs table (procurement.partials.specs).
     Expects $text; optional $title (default "Specifications (TWG)"), $note (small text under the header), $open (start opened). --}}
@php
    $uid = 'specs_' . uniqid();
    $rows = count(\App\Support\Specs::table($text ?? ''));
    $open ??= false;
@endphp
@if (filled($text ?? null))
    <div class="border rounded-3 overflow-hidden specs-collapsible">
        <button type="button" class="btn btn-light w-100 d-flex align-items-center gap-2 text-start rounded-0 border-0 px-3 py-2 small {{ $open ? '' : 'collapsed' }}"
                data-bs-toggle="collapse" data-bs-target="#{{ $uid }}" aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="{{ $uid }}">
            <i class="fas fa-list-alt text-muted"></i>
            <span class="fw-semibold">{{ $title ?? 'Specifications (TWG)' }}</span>
            <span class="text-muted">· {{ $rows }} {{ $rows === 1 ? 'line' : 'lines' }}</span>
            <i class="fas fa-chevron-down ms-auto text-muted specs-chevron"></i>
        </button>
        <div id="{{ $uid }}" class="collapse {{ $open ? 'show' : '' }}">
            <div class="p-2 border-top bg-white">
                @if (! empty($note))<div class="text-muted small mb-2">{{ $note }}</div>@endif
                @include('procurement.partials.specs', ['text' => $text])
            </div>
        </div>
    </div>
    @once
        <style>
            .specs-collapsible .specs-chevron { transition: transform .15s ease; }
            .specs-collapsible [aria-expanded="true"] .specs-chevron { transform: rotate(180deg); }
        </style>
    @endonce
@endif
