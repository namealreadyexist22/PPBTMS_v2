@extends('BackEnd.layouts.master')

@section('content')
@php
    use App\Enums\AppStatus;
    $returned = $app->status === AppStatus::Draft ? $app->signatories->where('role', 'returned')->sortByDesc('id')->first() : null;
    $epa = $app->items->where('early_procurement', true)->sum(fn ($l) => (float) $l->estimated_budget);
    $cse = $app->items->where('is_cse', true)->sum(fn ($l) => (float) $l->estimated_budget);
    $mmYyyy = fn ($d) => $d->format('n/Y');
@endphp

<div class="mb-3">
    <a href="{{ route('procurement.app.index', ['fy' => $app->fiscal_year]) }}" class="text-decoration-none small text-muted"><i class="fas fa-arrow-left me-1"></i> Back to APP</a>
</div>

{{-- Header --}}
<div class="card border-0 shadow-sm mb-3" style="border-radius: 12px;">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
            <div>
                <div class="small text-muted text-uppercase fw-semibold">Annual Procurement Plan · {{ $app->region->bac() }}</div>
                <h4 class="fw-bold mb-1">
                    FY {{ $app->fiscal_year }} — {{ $app->region->label() }}
                    <span class="badge bg-{{ $app->status->color() }} align-middle ms-1" style="font-size: .7rem;">{{ $app->status->label() }}</span>
                </h4>
                <div class="small text-muted">
                    {{ $app->type->label() }}{{ $app->version > 1 ? ' · Version No. ' . $app->version : '' }}
                    @if ($versions->count() > 1)
                        · Versions:
                        @foreach ($versions as $v)
                            <a href="{{ route('procurement.app.show', $v) }}" class="badge {{ $v->id === $app->id ? 'bg-dark' : 'bg-light text-dark border' }} text-decoration-none">{{ $v->version }}</a>
                        @endforeach
                    @endif
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('procurement.app.print', $app) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fas fa-print me-1"></i> Print</a>
                @if ($canEdit)
                    <button class="btn btn-sm btn-primary" id="btn_submit"><i class="fas fa-paper-plane me-1"></i> Submit for Recommendation</button>
                @endif
                @if ($canRecommend)
                    <button class="btn btn-sm btn-success" id="btn_recommend"><i class="fas fa-thumbs-up me-1"></i> Recommend</button>
                    <button class="btn btn-sm btn-warning btn-return"><i class="fas fa-undo me-1"></i> Return</button>
                @endif
                @if ($canApprove)
                    <button class="btn btn-sm btn-success" id="btn_approve"><i class="fas fa-check me-1"></i> Approve</button>
                    <button class="btn btn-sm btn-warning btn-return"><i class="fas fa-undo me-1"></i> Return</button>
                @endif
                @if ($canUpdate)
                    <button class="btn btn-sm btn-outline-primary" id="btn_update_version"><i class="fas fa-code-branch me-1"></i> Create Updated Version</button>
                @endif
            </div>
        </div>
        <hr class="my-3">
        <div class="row g-3 small">
            <div class="col-6 col-md-3"><div class="text-muted">BAC Chairperson (recommends)</div><div class="fw-semibold">{{ $chair?->fullname ?? 'Not set' }}</div></div>
            <div class="col-6 col-md-3"><div class="text-muted">HOPE (approves)</div><div class="fw-semibold">{{ $hope?->fullname ?? 'Not set' }}</div></div>
            <div class="col-4 col-md-2 text-md-end"><div class="text-muted">EPA Projects</div><div class="fw-semibold">₱ {{ number_format($epa, 2) }}</div></div>
            <div class="col-4 col-md-2 text-md-end"><div class="text-muted">CSE (PS-DBM)</div><div class="fw-semibold">₱ {{ number_format($cse, 2) }}</div></div>
            <div class="col-4 col-md-2 text-md-end"><div class="text-muted">Total Budget</div><div class="fw-bold fs-5">₱ {{ number_format((float) $app->total_budget, 2) }}</div></div>
        </div>
        @if ($returned)
            <div class="alert alert-warning small mt-3 mb-0"><i class="fas fa-undo me-1"></i> <strong>Returned by {{ $returned->name_snapshot }}</strong> on {{ $returned->signed_at->format('M d, Y h:i A') }}: {{ $returned->remarks }}</div>
        @endif
    </div>
</div>

{{-- Unassigned PPMP projects --}}
@if ($canEdit)
    <div class="card border-0 shadow-sm mb-3" style="border-radius: 12px; overflow: hidden;">
        <div class="card-header bg-white pt-3 pb-2 px-4 d-flex flex-wrap justify-content-between align-items-center gap-2" style="border-bottom: 1px solid #f1f5f9;">
            <h6 class="m-0 fw-bold"><i class="fas fa-inbox text-muted me-2"></i>PPMP projects not yet in the APP ({{ $pool->count() }})</h6>
            @if ($pool->isNotEmpty())
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-success" id="btn_generate_selected"><i class="fas fa-plus me-1"></i> Add selected as lines</button>
                    <button class="btn btn-sm btn-success" id="btn_generate_all"><i class="fas fa-plus-circle me-1"></i> Add all as lines</button>
                </div>
            @endif
        </div>
        @if ($pool->isEmpty())
            <div class="card-body small text-muted">All projects of the approved {{ $app->region->label() }} Division PPMPs are in the APP.</div>
        @else
            <div class="table-responsive" style="max-height: 320px;">
                <table class="table table-sm table-hover align-middle small mb-0">
                    <thead class="table-light sticky-top"><tr><th class="ps-4"><input type="checkbox" class="form-check-input" id="pool_all"></th><th>Office</th><th>PAP</th><th>Project</th><th>Mode</th><th>Class</th><th class="text-end pe-4">Budget</th></tr></thead>
                    <tbody>
                        @foreach ($pool as $item)
                            <tr>
                                <td class="ps-4"><input type="checkbox" class="form-check-input pool-check" value="{{ $item->id }}"></td>
                                <td>{{ $item->ppmp->office->shortName() }}</td>
                                <td class="text-nowrap">{{ $item->pap?->code }}</td>
                                <td>{{ $item->description }}</td>
                                <td title="{{ $item->procurementMode->name }}">{{ $item->procurementMode->code }}</td>
                                <td>{{ $item->allotment_class->short() }}</td>
                                <td class="text-end pe-4">{{ number_format((float) $item->estimated_budget, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endif

{{-- APP lines --}}
<div class="card border-0 shadow-sm mb-3" style="border-radius: 12px; overflow: hidden;">
    <div class="card-header bg-white pt-3 pb-2 px-4 d-flex flex-wrap justify-content-between align-items-center gap-2" style="border-bottom: 1px solid #f1f5f9;">
        <h6 class="m-0 fw-bold"><i class="fas fa-list text-muted me-2"></i>APP Lines ({{ $app->items->count() }})</h6>
        @if ($canEdit && $app->items->count() > 1)
            <button class="btn btn-sm btn-outline-primary" id="btn_group"><i class="fas fa-object-group me-1"></i> Group selected</button>
        @endif
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle small mb-0">
            <thead class="table-light">
                <tr class="text-nowrap">
                    @if ($canEdit)<th class="ps-4"></th>@endif
                    <th class="{{ $canEdit ? '' : 'ps-4' }}" style="min-width: 220px;">Project Title</th>
                    <th>End-User</th>
                    <th>Mode</th>
                    <th class="text-center">EPA</th>
                    <th>Criteria</th>
                    <th>Timeline</th>
                    <th>Fund</th>
                    <th class="text-end">Budget</th>
                    <th class="text-center">PPMP</th>
                    @if ($canEdit)<th class="pe-4"></th>@endif
                </tr>
            </thead>
            <tbody>
                @php $cols = $canEdit ? 11 : 9; @endphp
                @forelse ($lines['main'] as $group => $groupLines)
                    @if ($group !== '')
                        <tr class="table-secondary"><td colspan="{{ $cols }}" class="ps-4 fw-bold">{{ $group }}</td></tr>
                    @endif
                    @foreach ($groupLines as $line)
                        @include('procurement.app.extras.app_line_row')
                    @endforeach
                @empty
                    @if ($lines['cse']->isEmpty())
                        <tr><td colspan="{{ $cols }}" class="text-center text-muted py-5"><i class="fas fa-inbox fa-2x mb-2 d-block opacity-50"></i>No APP lines yet.@if ($canEdit) Add PPMP projects from the list above.@endif</td></tr>
                    @endif
                @endforelse
                @if ($lines['cse']->isNotEmpty())
                    <tr class="table-secondary"><td colspan="{{ $cols }}" class="ps-4 fw-bold">Common Use Supplies and Equipment (CSE) to be purchased from PS-DBM</td></tr>
                    @foreach ($lines['cse'] as $line)
                        @include('procurement.app.extras.app_line_row')
                    @endforeach
                @endif
            </tbody>
            @if ($app->items->isNotEmpty())
                <tfoot class="table-light"><tr><td colspan="{{ $canEdit ? 8 : 7 }}" class="text-end fw-bold">TOTAL</td><td class="text-end fw-bold text-nowrap">{{ number_format((float) $app->total_budget, 2) }}</td><td colspan="{{ $canEdit ? 2 : 1 }}"></td></tr></tfoot>
            @endif
        </table>
    </div>
</div>

<div class="card border-0 shadow-sm" style="border-radius: 12px;">
    <div class="card-header bg-white pt-3 pb-2 px-4" style="border-bottom: 1px solid #f1f5f9;"><h6 class="m-0 fw-bold"><i class="fas fa-history text-muted me-2"></i>History</h6></div>
    <ul class="list-group list-group-flush small">
        @forelse ($app->signatories->sortBy('id') as $s)
            <li class="list-group-item px-4">
                <span class="badge bg-light text-dark border text-capitalize me-2">{{ $s->role }}</span>
                <strong>{{ $s->name_snapshot }}</strong>@if ($s->designation_snapshot)<span class="text-muted">, {{ $s->designation_snapshot }}</span>@endif
                <span class="text-muted ms-2">{{ $s->signed_at->format('M d, Y h:i A') }}</span>
                @if ($s->remarks)<div class="text-muted mt-1"><i class="fas fa-comment-alt me-1"></i>{{ $s->remarks }}</div>@endif
            </li>
        @empty
            <li class="list-group-item px-4 text-muted">Not yet submitted.</li>
        @endforelse
    </ul>
</div>

<div id="modal-body"></div>
@endsection

@push('script')
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function () {
    const csrf = '{{ csrf_token() }}';

    function send(url, data, method = 'POST', confirmReload = true) {
        return $.ajax({
            url: url, type: 'POST',
            data: Object.assign({ _token: csrf, _method: method }, data || {}),
            success: function (res) {
                toastr.success(res.message, 'Success');
                setTimeout(function () { window.location.href = res.url || window.location.href; }, 600);
            },
            error: function (xhr) {
                const res = xhr.responseJSON || {};
                toastr.error(res.errors ? Object.values(res.errors)[0][0] : (res.message || 'Something went wrong.'), 'Error');
            }
        });
    }
    const checked = (selector) => $(selector + ':checked').map(function () { return $(this).val(); }).get();

    // Unassigned PPMP projects -> lines
    $('#pool_all').on('change', function () { $('.pool-check').prop('checked', this.checked); });
    $('#btn_generate_all').on('click', () => send('{{ route("procurement.app.generate", $app) }}'));
    $('#btn_generate_selected').on('click', function () {
        const ids = checked('.pool-check');
        if (!ids.length) return toastr.warning('Select PPMP projects first.');
        send('{{ route("procurement.app.generate", $app) }}', { ppmp_item_ids: ids });
    });

    // Group / ungroup / remove lines
    $('#btn_group').on('click', function () {
        const ids = checked('.line-check');
        if (ids.length < 2) return toastr.warning('Select at least two APP lines to group.');
        Swal.fire({ title: 'Group ' + ids.length + ' lines into one?', text: 'Budgets are added and end-users listed. You can split them again later.',
            icon: 'question', showCancelButton: true, confirmButtonText: 'Group', reverseButtons: true })
            .then((r) => { if (r.isConfirmed) send('{{ route("procurement.app.group", $app) }}', { line_ids: ids }); });
    });
    $(document).on('click', '.btn-ungroup', function () { send('{{ route("procurement.app.ungroup", $app) }}', { id: $(this).data('id') }); });
    $(document).on('click', '.btn-remove-line', function () {
        const id = $(this).data('id');
        Swal.fire({ title: 'Remove this line?', text: 'Its PPMP projects go back to the unassigned list.', icon: 'warning', showCancelButton: true,
            confirmButtonColor: '#ef4444', confirmButtonText: 'Remove', reverseButtons: true })
            .then((r) => { if (r.isConfirmed) send('{{ route("procurement.app.lines.destroy", $app) }}', { id: id }, 'DELETE'); });
    });

    // Edit line
    $(document).on('click', '.btn-edit-line', function () {
        $.get('{{ route("procurement.app.lines.entry", $app) }}', { id: $(this).data('id') }, function (html) {
            $('#modal-body').html(html);
            const el = document.getElementById('APP_LINE_MODAL');
            new bootstrap.Modal(el).show();
            el.addEventListener('hidden.bs.modal', () => $('#modal-body').html(''));
        }).fail(() => toastr.error('Could not open form.'));
    });
    $(document).on('submit', '#form_app_line', function (e) {
        e.preventDefault();
        const form = $(this);
        form.find('.is-invalid').removeClass('is-invalid');
        $.post('{{ route("procurement.app.lines.store", $app) }}', form.serialize())
            .done(function (res) { toastr.success(res.message, 'Saved'); setTimeout(() => window.location.reload(), 500); })
            .fail(function (xhr) {
                const res = xhr.responseJSON || {};
                $.each(res.errors || {}, function (key, messages) {
                    form.find('[name="' + key + '"]').addClass('is-invalid').siblings('.invalid-feedback').text(messages[0]);
                });
                toastr.error(res.errors ? 'Please correct the highlighted fields.' : (res.message || 'Something went wrong.'), 'Error');
            });
    });

    // Workflow
    const ask = (title, action, url, required = false, color = '#0d6efd') => Swal.fire({
        title: title, input: 'textarea', inputPlaceholder: required ? 'Reason (required)' : 'Remarks (optional)',
        inputValidator: (v) => (required && !v) ? 'Please state the reason.' : undefined,
        icon: 'question', showCancelButton: true, confirmButtonColor: color, confirmButtonText: action, reverseButtons: true
    }).then((r) => { if (r.isConfirmed) send(url, { remarks: r.value }); });

    $('#btn_submit').on('click', () => ask('Submit the APP to the BAC Chairperson?', 'Submit', '{{ route("procurement.app.submit", $app) }}'));
    $('#btn_recommend').on('click', () => ask('Recommend this APP for approval?', 'Recommend', '{{ route("procurement.app.recommend", $app) }}', false, '#10b981'));
    $('#btn_approve').on('click', () => ask('Approve this APP?', 'Approve', '{{ route("procurement.app.approve", $app) }}', false, '#10b981'));
    $('.btn-return').on('click', () => ask('Return to the BAC Secretariat', 'Return', '{{ route("procurement.app.return", $app) }}', true, '#f59e0b'));
    $('#btn_update_version').on('click', function () {
        Swal.fire({ title: 'Create an updated version?', text: 'A new draft (UPDATED, next version no.) is made from this APP and the latest approved PPMPs.',
            icon: 'question', showCancelButton: true, confirmButtonText: 'Create', reverseButtons: true })
            .then((r) => { if (r.isConfirmed) send('{{ route("procurement.app.update-version", $app) }}'); });
    });
});
</script>
@endpush
