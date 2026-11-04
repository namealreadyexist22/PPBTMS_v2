{{-- PR / JR list: the status (Returned when the BAC sent it back). Expects $row. --}}
@if ($row->isReturned())
    <span class="badge bg-danger" title="Returned by the BAC {{ \App\Models\Procurement\PurchaseRequest::RETURN_FOR[$row->return_for] ?? '' }}: {{ $row->return_reason }}">Returned</span>
@else
    <span class="badge bg-{{ $row->status->color() }}">{{ $row->status->label() }}</span>
@endif
