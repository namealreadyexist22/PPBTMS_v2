@extends('BackEnd.layouts.master')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h5 class="fw-bold mb-0">
            <i class="fas fa-file-invoice-dollar text-muted me-2"></i>Budget Proposal
            <a tabindex="0" role="button" class="text-muted ms-1 fs-6" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="right"
               title="How it works"
               data-bs-content="Each department's offices write their PAP titles here, with their budget lines (MOOE not procured) and performance targets. The department submits it; the evaluator (Budget and Treasury) gives the PAP codes and evaluates it. The PPMPs pick their PAPs from it."><i class="far fa-question-circle"></i></a>
        </h5>
    </div>
    <form method="GET" class="d-flex align-items-center gap-2">
        <label class="small text-muted">Fiscal Year</label>
        <select name="fy" class="form-select form-select-sm" style="width: 110px;" onchange="this.form.submit()">
            @foreach ($years as $year)<option value="{{ $year }}" @selected($year === $fiscalYear)>{{ $year }}</option>@endforeach
        </select>
    </form>
</div>

<div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle small mb-0">
            <thead class="table-light">
                <tr class="text-nowrap">
                    <th class="ps-4">Department</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">PAPs</th>
                    <th class="text-end">Budget lines</th>
                    <th>Submitted</th>
                    <th class="pe-4"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php $d = $row['department']; $p = $row['proposal']; @endphp
                    <tr>
                        <td class="ps-4"><span class="fw-semibold">{{ $d->acronym ?: $d->code }}</span>@if ($d->acronym) <span class="text-muted">—</span> {{ $d->name }}@endif</td>
                        <td class="text-center">
                            @if ($p)<span class="badge {{ $p->status->badge() }}">{{ $p->status->label() }}</span>@else<span class="text-muted">Not started</span>@endif
                        </td>
                        <td class="text-center">{{ $p ? $p->paps_count : '' }}</td>
                        <td class="text-end">{{ $p && $row['lines'] ? number_format($row['lines'] / 100, 2) : '' }}</td>
                        <td class="text-nowrap text-muted">{{ $p?->submitted_at?->format('M d, Y') }}</td>
                        <td class="pe-4 text-end text-nowrap">
                            @if ($p)
                                <a href="{{ route('procurement.budget-proposals.show', $p) }}" class="btn btn-sm btn-outline-primary py-0">
                                    {{ $canEvaluate && $p->status === \App\Enums\BudgetProposalStatus::Submitted ? 'Evaluate' : 'Open' }}
                                </a>
                            @elseif ($row['canOpen'])
                                <button class="btn btn-sm btn-outline-success py-0 btn-open" data-department="{{ $d->id }}"><i class="fas fa-plus me-1"></i> Start</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-5">No departments to show.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('script')
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-bs-toggle="popover"]').forEach((el) => bootstrap.Popover.getOrCreateInstance(el));
    $(document).on('click', '.btn-open', function () {
        const btn = $(this).prop('disabled', true);
        $.post('{{ route("procurement.budget-proposals.open") }}', { _token: '{{ csrf_token() }}', department_id: btn.data('department'), fiscal_year: {{ $fiscalYear }} })
            .done((res) => { window.location.href = res.url; })
            .fail((xhr) => { btn.prop('disabled', false); toastr.error(xhr.responseJSON?.message || 'Could not start it.', 'Error'); });
    });
});
</script>
@endpush
