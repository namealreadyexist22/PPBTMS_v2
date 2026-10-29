@extends('BackEnd.layouts.master')

@section('content')
<style>
    /* A unit's row opens its PPMP */
    tr.ppmp-row { cursor: pointer; }
    tr.ppmp-row:hover > td { background-color: var(--bs-primary-bg-subtle); }
    tr.ppmp-row:focus-visible { outline: 2px solid var(--bs-primary); outline-offset: -2px; }
</style>
@php
    $statusColors = ['draft' => 'secondary', 'submitted' => 'primary', 'returned' => 'warning', 'approved' => 'success', 'superseded' => 'dark'];
@endphp

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h5 class="fw-bold mb-0"><i class="fas fa-layer-group text-muted me-2"></i>Division PPMP</h5>
        <div class="text-muted small">Grouped by department. Each card is one approver: its head approves the PPMPs of the units listed in it as PPMP No. 1, 2, 3… for BAC.</div>
    </div>
    <form method="GET" class="d-flex align-items-center gap-2">
        <label class="small text-muted">Fiscal Year</label>
        <select name="fy" class="form-select form-select-sm" style="width: 110px;" onchange="this.form.submit()">
            @foreach ($years as $year)
                <option value="{{ $year }}" @selected($year === $fiscalYear)>{{ $year }}</option>
            @endforeach
        </select>
    </form>
</div>

@php $lastDepartment = false; @endphp
@forelse ($divisions as $d)
    @php $office = $d['office']; $current = $d['current']; $region = $d['region']; $key = $office->id . '-' . $region->value; $dept = $d['department']; @endphp
    @if ($lastDepartment === false || $lastDepartment?->id !== $dept?->id)
        <div class="d-flex align-items-center gap-2 mt-4 mb-2">
            <span class="badge bg-dark">Department</span>
            @if ($dept)
                <span class="fw-bold">{{ $dept->acronym ?: $dept->code }}</span><span class="text-muted">— {{ $dept->name }}</span>
            @else
                <span class="text-muted">Not under a department</span>
            @endif
        </div>
        @php $lastDepartment = $dept; @endphp
    @endif
    <div class="card border-0 shadow-sm mb-3 {{ $dept && $dept->id !== $office->id ? 'ms-md-4' : '' }}" style="border-radius: 12px;">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <div class="small text-muted">{{ $office->typeLabel() }}{{ $office->code ? ' · ' . $office->code : '' }}{{ $office->acronym ? ' · ' . $office->acronym : '' }}</div>
                    <h6 class="fw-bold mb-1">
                        {{ $office->name }}
                        <span class="badge {{ $region === \App\Enums\Region::Vis ? 'bg-info' : 'bg-primary' }} align-middle ms-1" title="Goes to the {{ $region->bac() }}">{{ $region->label() }}</span>
                    </h6>
                    <div class="small">
                        Head: <strong>{{ $office->head?->fullname ?? 'Not set' }}</strong>
                        <span class="mx-2 text-muted">|</span>
                        @if ($current)
                            Current:
                            <button type="button" class="btn btn-sm btn-outline-primary fw-semibold py-0 px-2 align-baseline btn-division-ppmp" data-url="{{ route('procurement.division-ppmp.details', $current) }}">
                                <i class="fas fa-eye me-1"></i>PPMP No. {{ $current->ppmp_number }}
                            </button>
                            ({{ $current->type->label() }}, approved {{ $current->approved_at->format('M d, Y') }}) — ₱ {{ number_format((float) $current->total_budget, 2) }}
                        @else
                            <span class="text-muted">No approved PPMP yet</span>
                        @endif
                    </div>
                    @if ($d['history']->count() > 1)
                        <div class="small mt-1 text-muted">History:
                            @foreach ($d['history'] as $h)
                                <button type="button" class="badge border-0 {{ $h->isCurrent() ? 'bg-success' : 'bg-light text-dark border' }} btn-division-ppmp" data-url="{{ route('procurement.division-ppmp.details', $h) }}">No. {{ $h->ppmp_number }}</button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="d-flex flex-wrap gap-2">
                    @if ($current)
                        <a href="{{ route('procurement.division-ppmp.print', $current) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fas fa-print me-1"></i> Print No. {{ $current->ppmp_number }}</a>
                    @endif
                    @if ($d['pending']->isNotEmpty())
                        <a href="{{ route('procurement.division-ppmp.preview', [$office, 'fy' => $fiscalYear, 'region' => $region->value]) }}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye me-1"></i> Preview No. {{ ($current?->ppmp_number ?? 0) + 1 }}</a>
                        @if ($d['isHead'])
                            <button class="btn btn-sm btn-success btn-approve-division" data-target="{{ $key }}"><i class="fas fa-check me-1"></i> Approve {{ $d['pending']->count() }} submitted</button>
                        @endif
                    @endif
                </div>
            </div>

            <div class="table-responsive mt-3">
                <table class="table table-sm align-middle small mb-0">
                    <thead class="table-light">
                        <tr><th class="ps-3">Unit</th><th>PPMP</th><th class="text-center">Status</th><th class="text-end pe-3">Total</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($d['units'] as $unit)
                            @php $ppmp = $d['sections']->firstWhere('office_id', $unit->id); $opens = $ppmp && $canOpenPpmps; @endphp
                            {{-- A unit's row opens its PPMP --}}
                            <tr @class(['text-muted' => ! $ppmp, 'ppmp-row' => $opens])
                                @if ($opens) data-url="{{ route('procurement.ppmp.show', $ppmp) }}" role="link" tabindex="0" title="Open PPMP {{ $ppmp->ppmp_no }}" @endif>
                                <td class="ps-3">
                                    <span class="badge bg-light text-dark border me-1" style="min-width: 62px;">{{ $unit->typeLabel() }}</span>
                                    {{ $unit->code }} — {{ $unit->acronym ? $unit->acronym . ' · ' : '' }}{{ $unit->name }}
                                </td>
                                @if ($ppmp)
                                    <td>{{ $ppmp->ppmp_no }}</td>
                                    <td class="text-center"><span class="badge bg-{{ $statusColors[$ppmp->status->value] ?? 'secondary' }}">{{ $ppmp->status->label() }}</span></td>
                                    <td class="text-end pe-3">{{ number_format((float) $ppmp->total_budget, 2) }}</td>
                                @else
                                    <td colspan="3" class="fst-italic">No PPMP for FY {{ $fiscalYear }} yet</td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">No units with an office number report to this head.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Approve modal for this division --}}
    @if ($d['isHead'] && $d['pending']->isNotEmpty())
        <div class="modal fade" id="approve-modal-{{ $key }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content border-0 shadow form-approve-division">
                    <input type="hidden" name="office_id" value="{{ $office->id }}">
                    <input type="hidden" name="fiscal_year" value="{{ $fiscalYear }}">
                    <input type="hidden" name="region" value="{{ $region->value }}">
                    <div class="modal-header bg-light py-3">
                        <h5 class="modal-title fw-bold"><i class="fas fa-check text-success me-2"></i>Approve PPMP No. {{ ($current?->ppmp_number ?? 0) + 1 }} ({{ $region->short() }})</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4 small">
                        <p class="mb-2">These submitted section PPMPs will be approved and combined:</p>
                        <ul class="mb-3">
                            @foreach ($d['pending'] as $ppmp)
                                <li>{{ $ppmp->office->name }} — {{ $ppmp->ppmp_no }} (₱ {{ number_format((float) $ppmp->total_budget, 2) }})</li>
                            @endforeach
                        </ul>
                        @php $notReady = $d['sections']->filter(fn ($p) => in_array($p->status->value, ['draft', 'returned'])); @endphp
                        @if ($notReady->isNotEmpty())
                            <div class="alert alert-warning py-2">
                                Not yet submitted (will not be included): {{ $notReady->map(fn ($p) => $p->office->name)->join(', ') }}
                            </div>
                        @endif
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold text-muted mb-1">Type</label>
                                <select name="type" class="form-select form-select-sm">
                                    {{-- Defaults to Indicative when the submitted section PPMPs are indicative --}}
                                    @php $defaultType = $d['sections']->where('status', \App\Enums\PpmpStatus::Submitted)->contains(fn ($p) => $p->type === \App\Enums\PpmpType::Indicative) ? \App\Enums\PpmpType::Indicative : \App\Enums\PpmpType::Final; @endphp
                                    @foreach ($types as $type)
                                        <option value="{{ $type->value }}" @selected($type === $defaultType)>{{ $type->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold text-muted mb-1">Prepared by (on the form)</label>
                                @php $lastPrepared = $current?->latestSignatory('prepared')?->user_id; @endphp
                                <select name="prepared_by_id" class="form-select form-select-sm" required>
                                    @foreach ($d['members'] as $member)
                                        <option value="{{ $member->id }}" @selected($member->id === $lastPrepared)>{{ $member->fullname }}{{ $member->designation ? ' — ' . $member->designation : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold text-muted mb-1">Remarks (optional)</label>
                                <textarea name="remarks" class="form-control form-control-sm" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-sm btn-success px-3"><i class="fas fa-check me-1"></i> Approve</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@empty
    <div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5">
        <i class="fas fa-inbox fa-2x mb-2 d-block opacity-50"></i>
        Nothing to show for FY {{ $fiscalYear }}. Divisions appear here once a section under them is set up in Offices.
    </div></div>
@endforelse
<div id="modal-body"></div>
@endsection

@push('script')
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function () {
    // A unit's row opens its PPMP (Ctrl / middle click or Ctrl+Enter: in a new tab); Enter opens the focused row
    function openRow(row, newTab) {
        newTab ? window.open(row.dataset.url, '_blank') : (window.location.href = row.dataset.url);
    }
    $(document).on('click', 'tr.ppmp-row', function (e) {
        if (window.getSelection().toString()) return;   // selecting text to copy, not opening
        openRow(this, e.ctrlKey || e.metaKey);
    });
    $(document).on('auxclick', 'tr.ppmp-row', function (e) { if (e.button === 1) openRow(this, true); });
    $(document).on('keydown', 'tr.ppmp-row', function (e) { if (e.key === 'Enter') openRow(this, e.ctrlKey || e.metaKey); });

    // A numbered Division PPMP opens read-only in a modal
    $(document).on('click', '.btn-division-ppmp', function () {
        const btn = $(this).prop('disabled', true);
        $.get(btn.data('url'), function (html) {
            $('#modal-body').html(html);
            const el = document.getElementById('DIVISION_PPMP_DETAILS_MODAL');
            el.addEventListener('hidden.bs.modal', () => $('#modal-body').html(''));
            new bootstrap.Modal(el).show();
        }).fail(function (xhr) {
            toastr.error((xhr.responseJSON || {}).message || 'Could not load the PPMP.', 'Error');
        }).always(() => btn.prop('disabled', false));
    });

    $(document).on('click', '.btn-approve-division', function () {
        new bootstrap.Modal(document.getElementById('approve-modal-' + $(this).data('target'))).show();
    });

    $(document).on('submit', '.form-approve-division', function (e) {
        e.preventDefault();
        const form = $(this);
        const btn = form.find('button[type=submit]').prop('disabled', true);

        $.ajax({
            url: '{{ route("procurement.division-ppmp.approve") }}',
            type: 'POST',
            data: form.serialize() + '&_token={{ csrf_token() }}',
            success: function (response) {
                toastr.success(response.message, 'Approved');
                setTimeout(function () { window.location.href = response.url; }, 700);
            },
            error: function (xhr) {
                btn.prop('disabled', false);
                const res = xhr.responseJSON || {};
                const firstError = res.errors ? Object.values(res.errors)[0][0] : null;
                toastr.error(firstError || res.message || 'Something went wrong.', 'Error');
            }
        });
    });
});
</script>
@endpush
