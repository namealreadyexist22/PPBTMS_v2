{{-- Bidding list: the last step recorded, and the next date coming up. Expects $bidding. --}}
@php
    use App\Enums\BiddingStep;
    $upcoming = match (true) {
        $bidding->bidding_at && ! $bidding->bidding_held_at => ['Public bidding', $bidding->bidding_at],
        $bidding->preproc_at && ! $bidding->preproc_held_at => ['Pre-proc conference', $bidding->preproc_at],
        default => null,
    };
@endphp
<div class="text-nowrap">
    @if ($bidding->stage)
        {{ $bidding->stage->label() }}
    @else
        <span class="text-muted">Putting the lots together</span>
    @endif
</div>
@if ($upcoming && $bidding->status !== \App\Enums\BiddingStatus::Cancelled)
    <div class="text-muted text-nowrap"><i class="far fa-calendar me-1"></i>{{ $upcoming[0] }} {{ $upcoming[1]->format('M d, Y g:i A') }}@if ($upcoming[0] === 'Public bidding' && $bidding->bidding_resets) <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">re-set {{ $bidding->bidding_resets }}×</span>@endif</div>
@endif
