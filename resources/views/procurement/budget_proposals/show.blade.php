@extends('BackEnd.layouts.master')

@section('content')
@php
    use App\Enums\BudgetProposalStatus;
    $peso = fn ($cents) => number_format($cents / 100, 2);
    $editable = $proposal->status->isEditable();
    $myOfficeIds = $myOffices->pluck('id')->all();
@endphp

<div class="mb-3">
    <a href="{{ route('procurement.budget-proposals.index', ['fy' => $proposal->fiscal_year]) }}" class="text-decoration-none small text-muted"><i class="fas fa-arrow-left me-1"></i> Back to Budget Proposals</a>
</div>

{{-- Swapped in place after each change (no full reload) --}}
<div id="proposal_view">
<div class="card border-0 shadow-sm mb-3" style="border-radius: 12px; overflow: hidden;">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
            <div>
                <div class="small text-muted text-uppercase fw-semibold" style="letter-spacing: .5px;">FY {{ $proposal->fiscal_year }} Budget Proposal</div>
                <h4 class="fw-bold mb-1">{{ $department->acronym ?: $department->name }}
                    <span class="badge {{ $proposal->status->badge() }} align-middle ms-1" style="font-size: .7rem;">{{ $proposal->status->label() }}</span>
                </h4>
                <div class="text-muted small">{{ $department->name }}</div>
                <div class="text-muted small mt-1">
                    @if ($proposal->submitted_at)Submitted {{ $proposal->submitted_at->format('M d, Y') }}@if ($proposal->submitter) by {{ $proposal->submitter->fullname }}@endif @endif
                    @if ($proposal->evaluated_at) · Evaluated {{ $proposal->evaluated_at->format('M d, Y') }}@if ($proposal->evaluator) by {{ $proposal->evaluator->fullname }}@endif @endif
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('procurement.budget-proposals.print', $proposal) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fas fa-print me-1"></i> Print</a>
                @if ($canSetSubmitters)
                    <button class="btn btn-sm btn-outline-secondary" id="btn_submitters" title="Who submits the printed proposal"><i class="fas fa-signature me-1"></i> Submitted by</button>
                @endif
                @if ($canSubmit)
                    <button class="btn btn-sm btn-primary" id="btn_submit"><i class="fas fa-paper-plane me-1"></i> Submit</button>
                @endif
                @if ($canEvaluate)
                    <button class="btn btn-sm btn-success" id="btn_evaluate"><i class="fas fa-check-double me-1"></i> Evaluate</button>
                @endif
                @if ($canReturn)
                    <button class="btn btn-sm btn-outline-warning" id="btn_return"><i class="fas fa-undo me-1"></i> Return</button>
                @endif
            </div>
        </div>

        <div class="row g-3 mt-1 small">
            <div class="col-6 col-md-3"><div class="text-muted">PAPs</div><div class="fw-semibold">{{ $proposal->paps->count() }}</div></div>
            <div class="col-6 col-md-3"><div class="text-muted">Budget lines (not procured)</div><div class="fw-semibold">₱ {{ $peso($lineTotal) }}</div></div>
            <div class="col-12 col-md-6">
                <div class="text-muted">Submitted by (printed)</div>
                <div class="fw-semibold">{{ $submitters->pluck('fullname')->join(', ') ?: 'Not set' }}@if (! $submitterIds && $submitters->isNotEmpty()) <span class="text-muted fw-normal">(department head)</span>@endif</div>
            </div>
        </div>

        @if ($proposal->status === BudgetProposalStatus::Returned && $proposal->remarks)
            <div class="alert alert-warning small mt-3 mb-0"><i class="fas fa-undo me-1"></i> <strong>Returned:</strong> {{ $proposal->remarks }}</div>
        @endif
        @if ($proposal->status === BudgetProposalStatus::Submitted)
            <div class="alert alert-info small mt-3 mb-0">
                <i class="fas fa-hourglass-half me-1"></i>
                @if ($canEvaluate)
                    Give each PAP its code (suggested codes are filled in), <strong>Save codes</strong>, then <strong>Evaluate</strong>. Or <strong>Return</strong> it to the department for changes.
                @else
                    Submitted for evaluation. The evaluator gives the PAP codes; it can no longer be changed unless returned.
                @endif
            </div>
        @endif
        @if ($proposal->status === BudgetProposalStatus::Evaluated)
            <div class="alert alert-success small mt-3 mb-0"><i class="fas fa-lock me-1"></i> Evaluated: the PAPs, budget lines and targets are locked. The evaluator can return it to reopen it.</div>
        @endif
    </div>

    {{-- PAPs per office --}}
    <div class="border-top">
        <div class="card-header bg-white pt-3 pb-2 px-4 d-flex flex-wrap align-items-center gap-2" style="border-bottom: 1px solid #f1f5f9;">
            <h6 class="m-0 fw-bold"><i class="fas fa-list text-muted me-2"></i>Programs / Activities / Projects (PAP)</h6>
            @if ($canEvaluate)
                <button class="btn btn-sm btn-success ms-auto" id="btn_save_codes"><i class="fas fa-save me-1"></i> Save codes</button>
            @endif
        </div>
        <form id="form_codes" autocomplete="off">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle small mb-0">
                <thead class="table-light">
                    <tr class="text-nowrap">
                        <th class="ps-4" style="width: 150px;">PAP Code</th>
                        <th>PAP</th>
                        <th class="text-end" title="What the PPMPs (latest versions, drafts too) put under it">PPMP CO</th>
                        <th class="text-end" title="MOOE and semi-expendable projects of the PPMPs">PPMP MOOE</th>
                        <th class="text-end">Budget lines</th>
                        <th class="text-center">Targets</th>
                        <th class="pe-3" style="width: 44px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($offices as $unit)
                        @php $office = $unit['office']; $paps = $papsByOffice->get($office->id, collect()); $mine = in_array($office->id, $myOfficeIds, true); @endphp
                        <tr class="{{ $unit['depth'] <= 1 ? 'table-success' : 'table-info' }}">
                            <td colspan="7" class="fw-semibold" style="padding-left: {{ 1.5 + $unit['depth'] * 1 }}rem;">
                                {{ $office->name }} @if ($office->code)<span class="text-muted fw-normal">· {{ $office->code }}</span>@endif
                                @if ($mine)
                                    <button type="button" class="btn btn-sm btn-link p-0 ms-2 btn-add-pap" data-office="{{ $office->id }}" data-name="{{ $office->shortName() }}"><i class="fas fa-plus-circle"></i> Add PAP</button>
                                @endif
                            </td>
                        </tr>
                        @foreach ($paps as $pap)
                            @php $edit = $canEditPap($pap); $p = $planned->get($pap->id, ['co' => 0, 'mooe' => 0]); $lines = $pap->budgetLines; @endphp
                            <tr>
                                <td class="ps-4">
                                    @if ($canEvaluate)
                                        <input type="text" name="codes[{{ $pap->id }}]" class="form-control form-control-sm" maxlength="50" value="{{ $pap->code ?? ($suggested[$pap->id] ?? '') }}"
                                               placeholder="e.g. {{ sprintf('%02d', $proposal->fiscal_year % 100) }}-{{ $office->code }}-01" @if (! $pap->code && isset($suggested[$pap->id])) title="Suggested" style="background: #fffbeb;" @endif>
                                    @else
                                        <span class="fw-semibold">{{ $pap->code }}</span>@unless ($pap->code)<span class="text-muted fst-italic">no code yet</span>@endunless
                                    @endif
                                </td>
                                <td>
                                    {{ $pap->title }}
                                    @if ($pap->ppmp_paps_count)<span class="badge bg-light text-muted border ms-1" title="Used by {{ $pap->ppmp_paps_count }} PPMP version(s)"><i class="fas fa-clipboard-list"></i></span>@endif
                                </td>
                                <td class="text-end text-nowrap">{{ $p['co'] ? $peso($p['co']) : '' }}</td>
                                <td class="text-end text-nowrap">{{ $p['mooe'] ? $peso($p['mooe']) : '' }}</td>
                                <td class="text-end text-nowrap">{{ $lines->isNotEmpty() ? $peso($lines->sum(fn ($l) => \App\Support\Money::toCents($l->amount))) : '' }}</td>
                                <td class="text-center">
                                    <a href="{{ route('procurement.budget-proposals.performance', [$proposal, $pap]) }}" class="{{ $pap->indicators_count ? '' : 'text-muted' }}" title="Performance targets (Form A)">
                                        <i class="fas fa-bullseye"></i>@if ($pap->indicators_count) {{ $pap->indicators_count }}@endif
                                    </a>
                                </td>
                                <td class="pe-3 text-end">
                                    @if ($edit)
                                        <div class="dropdown">
                                            <button type="button" class="btn btn-sm btn-light border py-0 px-2" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-label="Actions"><i class="fas fa-ellipsis-h"></i></button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow small">
                                                <li><a class="dropdown-item btn-add-line" href="#" data-pap="{{ $pap->id }}"><i class="fas fa-receipt me-2 text-muted"></i>Add budget line</a></li>
                                                <li><a class="dropdown-item" href="{{ route('procurement.budget-proposals.performance', [$proposal, $pap]) }}"><i class="fas fa-bullseye me-2 text-muted"></i>Performance targets</a></li>
                                                <li><a class="dropdown-item btn-rename-pap" href="#" data-id="{{ $pap->id }}" data-title="{{ $pap->title }}"><i class="fas fa-edit me-2 text-primary"></i>Rename</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger btn-delete-pap" href="#" data-id="{{ $pap->id }}"><i class="fas fa-trash-alt me-2"></i>Remove</a></li>
                                            </ul>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                            {{-- Its budget lines (MOOE not procured) --}}
                            {{-- Grouped by expense class, in the Details of MOOE column order, with each class's subtotal --}}
                            @php
                                $lineGroups = $lines->sortBy(fn ($l) => [$l->expenseClass->sort_order, $l->expenseClass->name, $l->location?->name ?? 'zzz', $l->id])
                                    ->groupBy('expense_class_id');
                            @endphp
                            @foreach ($lineGroups as $classLines)
                                <tr class="text-muted">
                                    <td class="ps-4"></td>
                                    <td class="ps-4 fw-semibold"><i class="fas fa-receipt me-1 opacity-50"></i>{{ $classLines->first()->expenseClass->name }}</td>
                                    <td></td><td></td>
                                    <td class="text-end text-nowrap fw-semibold">{{ $peso($classLines->sum(fn ($l) => \App\Support\Money::toCents($l->amount))) }}</td>
                                    <td></td><td></td>
                                </tr>
                            @foreach ($classLines as $line)
                                <tr class="text-muted">
                                    <td class="ps-4"></td>
                                    <td style="padding-left: 3rem;">{{ collect([$line->description, $line->location?->name, $line->fundSource->code])->filter()->join(' · ') }}
                                        @if ($line->remarks)<span class="badge bg-light text-muted border ms-1" title="Remarks: {{ $line->remarks }}"><i class="fas fa-comment-alt"></i></span>@endif
                                    </td>
                                    <td></td><td></td>
                                    <td class="text-end text-nowrap">{{ number_format((float) $line->amount, 2) }}</td>
                                    <td></td>
                                    <td class="pe-3 text-end text-nowrap">
                                        @if ($edit)
                                            <a href="#" class="btn-edit-line me-1" data-id="{{ $line->id }}" title="Edit"><i class="fas fa-edit"></i></a>
                                            <a href="#" class="text-danger btn-delete-line" data-id="{{ $line->id }}" title="Remove"><i class="fas fa-times"></i></a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            @endforeach
                        @endforeach
                        @if ($paps->isEmpty())
                            <tr><td colspan="7" class="text-muted fst-italic" style="padding-left: {{ 2.5 + $unit['depth'] * 1 }}rem;">No PAPs{{ $mine ? ' yet: click Add PAP.' : '.' }}</td></tr>
                        @endif
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-5">No offices with an office number under {{ $department->shortName() }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        </form>
        <div class="small text-muted px-4 py-2">
            Each office writes its own PAPs. The PPMPs pick a PAP from here for their CO, MOOE and semi-expendable projects; budget lines are MOOE that is not procured (travel, training, representation, ...).
        </div>
    </div>
</div>
</div>{{-- #proposal_view --}}

{{-- Who submits the printed proposal --}}
<div class="modal fade" id="SUBMITTERS_MODAL" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow" id="form_submitters" novalidate>
            <div class="modal-header bg-light py-3"><h5 class="modal-title fw-bold"><i class="fas fa-signature me-2"></i>Budget Proposal — Submitted by</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4 small">
                <label class="form-label fw-semibold text-muted mb-1">Submitted by <span class="fw-normal">(up to two, in order)</span></label>
                <select class="form-select form-select-sm" multiple id="submitters_users">
                    @foreach (collect($submitterIds)->map(fn ($id) => $users->firstWhere('id', $id))->filter()->merge($users->whereNotIn('id', $submitterIds)) as $u)
                        <option value="{{ $u->id }}" @selected(in_array($u->id, $submitterIds))>{{ $u->fullname }}{{ $u->designation ? ' — ' . $u->designation : '' }}</option>
                    @endforeach
                </select>
                <div class="form-text">None picked: the department head{{ $department->head ? ' (' . $department->head->fullname . ')' : '' }} submits it. Evaluated, Recommended and Approved by are set on Signatories &amp; BAC.</div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-primary px-3"><i class="fas fa-save me-1"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-body"></div>
@endsection

@push('script')
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function () {
    const csrf = '{{ csrf_token() }}';
    // Reload just the proposal (PAPs, totals, status and buttons), keeping the scroll position
    function reload() {
        const view = $('#proposal_view').css({ opacity: .6, pointerEvents: 'none' });
        $.get(window.location.href)
            .done(function (html) {
                const fresh = $('<div>').append($.parseHTML(html)).find('#proposal_view');
                if (!fresh.length) { window.location.reload(); return; }
                view.replaceWith(fresh);
            })
            .fail(() => window.location.reload())
            .always(() => $('#proposal_view').css({ opacity: '', pointerEvents: '' }));
    }
    const fail = (xhr) => { const res = xhr.responseJSON || {}; toastr.error(res.errors ? Object.values(res.errors)[0][0] : (res.message || 'Something went wrong.'), 'Error'); };
    const send = (url, data, method = 'POST') => $.ajax({ url: url, type: 'POST', data: Object.assign({ _token: csrf, _method: method }, data || {}), headers: { Accept: 'application/json' } })
        .done((res) => { toastr.success(res.message, 'Success'); reload(); }).fail(fail);

    // ----- PAPs -----
    $(document).on('click', '.btn-add-pap', function () {
        const office = $(this).data();
        Swal.fire({ title: 'Add PAP — ' + office.name, input: 'text', inputLabel: 'Title of the program / activity / project', inputPlaceholder: 'e.g. ICT Infrastructure Management',
                    showCancelButton: true, confirmButtonText: 'Add', inputValidator: (v) => !v.trim() && 'Write the PAP title.' })
            .then((r) => { if (r.isConfirmed) send('{{ route("procurement.budget-proposals.paps.store", $proposal) }}', { office_id: office.office, title: r.value }); });
    });
    $(document).on('click', '.btn-rename-pap', function (e) {
        e.preventDefault();
        const pap = $(this).data();
        Swal.fire({ title: 'Rename PAP', input: 'text', inputValue: pap.title, showCancelButton: true, confirmButtonText: 'Save', inputValidator: (v) => !v.trim() && 'Write the PAP title.' })
            .then((r) => { if (r.isConfirmed) send('{{ route("procurement.budget-proposals.paps.store", $proposal) }}', { id: pap.id, title: r.value }); });
    });
    $(document).on('click', '.btn-delete-pap', function (e) {
        e.preventDefault();
        const id = $(this).data('id');
        Swal.fire({ title: 'Remove this PAP?', text: 'Its budget lines and targets go with it. A PAP used by a PPMP cannot be removed.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Remove', reverseButtons: true })
            .then((r) => { if (r.isConfirmed) send('{{ route("procurement.budget-proposals.paps.destroy", $proposal) }}', { id: id }, 'DELETE'); });
    });

    // ----- Budget lines -----
    function openLine(params) {
        $.get('{{ route("procurement.budget-proposals.lines.entry", $proposal) }}', params)
            .done(function (html) {
                $('#modal-body').html(html);
                const el = document.getElementById('BUDGET_LINE_MODAL');
                new bootstrap.Modal(el).show();
                el.addEventListener('hidden.bs.modal', () => $('#modal-body').html(''));
            }).fail(fail);
    }
    $(document).on('click', '.btn-add-line', function (e) { e.preventDefault(); openLine({ pap_id: $(this).data('pap') }); });
    $(document).on('click', '.btn-edit-line', function (e) { e.preventDefault(); openLine({ id: $(this).data('id') }); });
    $(document).on('submit', '#form_budget_line', function (e) {
        e.preventDefault();
        const form = $(this);
        form.find('.is-invalid').removeClass('is-invalid');
        $.post('{{ route("procurement.budget-proposals.lines.store", $proposal) }}', form.serialize())
            .done((res) => { toastr.success(res.message, 'Success'); bootstrap.Modal.getInstance(document.getElementById('BUDGET_LINE_MODAL')).hide(); reload(); })
            .fail(function (xhr) {
                const res = xhr.responseJSON || {};
                $.each(res.errors || {}, (k, m) => form.find('[name="' + k + '"]').addClass('is-invalid').siblings('.invalid-feedback').text(m[0]));
                if (!res.errors) toastr.error(res.message || 'Something went wrong.', 'Error');
            });
    });
    $(document).on('click', '.btn-delete-line', function (e) {
        e.preventDefault();
        const id = $(this).data('id');
        Swal.fire({ title: 'Remove this budget line?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Remove', reverseButtons: true })
            .then((r) => { if (r.isConfirmed) send('{{ route("procurement.budget-proposals.lines.destroy", $proposal) }}', { id: id }, 'DELETE'); });
    });

    // ----- Workflow -----
    $(document).on('click', '#btn_submit', function () {
        Swal.fire({ title: 'Submit the Budget Proposal?', text: 'The evaluator gives the PAP codes. It can no longer be changed unless returned. Its budget lines then count against the budget.', icon: 'question', showCancelButton: true, confirmButtonText: 'Submit', reverseButtons: true })
            .then((r) => { if (r.isConfirmed) send('{{ route("procurement.budget-proposals.submit", $proposal) }}'); });
    });
    $(document).on('click', '#btn_save_codes', function () { send('{{ route("procurement.budget-proposals.codes", $proposal) }}', Object.fromEntries(new FormData(document.getElementById('form_codes')))); });
    $(document).on('click', '#btn_evaluate', function () {
        Swal.fire({ title: 'Evaluate the Budget Proposal?', text: 'Save the codes first. Every PAP needs its code; it is then locked.', icon: 'question', showCancelButton: true, confirmButtonText: 'Evaluate', reverseButtons: true })
            .then((r) => { if (r.isConfirmed) send('{{ route("procurement.budget-proposals.evaluate", $proposal) }}'); });
    });
    $(document).on('click', '#btn_return', function () {
        Swal.fire({ title: 'Return to {{ $department->shortName() }}?', input: 'textarea', inputLabel: 'Why is it returned?', showCancelButton: true, confirmButtonText: 'Return', inputValidator: (v) => !v.trim() && 'Please state why.' })
            .then((r) => { if (r.isConfirmed) send('{{ route("procurement.budget-proposals.return", $proposal) }}', { remarks: r.value }); });
    });

    // ----- Submitted by -----
    $(document).on('click', '#btn_submitters', function () {
        const select = $('#submitters_users');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('SUBMITTERS_MODAL')).show();
        if (!select.hasClass('select2-hidden-accessible')) {
            select.select2({ theme: 'bootstrap-5', width: '100%', maximumSelectionLength: 2, placeholder: 'The department head', dropdownParent: $('#SUBMITTERS_MODAL'),
                selectionCssClass: 'select2--small', dropdownCssClass: 'select2--small' });
        }
    });
    $('#form_submitters').on('submit', function (e) {
        e.preventDefault();
        send('{{ route("procurement.budget-proposals.submitters", $proposal) }}', { users: $('#submitters_users').select2('data').map((d) => d.id) })
            .done(() => bootstrap.Modal.getInstance(document.getElementById('SUBMITTERS_MODAL'))?.hide());
    });
});
</script>
@endpush
