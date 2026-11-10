@extends('BackEnd.layouts.master')

@section('content')
@php $peso = fn ($cents) => number_format($cents / 100, 2); @endphp

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h5 class="fw-bold mb-0"><i class="fas fa-coins text-muted me-2"></i>Budget Allocation</h5>
        <div class="text-muted small">Approved budget per department (MOOE, CO and semi-expendable together). All offices under a department share its budget, first come, first served; PPMPs that would go over it cannot be submitted.</div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <form method="GET" class="d-flex align-items-center gap-2" id="budget_fy_form">
            <input type="hidden" name="fund" value="{{ $fund->value }}">
            <label class="small text-muted">Fiscal Year</label>
            <select name="fy" class="form-select form-select-sm" style="width: 110px;" id="budget_fy">
                @foreach ($years as $year)<option value="{{ $year }}" @selected($year === $fiscalYear)>{{ $year }}</option>@endforeach
            </select>
        </form>
        {{-- Corporate Operating Budget printout: PS from here, CO and MOOE from the PPMPs --}}
        <a href="{{ route('procurement.budget.cob.breakdown', ['fy' => $fiscalYear]) }}" target="_blank" class="btn btn-sm btn-outline-secondary text-nowrap" id="btn_cob_breakdown"
           title="Breakdown per Office / Department (PS, CO, MOOE)"><i class="fas fa-print me-1"></i> COB Breakdown</a>
        <a href="{{ route('procurement.budget.cob.organization', ['fy' => $fiscalYear]) }}" target="_blank" class="btn btn-sm btn-outline-secondary text-nowrap" id="btn_cob_organization"
           title="The Breakdown and every department's Budget Proposal, in one printout"><i class="fas fa-print me-1"></i> Whole Organization</a>
    </div>
</div>

{{-- Swapped in place when the fund tab or year changes (no full reload) --}}
<div id="budget_view">
<div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
    <div class="card-header bg-white pt-3 pb-0 px-4" style="border-bottom: 1px solid #f1f5f9;">
        <ul class="nav nav-tabs border-0">
            @foreach (\App\Enums\FundGroup::cases() as $f)
                <li class="nav-item"><a class="nav-link budget-nav {{ $f === $fund ? 'active fw-semibold' : 'text-muted' }}" href="{{ route('procurement.budget.index', ['fy' => $fiscalYear, 'fund' => $f->value]) }}">{{ $f->label() }}</a></li>
            @endforeach
        </ul>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle small mb-0">
            <thead class="table-light">
                <tr class="text-nowrap">
                    <th class="ps-4">Department</th>
                    <th class="text-end" title="Personnel Services (salaries): printed on the COB; PPMPs are not checked against it">Personnel Services</th>
                    <th class="text-end" title="CO and MOOE: what the PPMPs use">Allocated</th><th class="text-end">Used</th><th class="text-end">Remaining</th>
                    <th style="width: 140px;"></th>
                    <th class="pe-4"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $i => $row)
                    @php $d = $row['department']; $a = $row['allocation']; $ps = $row['personnel']; @endphp
                    <tr>
                        <td class="ps-4">
                            <span class="fw-semibold">{{ $d->acronym ?: $d->code }}</span>@if ($d->acronym) <span class="text-muted">—</span> {{ $d->name }}@endif
                        </td>
                        {{-- Personnel Services: its own setting --}}
                        <td class="text-end text-nowrap">
                            @if ($ps)
                                <span class="text-muted">{{ number_format((float) $ps->amount, 2) }}</span>
                                <button class="btn btn-sm btn-link p-0 ms-1 btn-set-ps" title="Change Personnel Services"
                                        data-department="{{ $d->id }}" data-name="{{ $d->label() }}" data-amount="{{ number_format((float) $ps->amount, 2) }}" data-existing="1"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-sm btn-link p-0 ms-1" data-bs-toggle="modal" data-bs-target="#ps-history-{{ $ps->id }}" title="Personnel Services history"><i class="fas fa-history"></i></button>
                            @else
                                <button class="btn btn-sm btn-outline-secondary py-0 btn-set-ps" data-department="{{ $d->id }}" data-name="{{ $d->label() }}" title="Set Personnel Services (salaries)"><i class="fas fa-plus me-1"></i> PS</button>
                            @endif
                        </td>
                        @if ($a)
                            @php $pct = $row['amount'] > 0 ? min(100, round($row['used'] / $row['amount'] * 100)) : ($row['used'] > 0 ? 100 : 0); @endphp
                            <td class="text-end">{{ $peso($row['amount']) }}</td>
                            <td class="text-end">{{ $peso($row['used']) }}</td>
                            <td class="text-end fw-semibold {{ $row['remaining'] < 0 ? 'text-danger' : 'text-success' }}">{{ $peso($row['remaining']) }}</td>
                            <td>
                                <div class="progress" style="height: 6px;" title="{{ $pct }}% used">
                                    <div class="progress-bar {{ $row['remaining'] < 0 ? 'bg-danger' : ($pct >= 90 ? 'bg-warning' : 'bg-success') }}" style="width: {{ $pct }}%"></div>
                                </div>
                            </td>
                        @else
                            <td class="text-end text-muted">Not set</td><td></td><td></td><td></td>
                        @endif
                        <td class="pe-4 text-end text-nowrap">
                            @if ($a)
                                <button class="btn btn-sm btn-link p-0 me-2 btn-set" title="Edit / realign"
                                    data-department="{{ $d->id }}" data-name="{{ $d->label() }}" data-amount="{{ number_format((float) $a->amount, 2) }}" data-existing="1"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-sm btn-link p-0" data-bs-toggle="modal" data-bs-target="#history-{{ $a->id }}" title="History"><i class="fas fa-history"></i></button>
                            @else
                                <button class="btn btn-sm btn-outline-success py-0 btn-set" data-department="{{ $d->id }}" data-name="{{ $d->label() }}"><i class="fas fa-plus me-1"></i> Set</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-5"><i class="fas fa-building fa-2x mb-2 d-block opacity-50"></i>No {{ $fund->label() }} departments yet. Add them in Settings → Organization.</td></tr>
                @endforelse
            </tbody>
            @if ($rows->isNotEmpty())
                <tfoot class="table-light fw-semibold">
                    <tr>
                        <td class="ps-4">Total {{ $fund->label() }} FY {{ $fiscalYear }}</td>
                        <td class="text-end">{{ $totals['ps'] > 0 ? $peso($totals['ps']) : '' }}</td>
                        <td class="text-end">{{ $peso($totals['amount']) }}</td>
                        <td class="text-end">{{ $peso($totals['used']) }}</td>
                        <td class="text-end">{{ $peso($totals['amount'] - $totals['used']) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
    <div class="small text-muted px-4 py-2">Used = latest submitted or approved PPMPs of the department's offices (drafts do not hold budget).</div>
</div>

{{-- History modals --}}
@foreach ($rows->whereNotNull('allocation') as $row)
    @php $a = $row['allocation']; @endphp
    <div class="modal fade" id="history-{{ $a->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light py-3"><h5 class="modal-title fw-bold"><i class="fas fa-history me-2"></i>{{ $row['department']->shortName() }} — {{ $fund->label() }} FY {{ $fiscalYear }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body p-0">
                    <table class="table table-sm small mb-0">
                        <thead class="table-light"><tr><th class="ps-3">When</th><th>By</th><th class="text-end">Budget</th><th class="pe-3">Reason</th></tr></thead>
                        <tbody>
                            @foreach ($a->history as $h)
                                <tr>
                                    <td class="ps-3 text-nowrap">{{ $h->created_at->format('M d, Y h:i A') }}</td>
                                    <td>{{ $h->user?->fullname }}</td>
                                    <td class="text-end text-nowrap">@if ($h->old_amount !== null){{ number_format((float) $h->old_amount, 2) }} → @endif{{ number_format((float) $h->new_amount, 2) }}</td>
                                    <td class="pe-3">{{ $h->reason ?? 'Initial allocation' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endforeach

{{-- Personnel Services history modals --}}
@foreach ($rows->whereNotNull('personnel') as $row)
    @php $ps = $row['personnel']; @endphp
    <div class="modal fade" id="ps-history-{{ $ps->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light py-3"><h5 class="modal-title fw-bold"><i class="fas fa-history me-2"></i>{{ $row['department']->shortName() }} — Personnel Services FY {{ $fiscalYear }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body p-0">
                    <table class="table table-sm small mb-0">
                        <thead class="table-light"><tr><th class="ps-3">When</th><th>By</th><th class="text-end">Personnel Services</th><th class="pe-3">Reason</th></tr></thead>
                        <tbody>
                            @foreach ($ps->history as $h)
                                <tr>
                                    <td class="ps-3 text-nowrap">{{ $h->created_at->format('M d, Y h:i A') }}</td>
                                    <td>{{ $h->user?->fullname }}</td>
                                    <td class="text-end text-nowrap">@if ($h->old_amount !== null){{ number_format((float) $h->old_amount, 2) }} → @endif{{ number_format((float) $h->new_amount, 2) }}</td>
                                    <td class="pe-3">{{ $h->reason ?? 'Initial amount' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endforeach

{{-- Set / change Personnel Services --}}
<div class="modal fade" id="PS_MODAL" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow" id="form_ps" novalidate>
            <input type="hidden" name="fiscal_year" value="{{ $fiscalYear }}">
            <input type="hidden" name="fund_group" value="{{ $fund->value }}">
            <input type="hidden" name="department_id">
            <div class="modal-header bg-light py-3"><h5 class="modal-title fw-bold"><i class="fas fa-users me-2"></i>Personnel Services — FY {{ $fiscalYear }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4 small">
                <div class="mb-3">
                    <label class="form-label fw-semibold text-muted mb-1">Department</label>
                    <div class="fw-semibold" id="ps_department"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold text-muted mb-1">Personnel Services (PHP) <span class="text-muted fw-normal">— salaries and benefits</span></label>
                    <input type="text" inputmode="decimal" name="amount" class="form-control form-control-sm text-end js-money" placeholder="0.00">
                    <div class="invalid-feedback"></div>
                    <div class="form-text">Printed on the COB Breakdown per Office / Department. Separate from the CO + MOOE budget: PPMPs are not checked against it.</div>
                </div>
                <div class="mb-0" id="ps_reason">
                    <label class="form-label fw-semibold text-muted mb-1">Reason for the change</label>
                    <textarea name="reason" class="form-control form-control-sm" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-primary px-3"><i class="fas fa-save me-1"></i> Save</button>
            </div>
        </form>
    </div>
</div>

{{-- Set / realign modal --}}
<div class="modal fade" id="BUDGET_MODAL" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow" id="form_budget" novalidate>
            <input type="hidden" name="fiscal_year" value="{{ $fiscalYear }}">
            <input type="hidden" name="fund_group" value="{{ $fund->value }}">
            <input type="hidden" name="department_id">
            <div class="modal-header bg-light py-3"><h5 class="modal-title fw-bold"><i class="fas fa-coins me-2"></i>{{ $fund->label() }} Budget — FY {{ $fiscalYear }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4 small">
                <div class="mb-3">
                    <label class="form-label fw-semibold text-muted mb-1">Department</label>
                    <div class="fw-semibold" id="budget_department"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold text-muted mb-1">Approved Budget (PHP) <span class="text-muted fw-normal">— MOOE, CO and semi-expendable together</span></label>
                    <input type="text" inputmode="decimal" name="amount" class="form-control form-control-sm text-end js-money" placeholder="0.00">
                    <div class="invalid-feedback"></div>
                </div>
                <div class="mb-0" id="budget_reason">
                    <label class="form-label fw-semibold text-muted mb-1">Reason for the change <span class="text-muted fw-normal">(e.g. realignment)</span></label>
                    <textarea name="reason" class="form-control form-control-sm" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-primary px-3"><i class="fas fa-save me-1"></i> Save</button>
            </div>
        </form>
    </div>
</div>
</div>
@endsection

@push('script')
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function () {
    const modal = () => bootstrap.Modal.getOrCreateInstance(document.getElementById('BUDGET_MODAL'));
    const peso = (n) => n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // Fund tabs and fiscal year: load the other view and swap it in, without reloading the page
    function loadView(url, push = true) {
        const view = $('#budget_view').css({ opacity: .5, pointerEvents: 'none' });
        $.get(url)
            .done(function (html) {
                const fresh = $('<div>').append($.parseHTML(html)).find('#budget_view');
                if (!fresh.length) { window.location.href = url; return; }
                view.replaceWith(fresh);
                $('#budget_fy_form [name=fund]').val(new URL(url, window.location.href).searchParams.get('fund') || 'regular');
                $('#btn_cob_breakdown').attr('href', '{{ route("procurement.budget.cob.breakdown") }}?fy=' + $('#budget_fy').val());
                $('#btn_cob_organization').attr('href', '{{ route("procurement.budget.cob.organization") }}?fy=' + $('#budget_fy').val());
                if (push) history.pushState({ budget: true }, '', url);
            })
            .fail(() => { window.location.href = url; })
            .always(() => $('#budget_view').css({ opacity: '', pointerEvents: '' }));
    }

    $(document).on('click', '.budget-nav', function (e) {
        if (e.ctrlKey || e.metaKey || e.shiftKey) return;   // let open-in-new-tab work
        e.preventDefault();
        if (!$(this).hasClass('active')) loadView(this.href);
    });
    $('#budget_fy').on('change', function () {
        loadView('{{ route("procurement.budget.index") }}?' + $('#budget_fy_form').serialize());
    });
    window.addEventListener('popstate', () => loadView(window.location.href, false));

    $(document).on('click', '.btn-set', function () {
        const data = $(this).data();
        const form = $('#form_budget');
        form.find('.is-invalid').removeClass('is-invalid');
        form.find('[name=department_id]').val(data.department);
        form.find('[name=amount]').val(data.amount || '');
        form.find('[name=reason]').val('');
        $('#budget_department').text(data.name);
        $('#budget_reason').toggle(!!data.existing);
        modal().show();
    });

    // Personnel Services: its own setting
    $(document).on('click', '.btn-set-ps', function () {
        const data = $(this).data();
        const form = $('#form_ps');
        form.find('.is-invalid').removeClass('is-invalid');
        form.find('[name=department_id]').val(data.department);
        form.find('[name=amount]').val(data.amount || '');
        form.find('[name=reason]').val('');
        $('#ps_department').text(data.name);
        $('#ps_reason').toggle(!!data.existing);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('PS_MODAL')).show();
    });

    $(document).on('submit', '#form_ps', function (e) {
        e.preventDefault();
        const form = $(this);
        form.find('.is-invalid').removeClass('is-invalid');
        $.post('{{ route("procurement.budget.personnel-services") }}', form.serialize() + '&_token={{ csrf_token() }}')
            .done((res) => {
                toastr.success(res.message, 'Saved');
                document.getElementById('PS_MODAL').addEventListener('hidden.bs.modal', () => loadView(window.location.href, false), { once: true });
                bootstrap.Modal.getInstance(document.getElementById('PS_MODAL')).hide();
            })
            .fail(function (xhr) {
                const res = xhr.responseJSON || {};
                $.each(res.errors || {}, (k, m) => form.find('[name="' + k + '"]').addClass('is-invalid').siblings('.invalid-feedback').text(m[0]));
                if (!res.errors) Swal.fire({ icon: 'error', title: 'Not saved', text: res.message || 'Something went wrong.' });
            });
    });

    // thousands separators while typing
    $(document).on('blur', '#form_budget .js-money, #form_ps .js-money', function () {
        const n = parseFloat(this.value.replace(/,/g, ''));
        if (!isNaN(n)) this.value = peso(n);
    }).on('input', '#form_budget .js-money, #form_ps .js-money', function () {
        const raw = this.value.replace(/[^0-9.]/g, '');
        const [w, d] = raw.split('.');
        this.value = (w || '').replace(/\B(?=(\d{3})+(?!\d))/g, ',') + (d !== undefined ? '.' + d.slice(0, 2) : '');
    });

    $(document).on('submit', '#form_budget', function (e) {
        e.preventDefault();
        const form = $(this);
        form.find('.is-invalid').removeClass('is-invalid');
        $.post('{{ route("procurement.budget.store") }}', form.serialize() + '&_token={{ csrf_token() }}')
            .done((res) => {
                toastr.success(res.message, 'Saved');
                // Refresh the table in place once the dialog has closed (it is swapped out with the view)
                document.getElementById('BUDGET_MODAL').addEventListener('hidden.bs.modal', () => loadView(window.location.href, false), { once: true });
                modal().hide();
            })
            .fail(function (xhr) {
                const res = xhr.responseJSON || {};
                $.each(res.errors || {}, (k, m) => form.find('[name="' + k + '"]').addClass('is-invalid').siblings('.invalid-feedback').text(m[0]));
                if (!res.errors) Swal.fire({ icon: 'error', title: 'Not saved', text: res.message || 'Something went wrong.' });
            });
    });
});
</script>
@endpush
