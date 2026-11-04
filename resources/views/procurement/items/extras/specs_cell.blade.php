{{-- Standard Items list: the specifications cell (View specs button + TWG reference). Expects $item. --}}
@php $twg = trim($item->twg_reference . ($item->twg_approved_at ? ' · ' . $item->twg_approved_at->format('M d, Y') : ''), ' ·'); @endphp
@if ($item->specifications)
    <button type="button" class="btn btn-sm btn-outline-secondary py-0 btn-view-specs"
            data-title="{{ $item->code }} — {{ $item->name }}" data-twg="{{ $twg }}"
            data-html="{{ view('procurement.partials.specs', ['text' => $item->specifications])->render() }}">
        <i class="fas fa-eye me-1"></i>View specs
    </button>
@else
    <span class="text-muted">—</span>
@endif
@if ($twg)<div class="text-muted mt-1 text-nowrap"><i class="fas fa-stamp me-1"></i>{{ $twg }}</div>@endif
