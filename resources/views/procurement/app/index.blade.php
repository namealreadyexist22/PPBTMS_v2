@extends('BackEnd.layouts.master')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h5 class="fw-bold mb-0"><i class="fas fa-calendar-check text-muted me-2"></i>Annual Procurement Plan</h5>
        <div class="text-muted small">One APP per region: Luzon/Mindanao by the BAC, Visayas by the Regional BAC. BAC Secretariat prepares, BAC Chair recommends, HOPE approves.</div>
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

<div class="row g-3">
    @foreach ($regions as $r)
        @php $region = $r['region']; $latest = $r['versions']->first(); @endphp
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 12px;">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <div class="small text-muted">{{ $region->bac() }}</div>
                            <h6 class="fw-bold mb-1">APP FY {{ $fiscalYear }} — {{ $region->label() }}</h6>
                        </div>
                        @if ($r['canManage'])
                            <button class="btn btn-sm btn-outline-secondary btn-signatories" data-region="{{ $region->value }}"><i class="fas fa-signature me-1"></i> Signatories</button>
                        @endif
                    </div>

                    @if ($r['versions']->isEmpty())
                        <p class="text-muted small my-3">No APP yet for FY {{ $fiscalYear }}.</p>
                        @if ($r['canManage'])
                            <form class="d-flex gap-2 align-items-center form-create-app">
                                <input type="hidden" name="fiscal_year" value="{{ $fiscalYear }}">
                                <input type="hidden" name="region" value="{{ $region->value }}">
                                <select name="type" class="form-select form-select-sm" style="width: 140px;">
                                    @foreach ($types as $type)
                                        <option value="{{ $type->value }}" @selected($type === \App\Enums\AppType::Final)>{{ $type->label() }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-sm btn-success"><i class="fas fa-plus me-1"></i> Create APP</button>
                            </form>
                        @endif
                    @else
                        <table class="table table-sm small align-middle mt-3 mb-0">
                            <thead class="table-light"><tr><th>Version</th><th>Type</th><th class="text-center">Status</th><th class="text-end">Total Budget</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($r['versions'] as $v)
                                    <tr>
                                        <td>{{ $v->version }}</td>
                                        <td>{{ $v->type->label() }}</td>
                                        <td class="text-center"><span class="badge bg-{{ $v->status->color() }}">{{ $v->status->label() }}</span></td>
                                        <td class="text-end">₱ {{ number_format((float) $v->total_budget, 2) }}</td>
                                        <td class="text-end"><a href="{{ route('procurement.app.show', $v) }}">Open</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif

                    <div class="small text-muted mt-3">
                        @foreach ($roles as $role => $label)
                            <div>{{ \Illuminate\Support\Str::before($label, ' (') }}: <strong>{{ $r['signatories'][$role]?->fullname ?? 'not set' }}</strong></div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Signatories for this region --}}
        @if ($r['canManage'])
            <div class="modal fade" id="signatories-{{ $region->value }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <form class="modal-content border-0 shadow form-signatories">
                        <input type="hidden" name="region" value="{{ $region->value }}">
                        <div class="modal-header bg-light py-3">
                            <h5 class="modal-title fw-bold"><i class="fas fa-signature me-2"></i>{{ $region->label() }} APP Signatories</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4 small">
                            @foreach ($roles as $role => $label)
                                <div class="mb-3">
                                    <label class="form-label fw-semibold text-muted mb-1">{{ $label }}</label>
                                    <select name="{{ $role }}" class="form-select form-select-sm">
                                        <option value="">— Not set —</option>
                                        @foreach ($users as $u)
                                            <option value="{{ $u->id }}" @selected($r['signatories'][$role]?->id === $u->id)>{{ $u->fullname }}{{ $u->designation ? ' — ' . $u->designation : '' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endforeach
                            <div class="form-text">The BAC Chairperson recommends and the HOPE approves in the system; all three print on the APP.</div>
                        </div>
                        <div class="modal-footer bg-light py-2">
                            <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-sm btn-primary px-3"><i class="fas fa-save me-1"></i> Save</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endforeach
</div>
@endsection

@push('script')
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function () {
    const csrf = '{{ csrf_token() }}';
    const fail = function (xhr) {
        const res = xhr.responseJSON || {};
        toastr.error(res.errors ? Object.values(res.errors)[0][0] : (res.message || 'Something went wrong.'), 'Error');
    };

    $(document).on('submit', '.form-create-app', function (e) {
        e.preventDefault();
        $.post('{{ route("procurement.app.store") }}', $(this).serialize() + '&_token=' + csrf)
            .done(function (res) { toastr.success(res.message, 'Created'); setTimeout(() => window.location.href = res.url, 600); })
            .fail(fail);
    });

    $(document).on('click', '.btn-signatories', function () {
        new bootstrap.Modal(document.getElementById('signatories-' + $(this).data('region'))).show();
    });

    $(document).on('submit', '.form-signatories', function (e) {
        e.preventDefault();
        $.post('{{ route("procurement.app.signatories") }}', $(this).serialize() + '&_token=' + csrf)
            .done(function (res) { toastr.success(res.message, 'Saved'); setTimeout(() => window.location.reload(), 600); })
            .fail(fail);
    });
});
</script>
@endpush
