{{-- PR / JR list: the number, opening the request. Expects $row. --}}
<a href="{{ route('procurement.requests.show', $row) }}" class="text-decoration-none" title="Open {{ $row->title() }}">
    <i class="fas fa-file-invoice me-1 small"></i>{{ $row->request_no ?? 'Draft' }}
</a>
@if ($row->revision) <span class="badge bg-info text-dark">Rev. {{ $row->revision }}</span>@endif
