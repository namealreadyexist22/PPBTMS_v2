{{-- AOQs on the TWG page. Expects $aoqs, $empty. --}}
<div class="table-responsive">
    <table class="table table-sm table-hover align-middle small mb-0">
        <thead class="table-light">
            <tr class="text-nowrap"><th class="ps-4">AOQ</th><th>Items</th><th>Mode</th><th class="text-center">Suppliers</th><th>Evaluators</th><th>Sent</th><th class="text-center">Status</th><th class="pe-4"></th></tr>
        </thead>
        <tbody>
            @forelse ($aoqs as $aoq)
                <tr>
                    <td class="ps-4 fw-semibold text-nowrap">{{ $aoq->title() }}</td>
                    <td>{{ $aoq->rfq->label() }}</td>
                    <td>{{ $aoq->rfq->procurementMode?->name ?? '—' }}</td>
                    <td class="text-center">{{ $aoq->suppliers->count() }}</td>
                    <td>{{ $aoq->evaluators->pluck('fullname')->implode(', ') }}</td>
                    <td class="text-nowrap">{{ $aoq->sent_to_twg_at?->format('M d, Y g:i A') }}</td>
                    <td class="text-center"><span class="badge bg-{{ $aoq->status->color() }}">{{ $aoq->status->label() }}</span></td>
                    <td class="pe-4 text-end"><a href="{{ route('procurement.twg.show', $aoq) }}" class="btn btn-sm {{ $aoq->status === \App\Enums\AoqStatus::ForEvaluation ? 'btn-primary' : 'btn-outline-secondary' }} py-0">{{ $aoq->status === \App\Enums\AoqStatus::ForEvaluation ? 'Evaluate' : 'View' }}</a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">{{ $empty }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
