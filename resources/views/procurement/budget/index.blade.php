@extends('BackEnd.layouts.master')

@section('content')
@php $peso = fn ($cents) => number_format($cents / 100, 2); @endphp

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h5 class="fw-bold mb-0"><i class="fas fa-coins text-muted me-2"></i>Budget Allocation</h5>
        <div class="text-muted small">Approved budget per office (MOOE and CO). An office's budget covers everything under it; offices below can be given their own share. PPMPs over budget cannot be submitted.</div>
    </div>
    <form method="GET" class="d-flex align-items-center gap-2">
        <input type="hidden" name="fund" value="{{ $fund->value }}">
        <label class="small text-muted">Fiscal Year</label>
        <select name="fy" class="form-select form-select-sm" style="width: 110px;" onchange="this.form.submit()">
            @foreach ($years as $year)<option value="{{ $year }}" @selected($year === $fiscalYear)>{{ $year }}</option>@endforeach
        </select>
    </form>
</div>

<div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
    <div class="card-header bg-white pt-3 pb-0 px-4 d-flex justify-content-between align-items-end" style="border-bottom: 1px solid #f1f5f9;">
        <ul class="nav nav-tabs border-0">
            @foreach (\App\Enums\FundGroup::cases() as $f)
                <li class="nav-item"><a class="nav-link {{ $f === $fund ? 'active fw-semibold' : 'text-muted' }}" href="{{ route('procurement.budget.index', ['fy' => $fiscalYear, 'fund' => $f->value]) }}">{{ $f->label() }}</a></li>
            @endforeach
        </ul>
        <button class="btn btn-sm btn-success mb-2" id="btn_add"><i class="fas fa-plus me-1"></i> Set Office Budget</button>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle small mb-0">
            <thead class="table-light">
                <tr class="text-nowrap">
                    <th class="ps-4" rowspan="2">Office</th>
                    <th class="text-center border-start" colspan="3">CO</th>
                    <th class="text-center border-start" colspan="3">MOOE</th>
                    <th rowspan="2"></th>
                </tr>
                <tr class="text-nowrap">
                    <th class="text-end border-start">Allocated</th><th class="text-end">Used</th><th class="text-end">Remaining</th>
                    <th class="text-end border-start">Allocated</th><th class="text-end">Used</th><th class="text-end">Remaining</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($allocations as $row)
                    @php $a = $row['allocation']; @endphp
                    <tr>
                        <td class="ps-4" style="padding-left: {{ 1.5 + $row['depth'] * 1.5 }}rem !important;">
                            @if ($row['depth'])<i class="fas fa-level-up-alt fa-rotate-90 text-muted me-1"></i>@endif
                            <span class="fw-semibold">{{ $a->office->code }}</span> {{ $a->office->name }}
                        </td>
                        @foreach (['co', 'mooe'] as $class)
                            @php $c = $row[$class]; @endphp
                            <td class="text-end border-start">
                                {{ $peso($c['amount']) }}
                                @if ($c['given'])<div class="text-muted" title="Given to offices below">↳ {{ $peso($c['given']) }}</div>@endif
                            </td>
                            <td class="text-end">{{ $peso($c['used']) }}</td>
                            <td class="text-end fw-semibold {{ $c['remaining'] < 0 ? 'text-danger' : 'text-success' }}">{{ $peso($c['remaining']) }}</td>
                        @endforeach
                        <td class="pe-4 text-end text-nowrap">
                            <button class="btn btn-sm btn-link p-0 me-2 btn-edit" title="Edit / realign"
                                data-office="{{ $a->office_id }}" data-mooe="{{ number_format((float) $a->mooe_amount, 2) }}" data-co="{{ number_format((float) $a->co_amount, 2) }}"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-link p-0 btn-history" data-bs-toggle="modal" data-bs-target="#history-{{ $a->id }}" title="History"><i class="fas fa-history"></i></button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-5"><i class="fas fa-coins fa-2x mb-2 d-block opacity-50"></i>No {{ $fund->label() }} budget set for FY {{ $fiscalYear }} yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="small text-muted px-4 py-2">Used = latest submitted or approved PPMPs of the office and everything under it (drafts do not hold budget). ↳ = already given to offices below.</div>
</div>

{{-- History modals --}}
@foreach ($allocations as $row)
    @php $a = $row['allocation']; @endphp
    <div class="modal fade" id="history-{{ $a->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light py-3"><h5 class="modal-title fw-bold"><i class="fas fa-history me-2"></i>{{ $a->office->code }} — {{ $fund->label() }} FY {{ $fiscalYear }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body p-0">
                    <table class="table table-sm small mb-0">
                        <thead class="table-light"><tr><th class="ps-3">When</th><th>By</th><th class="text-end">CO</th><th class="text-end">MOOE</th><th class="pe-3">Reason</th></tr></thead>
                        <tbody>
                            @foreach ($a->history as $h)
                                <tr>
                                    <td class="ps-3 text-nowrap">{{ $h->created_at->format('M d, Y h:i A') }}</td>
                                    <td>{{ $h->user?->fullname }}</td>
                                    <td class="text-end text-nowrap">@if ($h->old_co !== null){{ number_format((float) $h->old_co, 2) }} → @endif{{ number_format((float) $h->new_co, 2) }}</td>
                                    <td class="text-end text-nowrap">@if ($h->old_mooe !== null){{ number_format((float) $h->old_mooe, 2) }} → @endif{{ number_format((float) $h->new_mooe, 2) }}</td>
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

{{-- Set / realign modal --}}
<div class="modal fade" id="BUDGET_MODAL" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow" id="form_budget" novalidate>
            <input type="hidden" name="fiscal_year" value="{{ $fiscalYear }}">
            <input type="hidden" name="fund_group" value="{{ $fund->value }}">
            <div class="modal-header bg-light py-3"><h5 class="modal-title fw-bold"><i class="fas fa-coins me-2"></i>{{ $fund->label() }} Budget — FY {{ $fiscalYear }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4 small">
                <div class="mb-3">
                    <label class="form-label fw-semibold text-muted mb-1">Office</label>
                    <select name="office_id" class="form-select form-select-sm">
                        @foreach ($offices as $o)<option value="{{ $o->id }}">{{ $o->label() }}</option>@endforeach
                    </select>
                    <div class="invalid-feedback"></div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold text-muted mb-1">CO (PHP)</label>
                        <input type="text" inputmode="decimal" name="co_amount" class="form-control form-control-sm text-end js-money" placeholder="0.00">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold text-muted mb-1">MOOE (PHP)</label>
                        <input type="text" inputmode="decimal" name="mooe_amount" class="form-control form-control-sm text-end js-money" placeholder="0.00">
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
                <div class="mb-0">
                    <label class="form-label fw-semibold text-muted mb-1">Reason <span class="text-muted fw-normal">(required when changing; e.g. realignment)</span></label>
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
@endsection

@push('script')
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function () {
    const form = $('#form_budget');
    const modal = () => bootstrap.Modal.getOrCreateInstance(document.getElementById('BUDGET_MODAL'));
    const peso = (n) => n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function open(data) {
        form.find('.is-invalid').removeClass('is-invalid');
        form.find('[name=office_id]').val(data.office || form.find('[name=office_id] option:first').val()).prop('disabled', !!data.office);
        form.find('[name=co_amount]').val(data.co || '');
        form.find('[name=mooe_amount]').val(data.mooe || '');
        form.find('[name=reason]').val('');
        modal().show();
    }
    $('#btn_add').on('click', () => open({}));
    $(document).on('click', '.btn-edit', function () { open($(this).data()); });

    // thousands separators while typing
    form.find('.js-money').on('blur', function () {
        const n = parseFloat(this.value.replace(/,/g, ''));
        if (!isNaN(n)) this.value = peso(n);
    }).on('input', function () {
        const raw = this.value.replace(/[^0-9.]/g, '');
        const [w, d] = raw.split('.');
        this.value = (w || '').replace(/\B(?=(\d{3})+(?!\d))/g, ',') + (d !== undefined ? '.' + d.slice(0, 2) : '');
    });

    form.on('submit', function (e) {
        e.preventDefault();
        form.find('.is-invalid').removeClass('is-invalid');
        const officeSelect = form.find('[name=office_id]');
        const data = form.serialize() + (officeSelect.prop('disabled') ? '&office_id=' + officeSelect.val() : '') + '&_token={{ csrf_token() }}';
        $.post('{{ route("procurement.budget.store") }}', data)
            .done((res) => { toastr.success(res.message, 'Saved'); setTimeout(() => window.location.reload(), 500); })
            .fail(function (xhr) {
                const res = xhr.responseJSON || {};
                $.each(res.errors || {}, (k, m) => form.find('[name="' + k + '"]').addClass('is-invalid').siblings('.invalid-feedback').text(m[0]));
                if (!res.errors) Swal.fire({ icon: 'error', title: 'Not saved', text: res.message || 'Something went wrong.' });
            });
    });
});
</script>
@endpush
