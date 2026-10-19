@extends('BackEnd.layouts.master')

@section('content')
@php
    use App\Enums\AoqStatus;
    use App\Support\Money;
    $qty = fn ($q) => rtrim(rtrim(number_format((float) $q, 2), '0'), '.');
    $cents = fn (?int $c) => $c === null ? '—' : Money::format(Money::fromCents($c));
    $rfq = $aoq->rfq;
    $suppliers = $aoq->suppliers;
    $awarded = $aoq->status === AoqStatus::Awarded;
    $awardCents = $rows->sum(fn ($r) => $r['awarded'] ? $r['quotes'][$r['awarded']]['total_cents'] : 0);
    $failed = $rows->filter(fn ($r) => $r['lowest'] === [])->count();
    $ties = $rows->filter(fn ($r) => count($r['lowest']) > 1)->count();
    $members = collect($aoq->bac_members ?? []);
@endphp

<style>
    .pr-card { border-radius: 12px; }
    .pr-label { font-size: .7rem; text-transform: uppercase; letter-spacing: .5px; font-weight: 600; color: var(--bs-secondary-color); }
    .pr-stat { border: var(--bs-border-width) solid var(--bs-border-color); border-radius: var(--bs-border-radius-lg); padding: .5rem 1rem; height: 100%; }
    .pr-empty-ico { display: inline-flex; align-items: center; justify-content: center; width: 56px; height: 56px; border-radius: 50%; font-size: 1.4rem; background-color: var(--bs-primary-bg-subtle); color: var(--bs-primary); }
    #aoq_matrix .table > thead th { font-size: .7rem; letter-spacing: .3px; font-weight: 600; color: var(--bs-secondary-color); vertical-align: top; }
    #aoq_matrix .table > thead th.sup { text-transform: none; min-width: 170px; }
    #aoq_matrix td.q { min-width: 170px; }
    #aoq_matrix td.q.lowest { background-color: var(--bs-success-bg-subtle); }
    #aoq_matrix td.q.won { background-color: var(--bs-success-bg-subtle); box-shadow: inset 0 0 0 2px var(--bs-success); }
    #aoq_matrix td.q.out .amt { text-decoration: line-through; color: var(--bs-secondary-color); }
    #aoq_matrix .card-header::after { content: none; }
    #form_aoq fieldset:disabled .form-control { background: transparent; border-color: transparent; box-shadow: none; padding-left: 0; resize: none; }
    #form_aoq_supplier { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; }
    #form_aoq_supplier .modal-body { overflow-y: auto; }
</style>

<div class="mb-3">
    <a href="{{ route('procurement.aoqs.index') }}" class="text-decoration-none small text-muted"><i class="fas fa-arrow-left me-1"></i> Back to Abstract of Quotations</a>
</div>

<div class="card border-0 shadow-sm pr-card overflow-hidden mb-3">
<div id="aoq_header">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
            <div>
                <div class="pr-label">Abstract of Quotations · {{ $rfq->procurementMode?->name ?? '—' }} · {{ $aoq->region->label() }}</div>
                <h4 class="fw-bold mb-1">
                    {{ $aoq->title() }}
                    <span class="badge rounded-pill bg-{{ $aoq->status->color() }}-subtle text-{{ $aoq->status->color() }}-emphasis border border-{{ $aoq->status->color() }}-subtle align-middle" style="font-size: .7rem;">{{ $aoq->status->label() }}</span>
                </h4>
                <div class="text-muted">{{ $rfq->label() }}</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-sm btn-outline-secondary" id="btn_print"><i class="fas fa-print me-1"></i> Print</button>
                @if ($canEdit)
                    <button class="btn btn-sm btn-outline-danger" id="btn_delete"><i class="fas fa-trash-alt me-1"></i> Delete</button>
                    <button class="btn btn-sm btn-success" id="btn_award" @disabled($suppliers->isEmpty())><i class="fas fa-trophy me-1"></i> Award</button>
                @else
                    <button class="btn btn-sm btn-outline-warning" id="btn_reopen"><i class="fas fa-undo me-1"></i> Reopen</button>
                @endif
            </div>
        </div>

        <div class="row g-2 mt-3">
            <div class="col-6 col-md-3">
                <div class="pr-stat">
                    <div class="pr-label">RFQ</div>
                    @if (auth()->user()->canAccessPermission(\App\Models\Procurement\Rfq::MANAGE_PERMISSION))
                        <a href="{{ route('procurement.rfqs.show', $rfq) }}" class="fw-semibold small text-decoration-none">{{ $rfq->title() }}</a>
                    @else
                        <div class="fw-semibold small">{{ $rfq->title() }}</div>
                    @endif
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="pr-stat">
                    <div class="pr-label">Quotations</div>
                    <div class="fw-semibold small">
                        {{ $suppliers->count() }}
                        @if ($suppliers->isNotEmpty() && $suppliers->count() < 3)
                            <span class="text-warning-emphasis fw-normal" title="Small Value Procurement asks for at least three quotations; with fewer, the BAC states why in its resolution."><i class="fas fa-exclamation-triangle ms-1"></i> fewer than 3</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="pr-stat"><div class="pr-label">ABC</div><div class="fw-semibold small">{{ number_format((float) $rfq->total_abc, 2) }}</div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="pr-stat">
                    <div class="pr-label">{{ $awarded ? 'Awarded' : 'Lowest bids' }}</div>
                    <div class="fw-semibold small">
                        @if ($awarded)
                            {{ $cents($awardCents) }}
                        @else
                            {{ $cents($rows->sum(fn ($r) => $r['lowest'] ? $r['quotes'][$r['lowest'][0]]['total_cents'] : 0) ?: null) }}
                        @endif
                        @if ($failed)<span class="text-danger fw-normal ms-1">· {{ $failed }} failed</span>@endif
                    </div>
                </div>
            </div>
        </div>

        @if ($awarded)
            <div class="alert alert-success d-flex gap-2 align-items-start border-0 small mt-3 mb-0"><i class="fas fa-trophy mt-1"></i><div>Awarded {{ $aoq->awarded_at?->format('M d, Y g:i A') }}. Each item goes to its lowest responsive quotation. Reopen to correct a quotation.</div></div>
        @endif
    </div>
</div>

<div id="aoq_matrix">
<div class="border-top">
    <div class="card-header bg-white py-3 px-4 d-flex flex-wrap align-items-center gap-2" style="border-bottom: 1px solid #f1f5f9;">
        <h6 class="m-0 fw-bold"><i class="fas fa-table text-muted me-2"></i>Quotations</h6>
        <span class="small text-muted ms-2 d-none d-md-inline">
            <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">lowest</span>
            counts when the supplier is responsive, the offer meets the specs and is within the ABC
        </span>
        @if ($canEdit)
            <button class="btn btn-sm btn-primary ms-auto js-supplier"><i class="fas fa-plus me-1"></i> Add Supplier</button>
        @endif
    </div>
    @if ($suppliers->isEmpty())
        <div class="py-5 px-4 text-center">
            <span class="pr-empty-ico mb-3"><i class="fas fa-store"></i></span>
            <div class="fw-semibold">No quotations yet.</div>
            <div class="text-muted small">Add each supplier that submitted a quotation, with its unit price per item.</div>
            @if ($canEdit)<button class="btn btn-sm btn-outline-primary js-supplier mt-3"><i class="fas fa-plus me-1"></i> Add supplier</button>@endif
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-bordered align-middle small mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width: 40px;">#</th>
                        <th style="min-width: 220px;">ITEM</th>
                        <th class="text-end text-nowrap">ABC</th>
                        @foreach ($suppliers as $s)
                            <th class="sup">
                                <div class="d-flex align-items-start gap-1">
                                    <div class="flex-grow-1">
                                        <div class="fw-bold text-body">{{ $s->name }}</div>
                                        @if ($s->address)<div class="fw-normal">{{ $s->address }}</div>@endif
                                        @unless ($s->is_responsive)
                                            <span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle" title="{{ $s->remarks }}">Not responsive</span>
                                        @endunless
                                        @if ($s->is_responsive && $s->remarks)<div class="fw-normal fst-italic">{{ $s->remarks }}</div>@endif
                                    </div>
                                    @if ($canEdit)
                                        <button class="btn btn-sm btn-link p-0 text-secondary js-supplier" data-id="{{ $s->id }}" title="Change {{ $s->name }}" aria-label="Change {{ $s->name }}"><i class="fas fa-pen"></i></button>
                                        <button class="btn btn-sm btn-link p-0 ms-1 text-danger js-remove-supplier" data-id="{{ $s->id }}" data-name="{{ $s->name }}" title="Remove" aria-label="Remove {{ $s->name }}"><i class="fas fa-times"></i></button>
                                    @endif
                                </div>
                            </th>
                        @endforeach
                        <th class="pe-3" style="min-width: 180px;">AWARD</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $i => $row)
                        @php $line = $row['item']->line; $tie = count($row['lowest']) > 1; @endphp
                        <tr>
                            <td class="ps-3 text-muted">{{ $i + 1 }}</td>
                            <td>
                                <div class="fw-semibold">{{ $line->description }}</div>
                                <div class="text-muted">{{ $qty($line->quantity) }} {{ $line->unit }} × {{ number_format((float) $line->unit_cost, 2) }}</div>
                            </td>
                            <td class="text-end text-nowrap">{{ $cents($row['abc_cents']) }}</td>
                            @foreach ($suppliers as $s)
                                @php
                                    $q = $row['quotes'][$s->id];
                                    $won = $row['awarded'] === $s->id;
                                    $low = ! $awarded && in_array($s->id, $row['lowest'], true);
                                    $class = $won ? 'won' : ($low ? 'lowest' : ($q['issue'] && $q['issue'] !== 'No quote' ? 'out' : ''));
                                @endphp
                                <td class="q {{ $class }}">
                                    @if ($q['issue'] === 'No quote')
                                        <span class="text-muted">No quote</span>
                                    @else
                                        <div class="d-flex justify-content-between gap-2">
                                            <span class="text-muted">@ {{ number_format((float) $q['quote']->unit_price, 2) }}</span>
                                            <span class="amt fw-semibold text-nowrap">{{ $cents($q['total_cents']) }}</span>
                                        </div>
                                        @if ($q['quote']->brand)<div class="text-muted text-truncate" style="max-width: 220px;" title="{{ $q['quote']->brand }}">{{ $q['quote']->brand }}</div>@endif
                                        @if ($q['issue'])
                                            <span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle">{{ $q['issue'] }}</span>
                                        @elseif ($won)
                                            <span class="badge bg-success"><i class="fas fa-trophy me-1"></i>Awarded</span>
                                        @else
                                            <span class="badge bg-light text-dark border">Rank {{ $q['rank'] }}</span>
                                        @endif
                                    @endif
                                </td>
                            @endforeach
                            <td class="pe-3">
                                @if ($row['awarded'])
                                    <span class="fw-semibold">{{ $suppliers->firstWhere('id', $row['awarded'])->name }}</span>
                                @elseif ($row['lowest'] === [])
                                    <span class="text-danger"><i class="fas fa-times-circle me-1"></i>Failed</span>
                                    <div class="text-muted">No responsive quote within the ABC</div>
                                @elseif ($tie && $canEdit)
                                    <label class="text-warning-emphasis d-block mb-1" for="tie_{{ $row['item']->id }}"><i class="fas fa-equals me-1"></i>Tie: the BAC picks</label>
                                    <select class="form-select form-select-sm js-tie" id="tie_{{ $row['item']->id }}" data-item="{{ $row['item']->id }}">
                                        <option value="">Choose…</option>
                                        @foreach ($row['lowest'] as $sid)
                                            <option value="{{ $sid }}">{{ $suppliers->firstWhere('id', $sid)->name }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <span>{{ $suppliers->firstWhere('id', $row['lowest'][0])->name }}</span>
                                    <div class="text-muted">lowest, on award</div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="table-light fw-bold">
                        <td colspan="2" class="text-end">Total</td>
                        <td class="text-end text-nowrap">{{ number_format((float) $rfq->total_abc, 2) }}</td>
                        @foreach ($suppliers as $s)
                            @php
                                $quoted = $rows->map(fn ($r) => $r['quotes'][$s->id]['total_cents'])->filter(fn ($c) => $c !== null);
                            @endphp
                            <td class="text-end text-nowrap">
                                {{ $quoted->isEmpty() ? '—' : $cents($quoted->sum()) }}
                                @if ($quoted->count() < $rows->count() && $quoted->isNotEmpty())<div class="fw-normal text-muted">{{ $quoted->count() }} of {{ $rows->count() }} items</div>@endif
                            </td>
                        @endforeach
                        <td class="pe-3 text-nowrap">{{ $awarded ? $cents($awardCents) : '' }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
</div>

{{-- Opening, remarks and signatories --}}
<div class="border-top">
    <div class="card-body p-4">
        <form id="form_aoq" autocomplete="off">
            @csrf
            <fieldset class="border-0 p-0 m-0" @disabled(! $canEdit)>
                <div class="pr-label mb-2">Opening</div>
                <div class="row g-3">
                    <div class="col-lg-4">
                        <label class="form-label small fw-semibold text-muted mb-1">Date and time opened</label>
                        <input type="{{ $canEdit || $aoq->opened_at ? 'datetime-local' : 'text' }}" name="opened_at" class="form-control form-control-sm" value="{{ $aoq->opened_at?->format('Y-m-d\TH:i') }}">
                    </div>
                    <div class="col-lg-8">
                        <label class="form-label small fw-semibold text-muted mb-1">Remarks</label>
                        <textarea name="remarks" class="form-control form-control-sm" rows="1" maxlength="2000" style="resize: none;" placeholder="Printed below the abstract, e.g. why fewer than three quotations">{{ $aoq->remarks }}</textarea>
                    </div>
                </div>
            </fieldset>

            {{-- Printed only: can change after the award --}}
            <div class="pr-label mt-4 mb-2">BAC signatories and approval <span class="text-muted fw-normal text-lowercase">(defaults from Signatories &amp; BAC)</span></div>
            <div class="row g-3">
                <div class="col-sm-6 col-xl-4">
                    <label class="form-label small fw-semibold text-muted mb-1">BAC Chairperson</label>
                    @include('procurement.partials.signatory_select', ['name' => 'bac_chair_name', 'value' => $aoq->bac_chair_name, 'designationField' => '', 'people' => $people, 'placeholder' => 'Search or type a name'])
                </div>
                <div class="col-sm-6 col-xl-4">
                    <label class="form-label small fw-semibold text-muted mb-1">BAC Vice-Chairperson</label>
                    @include('procurement.partials.signatory_select', ['name' => 'bac_vice_chair_name', 'value' => $aoq->bac_vice_chair_name, 'designationField' => '', 'people' => $people, 'placeholder' => 'Search or type a name'])
                </div>
                <div class="col-sm-6 col-xl-4">
                    <label class="form-label small fw-semibold text-muted mb-1">Approved by (HOPE)</label>
                    @include('procurement.partials.signatory_select', ['name' => 'hope_name', 'value' => $aoq->hope_name, 'designationField' => '', 'people' => $people, 'placeholder' => 'Search or type a name'])
                </div>
                @foreach ($members->pad(max($members->count() + 1, 3), null) as $k => $member)
                    <div class="col-sm-6 col-xl-4">
                        <label class="form-label small fw-semibold text-muted mb-1">BAC Member {{ $k + 1 }}</label>
                        @include('procurement.partials.signatory_select', ['name' => 'bac_members[]', 'value' => $member, 'designationField' => '', 'people' => $people, 'placeholder' => 'Blank: not printed'])
                    </div>
                @endforeach
            </div>
            <div class="d-flex justify-content-end border-top pt-3 mt-3">
                <button type="submit" class="btn btn-sm btn-success px-3"><i class="fas fa-save me-1"></i> Save {{ $canEdit ? 'Details' : 'Names' }}</button>
            </div>
        </form>
    </div>
</div>
</div>{{-- one card --}}

<div id="modal-body"></div>
@endsection

@push('script')
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function () {
    const csrf = '{{ csrf_token() }}';
    let isModalOpen = false;

    function errorText(xhr) {
        const res = xhr.responseJSON || {};
        return res.errors ? Object.values(res.errors)[0][0]
            : (res.message && res.message !== 'Server Error' ? res.message : 'Something went wrong (error ' + xhr.status + '). Please tell the system administrator.');
    }

    function refreshView() {
        const y = window.scrollY;
        return $.get(window.location.href).done(function (html) {
            const page = $('<div>').append($.parseHTML(html));
            if (!page.find('#aoq_matrix').length) { window.location.reload(); return; }
            $('#aoq_header').replaceWith(page.find('#aoq_header'));
            $('#aoq_matrix').replaceWith(page.find('#aoq_matrix'));
            window.scrollTo(0, y);
        }).fail(() => window.location.reload());
    }

    function post(url, data, method = 'POST') {
        const body = typeof data === 'string' ? data + '&_method=' + method : Object.assign({ _token: csrf, _method: method }, data || {});
        return $.ajax({ url: url, type: 'POST', data: body, headers: { Accept: 'application/json' } });
    }

    $('#form_aoq').on('submit', function (e) {
        e.preventDefault();
        post('{{ route("procurement.aoqs.header", $aoq) }}', $(this).serialize())
            .done((res) => { toastr.success(res.message, 'Success'); refreshView(); })
            .fail((xhr) => toastr.error(errorText(xhr), 'Error'));
    });

    // ---------- Suppliers ----------
    $(document).on('click', '.js-supplier', function () {
        if (isModalOpen) return;
        isModalOpen = true;
        const id = $(this).data('id');
        $.get('{{ route("procurement.aoqs.suppliers.entry", $aoq) }}' + (id ? '?id=' + id : ''), function (html) {
            $('#modal-body').html(html);
            const el = document.getElementById('AOQ_SUPPLIER_MODAL');
            new bootstrap.Modal(el).show();
            el.addEventListener('shown.bs.modal', () => setTimeout(() => $('#form_aoq_supplier [name=name]').trigger('focus')));
            el.addEventListener('hidden.bs.modal', function () { isModalOpen = false; $('#modal-body').html(''); });
        }).fail(function (xhr) { isModalOpen = false; toastr.error(errorText(xhr), 'Error'); });
    });

    $(document).off('submit', '#form_aoq_supplier').on('submit', '#form_aoq_supplier', function (e) {
        e.preventDefault();
        const btn = $('#btn_save_supplier').prop('disabled', true);
        post('{{ route("procurement.aoqs.suppliers.store", $aoq) }}', $(this).serialize())
            .done(function (res) {
                toastr.success(res.message, 'Success');
                bootstrap.Modal.getInstance(document.getElementById('AOQ_SUPPLIER_MODAL')).hide();
                refreshView();
            })
            .fail(function (xhr) { btn.prop('disabled', false); toastr.error(errorText(xhr), 'Error'); });
    });

    $(document).on('click', '.js-remove-supplier', function () {
        const id = $(this).data('id');
        Swal.fire({ title: 'Remove ' + $(this).data('name') + '?', text: 'Its quotations are removed from the abstract.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Remove', reverseButtons: true })
            .then((r) => {
                if (!r.isConfirmed) return;
                post('{{ route("procurement.aoqs.suppliers.destroy", $aoq) }}', { id: id }, 'DELETE')
                    .done((res) => { toastr.success(res.message, 'Success'); refreshView(); })
                    .fail((xhr) => toastr.error(errorText(xhr), 'Error'));
            });
    });

    // ---------- Workflow ----------
    $(document).on('click', '#btn_print', () => window.open('{{ route("procurement.aoqs.print", $aoq) }}', '_blank'));

    $(document).on('click', '#btn_award', function () {
        const ties = {};
        let unpicked = 0;
        $('.js-tie').each(function () { if (this.value) ties[$(this).data('item')] = this.value; else unpicked++; });
        if (unpicked) { toastr.warning('Choose the supplier for each tied item first.'); return; }
        Swal.fire({
            title: 'Award this abstract?',
            html: 'Each item goes to its lowest responsive quotation within the ABC. Items without one fail. The quotations are then locked; reopen to correct them.',
            icon: 'question', showCancelButton: true, confirmButtonColor: '#198754', confirmButtonText: 'Award', reverseButtons: true
        }).then((r) => {
            if (!r.isConfirmed) return;
            post('{{ route("procurement.aoqs.award", $aoq) }}', { ties: ties })
                .done((res) => { toastr.success(res.message, 'Success'); setTimeout(() => window.location.reload(), 600); })
                .fail((xhr) => toastr.error(errorText(xhr), 'Error'));
        });
    });

    $(document).on('click', '#btn_reopen', function () {
        Swal.fire({ title: 'Reopen this abstract?', text: 'The award is cleared until it is awarded again.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#f59e0b', confirmButtonText: 'Reopen', reverseButtons: true })
            .then((r) => {
                if (!r.isConfirmed) return;
                post('{{ route("procurement.aoqs.reopen", $aoq) }}', {})
                    .done((res) => { toastr.success(res.message, 'Success'); setTimeout(() => window.location.reload(), 600); })
                    .fail((xhr) => toastr.error(errorText(xhr), 'Error'));
            });
    });

    $(document).on('click', '#btn_delete', function () {
        Swal.fire({ title: 'Delete this abstract?', text: 'Its suppliers and quotations are deleted; the RFQ can then be cancelled or abstracted again.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Delete', reverseButtons: true })
            .then((r) => {
                if (!r.isConfirmed) return;
                post('{{ route("procurement.aoqs.destroy", $aoq) }}', {}, 'DELETE')
                    .done((res) => { toastr.success(res.message, 'Success'); setTimeout(() => window.location.href = '{{ route("procurement.aoqs.index") }}', 600); })
                    .fail((xhr) => toastr.error(errorText(xhr), 'Error'));
            });
    });
});
</script>
@endpush
