@extends('BackEnd.layouts.master')

{{-- A PAP's Performance Contract entry (COB Form A): activities (brief description) with indicators and quarterly physical targets --}}
@section('content')
@php $rows = $pap->indicators->isNotEmpty() ? $pap->indicators : collect([null]); @endphp

<div class="mb-3">
    <a href="{{ route('procurement.budget-proposals.show', $proposal) }}" class="text-decoration-none small text-muted"><i class="fas fa-arrow-left me-1"></i> Back to the FY {{ $proposal->fiscal_year }} Budget Proposal of {{ $proposal->department->shortName() }}</a>
</div>

<div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
    <div class="card-header bg-white pt-3 pb-3 px-4 d-flex flex-wrap align-items-center gap-3" style="border-bottom: 1px solid #f1f5f9;">
        <div class="me-auto">
            <div class="small text-muted text-uppercase fw-semibold" style="letter-spacing: .5px;">Performance Targets · COB Form A</div>
            <h5 class="fw-bold mb-0"><i class="fas fa-bullseye text-muted me-2"></i>{{ $pap->code ? $pap->code . ' — ' : '' }}{{ $pap->title }}</h5>
            <div class="small text-muted">{{ $pap->office->label() }} · FY {{ $proposal->fiscal_year }}</div>
        </div>
        @if ($paps->count() > 1)
            {{-- Another PAP of the same office --}}
            <select class="form-select form-select-sm" style="max-width: 360px;" id="pap_switch" aria-label="Go to another PAP">
                @foreach ($paps as $other)
                    <option value="{{ route('procurement.budget-proposals.performance', [$proposal, $other]) }}" @selected($other->id === $pap->id)>
                        {{ $other->code ? $other->code . ' — ' : '' }}{{ \Illuminate\Support\Str::limit($other->title, 60) }} ({{ $other->indicators_count }})
                    </option>
                @endforeach
            </select>
        @endif
        @unless ($canEdit)<span class="badge bg-light text-muted border">View only</span>@endunless
    </div>

    <form id="form_performance" autocomplete="off" novalidate>
        @csrf
        <fieldset class="card-body p-4" @disabled(! $canEdit)>
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-top small mb-2" id="indicator_table">
                    <thead class="table-light">
                        <tr class="text-nowrap align-middle">
                            <th style="width: 34px;" class="text-center">#</th>
                            <th style="min-width: 300px; width: 33%;">Brief Description</th>
                            <th style="min-width: 300px; width: 33%;">Performance Indicator</th>
                            <th class="text-center" style="width: 80px;">Q1</th><th class="text-center" style="width: 80px;">Q2</th>
                            <th class="text-center" style="width: 80px;">Q3</th><th class="text-center" style="width: 80px;">Q4</th>
                            <th class="text-center" style="width: 90px;">Total</th><th style="width: 34px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr class="indicator-row">
                                <td class="text-center text-muted js-no pt-2"></td>
                                <td><textarea class="form-control form-control-sm js-description" data-field="description" rows="2" maxlength="3000" style="resize: vertical;">{{ $row?->description }}</textarea></td>
                                <td><textarea class="form-control form-control-sm" data-field="indicator" rows="2" maxlength="500" style="resize: vertical;" placeholder="e.g. % of committee meetings attended">{{ $row?->indicator }}</textarea></td>
                                @foreach (['q1', 'q2', 'q3', 'q4', 'total'] as $q)
                                    <td><input type="text" class="form-control form-control-sm text-center {{ $q === 'total' ? 'js-total fw-semibold' : 'js-quarter' }}" data-field="{{ $q }}" maxlength="30" value="{{ $row?->$q }}"></td>
                                @endforeach
                                <td class="text-center pt-2">@if ($canEdit)<button type="button" class="btn btn-sm btn-link text-danger p-0 js-remove-row" title="Remove this row"><i class="fas fa-times"></i></button>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($canEdit)
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn_add_indicator"><i class="fas fa-plus me-1"></i> Add indicator</button>
                <div class="form-text mt-2">
                    One row per indicator. An activity with several indicators: write its description on the first row and leave it blank on the next ones (printed as one merged cell).
                    Targets as you want them printed: <code>100%</code>, <code>3</code>, or blank. Total fills itself (sum of counts; the same percent when all quarters are equal) and can be changed.
                </div>
            @endif
        </fieldset>
        @if ($canEdit)
            <div class="card-footer bg-light d-flex justify-content-end gap-2 px-4 py-2">
                <a href="{{ route('procurement.budget-proposals.show', $proposal) }}" class="btn btn-sm btn-light border">Cancel</a>
                <button type="submit" class="btn btn-sm btn-primary px-3" id="btn_save_performance"><i class="fas fa-save me-1"></i> Save</button>
            </div>
        @endif
    </form>
</div>
@endsection

@push('script')
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function () {
    const table = document.querySelector('#indicator_table tbody');
    let dirty = false;

    // Total: sum of plain numbers; the shared percent when every filled quarter has the same one
    function fillTotal(row) {
        const total = row.querySelector('.js-total');
        if (total.dataset.touched) return;
        const values = [...row.querySelectorAll('.js-quarter')].map((el) => el.value.trim()).filter((v) => v !== '');
        if (!values.length) { total.value = ''; return; }
        if (values.every((v) => /%$/.test(v))) { total.value = values.every((v) => v === values[0]) ? values[0] : ''; return; }
        if (values.every((v) => /^-?\d+(\.\d+)?$/.test(v.replace(/,/g, '')))) {
            const sum = values.reduce((s, v) => s + parseFloat(v.replace(/,/g, '')), 0);
            total.value = Number.isInteger(sum) ? String(sum) : sum.toFixed(2);
        }
    }

    // Row numbers, and "same as above" on rows after the first
    function refreshRows() {
        [...table.rows].forEach((row, i) => {
            row.querySelector('.js-no').textContent = i + 1;
            row.querySelector('.js-description').placeholder = i === 0 ? 'e.g. Implementation of laws, rules and regulations, policies' : '↑ same as above';
        });
    }

    table.addEventListener('input', function (e) {
        dirty = true;
        const row = e.target.closest('tr');
        if (e.target.classList.contains('js-total')) e.target.dataset.touched = e.target.value !== '' ? '1' : '';
        if (e.target.classList.contains('js-quarter')) fillTotal(row);
    });
    table.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-remove-row');
        if (!btn) return;
        dirty = true;
        if (table.rows.length > 1) btn.closest('tr').remove();
        else btn.closest('tr').querySelectorAll('input, textarea').forEach((el) => { el.value = ''; delete el.dataset.touched; });
        refreshRows();
    });
    // A saved total that differs from the computed one was typed by hand: keep it
    table.querySelectorAll('tr').forEach((row) => {
        const total = row.querySelector('.js-total');
        const saved = total.value;
        if (saved === '') return;
        fillTotal(row);
        if (total.value !== saved) { total.value = saved; total.dataset.touched = '1'; }
    });
    refreshRows();

    document.getElementById('btn_add_indicator')?.addEventListener('click', function () {
        const row = table.rows[0].cloneNode(true);
        row.querySelectorAll('input, textarea').forEach((el) => { el.value = ''; delete el.dataset.touched; });
        table.appendChild(row);
        refreshRows();
        row.querySelector('[data-field="indicator"]').focus();
    });

    // Leaving with unsaved changes: ask first
    window.addEventListener('beforeunload', (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
    document.getElementById('pap_switch')?.addEventListener('change', function () { window.location.href = this.value; });

    $('#form_performance').on('submit', function (e) {
        e.preventDefault();
        const btn = $('#btn_save_performance').prop('disabled', true);
        const data = { _token: '{{ csrf_token() }}', indicators: [] };
        $(table).find('tr').each(function () {
            const row = {};
            $(this).find('[data-field]').each(function () { row[$(this).data('field')] = $(this).val(); });
            data.indicators.push(row);
        });
        $.post('{{ route("procurement.budget-proposals.performance.store", [$proposal, $pap]) }}', data)
            .done(function (res) { dirty = false; toastr.success(res.message, 'Saved'); })
            .fail(function (xhr) {
                const res = xhr.responseJSON || {};
                toastr.error(res.errors ? Object.values(res.errors)[0][0] : (res.message || 'Something went wrong.'), 'Error');
            })
            .always(() => btn.prop('disabled', false));
    });
});
</script>
@endpush
