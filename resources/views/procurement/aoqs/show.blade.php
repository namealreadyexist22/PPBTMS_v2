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
    $canAward = $aoq->status === AoqStatus::Evaluated;   // after the TWG's evaluation
    // Lowest, ranks and the award only once the TWG has found which offers meet the specs (the TWG page ranks them before)
    $ranked = $canAward || $awarded;
    $pdf = fn ($type, $owner) => route('procurement.aoqs.attachment', [$aoq, $type, $owner->id]);
    $awardCents = $rows->sum(fn ($r) => $r['awarded'] ? $r['quotes'][$r['awarded']]['total_cents'] : 0);
    $failed = $rows->filter(fn ($r) => $r['lowest'] === [])->count();
    $ties = $rows->filter(fn ($r) => count($r['lowest']) > 1)->count();
    $perLot = $lot !== null;   // all items to one supplier: the lowest lot total ($lot: AoqService::lotStanding)
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
                <div class="pr-label">Abstract of Quotations · {{ $rfq->procurementMode?->name ?? '—' }} · {{ $aoq->region->label() }} · Award {{ mb_strtolower($rfq->award_basis->label()) }}</div>
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
                    <button class="btn btn-sm btn-warning" id="btn_twg" data-name="{{ $aoq->evaluated_by_name }}" @disabled($suppliers->isEmpty() || $aoq->evaluators->isEmpty())
                            title="{{ $aoq->evaluators->isEmpty() ? 'Choose the TWG evaluator under "Evaluated by" first' : 'Send to ' . $aoq->evaluated_by_name }}"><i class="fas fa-user-check me-1"></i> Send to TWG</button>
                @elseif ($aoq->status === AoqStatus::ForEvaluation)
                    <button class="btn btn-sm btn-outline-warning" id="btn_reopen" data-title="Recall from the TWG?" data-text="It goes back to draft; the TWG's findings so far are kept. Send it again when ready."><i class="fas fa-undo me-1"></i> Recall</button>
                @else
                    <button class="btn btn-sm btn-outline-warning" id="btn_reopen" data-title="Reopen this abstract?" data-text="It goes back to draft{{ $awarded ? ' and the award is cleared' : '' }}; send it to the TWG again after the corrections."><i class="fas fa-undo me-1"></i> Reopen</button>
                    @if ($canAward)
                        <button class="btn btn-sm btn-success" id="btn_award"><i class="fas fa-trophy me-1"></i> Award</button>
                    @endif
                @endif
            </div>
        </div>

        <div class="row g-2 mt-3">
            <div class="col-6 col-md-3">
                <div class="pr-stat">
                    <div class="pr-label">RFQ</div>
                    <a href="#" class="badge rounded-pill bg-light text-dark border text-decoration-none me-1 js-rfq-details" title="View the RFQ's details">
                        <i class="fas fa-eye ms-1" style="font-size: .7rem;"></i>
                        {{ $rfq->title() }}
                    </a>
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
                    @if ($ranked)
                        <div class="pr-label">{{ $awarded ? 'Awarded' : 'Lowest bids' }}</div>
                        <div class="fw-semibold small">
                            @if ($awarded)
                                {{ $cents($awardCents) }}
                            @else
                                {{ $cents($rows->sum(fn ($r) => $r['lowest'] ? $r['quotes'][$r['lowest'][0]]['total_cents'] : 0) ?: null) }}
                            @endif
                            @if ($failed)<span class="text-danger fw-normal ms-1">· {{ $failed }} failed</span>@endif
                        </div>
                    @else
                        <div class="pr-label">TWG evaluator</div>
                        <div class="fw-semibold small">{{ $aoq->evaluated_by_name ?: '—' }}</div>
                    @endif
                </div>
            </div>
        </div>

        @php $evaluators = $aoq->evaluators->pluck('fullname')->implode(', '); @endphp
        @if ($awarded)
            <div class="alert alert-success d-flex gap-2 align-items-start border-0 small mt-3 mb-0"><i class="fas fa-trophy mt-1"></i><div>Awarded {{ $aoq->awarded_at?->format('M d, Y g:i A') }}. {{ $perLot ? 'All items go as one lot to the lowest lot total that meets the specs.' : 'Each item goes to its lowest responsive quotation that meets the specs.' }} Reopen to correct a quotation.</div></div>
        @elseif ($aoq->status === AoqStatus::ForEvaluation)
            <div class="alert alert-warning d-flex gap-2 align-items-start border-0 small mt-3 mb-0"><i class="fas fa-user-check mt-1"></i><div>With the TWG for technical evaluation since {{ $aoq->sent_to_twg_at?->format('M d, Y g:i A') }}: <b>{{ $evaluators }}</b>. The quotations are locked until it is evaluated or returned.</div></div>
        @elseif ($canAward)
            <div class="alert alert-info d-flex gap-2 align-items-start border-0 small mt-3 mb-0"><i class="fas fa-clipboard-check mt-1"></i><div>Evaluated by the TWG ({{ $aoq->evaluator?->fullname ?? $evaluators }}) {{ $aoq->evaluated_at?->format('M d, Y g:i A') }}. Offers that do not meet the specs do not count. The BAC can now award.@if ($aoq->technical_comment)<div class="mt-1"><b>Technical comment:</b> {{ $aoq->technical_comment }}</div>@endif</div></div>
        @elseif ($aoq->return_reason)
            <div class="alert alert-danger d-flex gap-2 align-items-start border-0 small mt-3 mb-0"><i class="fas fa-undo mt-1"></i><div><b>Returned by the TWG:</b> {{ $aoq->return_reason }}<div class="text-muted">Correct the quotations or attachments, then send it to the TWG again.</div></div></div>
        @endif
    </div>
</div>

<div id="aoq_matrix">
<div class="border-top">
    <div class="card-header bg-white py-3 px-4 d-flex flex-wrap align-items-center gap-2" style="border-bottom: 1px solid #f1f5f9;">
        <h6 class="m-0 fw-bold"><i class="fas fa-table text-muted me-2"></i>Quotations</h6>
        @if ($ranked)
            <span class="small text-muted ms-2 d-none d-md-inline">
                <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">lowest</span>
                @if ($perLot)
                    per lot: the supplier quoted every item, each meets the specs, and its total is within the total ABC
                @else
                    counts when the supplier is responsive, the offer meets the specs and is within the ABC
                @endif
            </span>
        @endif
        {{-- Per lot: one tie pick for the whole lot --}}
        @if ($perLot && $canAward && count($lot['lowest']) > 1)
            <div class="d-flex align-items-center gap-2 small ms-md-3">
                <label class="text-warning-emphasis text-nowrap mb-0" for="tie_lot"><i class="fas fa-equals me-1"></i>Lot tie: the BAC picks</label>
                <select class="form-select form-select-sm js-tie" id="tie_lot" data-item="lot" style="width: 220px;">
                    <option value="">Choose…</option>
                    @foreach ($lot['lowest'] as $sid)<option value="{{ $sid }}">{{ $suppliers->firstWhere('id', $sid)->name }}</option>@endforeach
                </select>
            </div>
        @endif
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
                                        @if ($s->attachment_path)<a href="{{ $pdf('supplier', $s) }}" target="_blank" class="fw-normal small text-decoration-none d-block"><i class="fas fa-file-pdf text-danger me-1"></i>Quotation</a>@endif
                                    </div>
                                    @if ($canEdit)
                                        <button class="btn btn-sm btn-link p-0 text-secondary js-supplier" data-id="{{ $s->id }}" title="Change {{ $s->name }}" aria-label="Change {{ $s->name }}"><i class="fas fa-pen"></i></button>
                                        <button class="btn btn-sm btn-link p-0 ms-1 text-danger js-remove-supplier" data-id="{{ $s->id }}" data-name="{{ $s->name }}" title="Remove" aria-label="Remove {{ $s->name }}"><i class="fas fa-times"></i></button>
                                    @endif
                                </div>
                            </th>
                        @endforeach
                        @if ($ranked)<th class="pe-3" style="min-width: 180px;">AWARD</th>@endif
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
                                    $low = $canAward && in_array($s->id, $row['lowest'], true);
                                    $class = $won ? 'won' : ($low ? 'lowest' : ($ranked && $q['issue'] && $q['issue'] !== 'No quote' ? 'out' : ''));
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
                                        @if ($q['quote']->attachment_path)<a href="{{ $pdf('quote', $q['quote']) }}" target="_blank" class="small text-decoration-none d-block"><i class="fas fa-file-pdf text-danger me-1"></i>Specs</a>@endif
                                        @if ($q['issue'])
                                            <span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle">{{ $q['issue'] }}</span>
                                        @elseif ($won)
                                            <span class="badge bg-success"><i class="fas fa-trophy me-1"></i>Awarded</span>
                                        @elseif ($ranked)
                                            <span class="badge bg-light text-dark border">Rank {{ $q['rank'] }}</span>
                                        @endif
                                        @if ($q['quote']->is_compliant === true)
                                            <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle" title="TWG finding">Meets specs</span>
                                        @elseif ($q['quote']->is_compliant === null && $s->is_responsive)
                                            <span class="badge bg-light text-muted border" title="TWG finding">Not evaluated</span>
                                        @endif
                                        @if ($q['quote']->twg_remarks)<div class="small fst-italic text-muted mt-1" title="TWG remarks"><i class="fas fa-comment-dots me-1"></i>{{ $q['quote']->twg_remarks }}</div>@endif
                                    @endif
                                </td>
                            @endforeach
                            @if ($ranked)
                            <td class="pe-3">
                                @if ($row['awarded'])
                                    <span class="fw-semibold">{{ $suppliers->firstWhere('id', $row['awarded'])->name }}</span>
                                @elseif ($row['lowest'] === [])
                                    <span class="text-danger"><i class="fas fa-times-circle me-1"></i>Failed</span>
                                    <div class="text-muted">{{ $perLot ? 'No supplier\'s lot can be awarded' : 'No responsive quote within the ABC' }}</div>
                                @elseif ($tie && $perLot)
                                    <span class="text-warning-emphasis"><i class="fas fa-equals me-1"></i>Lot tie</span>
                                    <div class="text-muted">picked above, on award</div>
                                @elseif ($tie && $canAward)
                                    <label class="text-warning-emphasis d-block mb-1" for="tie_{{ $row['item']->id }}"><i class="fas fa-equals me-1"></i>Tie: the BAC picks</label>
                                    <select class="form-select form-select-sm js-tie" id="tie_{{ $row['item']->id }}" data-item="{{ $row['item']->id }}">
                                        <option value="">Choose…</option>
                                        @foreach ($row['lowest'] as $sid)
                                            <option value="{{ $sid }}">{{ $suppliers->firstWhere('id', $sid)->name }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <span>{{ $suppliers->firstWhere('id', $row['lowest'][0])->name }}</span>
                                    <div class="text-muted">{{ $perLot ? 'lowest lot total, on award' : 'lowest, on award' }}</div>
                                @endif
                            </td>
                            @endif
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
                                {{-- Per lot: the supplier's lot standing --}}
                                @if ($perLot && $ranked)
                                    @php $standing = $lot['suppliers'][$s->id]; @endphp
                                    <div class="fw-normal mt-1">
                                        @if ($standing['issue'])
                                            <span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle">{{ $standing['issue'] }}</span>
                                        @else
                                            <span class="badge bg-light text-dark border">Lot rank {{ $standing['rank'] }}</span>
                                        @endif
                                    </div>
                                @elseif ($perLot && $quoted->count() < $rows->count() && $quoted->isNotEmpty())
                                    <div class="fw-normal"><span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" title="Per lot: every item must be quoted">Incomplete lot</span></div>
                                @endif
                            </td>
                        @endforeach
                        @if ($ranked)<td class="pe-3 text-nowrap">{{ $awarded ? $cents($awardCents) : '' }}</td>@endif
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
                    {{-- The one TWG member who evaluates it; printed as "Evaluated by" --}}
                    <div class="col-lg-4">
                        <label class="form-label small fw-semibold text-muted mb-1" for="aoq_evaluator">Evaluated by (TWG)</label>
                        @if ($canEdit)
                            <select name="evaluator_id" id="aoq_evaluator" class="form-select form-select-sm" data-placeholder="Choose the TWG evaluator">
                                <option value=""></option>
                                @foreach ($twg as $member)
                                    <option value="{{ $member->id }}" data-sub="{{ $member->designation }}" @selected($aoq->evaluators->contains('id', $member->id))>{{ $member->fullname }}</option>
                                @endforeach
                            </select>
                            @if ($twg->isEmpty())
                                <div class="form-text text-danger">No TWG members yet: give the evaluators the "TWG Evaluation" access in Roles &amp; Permissions.</div>
                            @endif
                        @else
                            <div class="form-control form-control-sm bg-body-tertiary">{{ $aoq->evaluated_by_name ?: '—' }}</div>
                        @endif
                        @if ($aoq->evaluated_by_designation)<div class="form-text">{{ $aoq->evaluated_by_designation }}</div>@endif
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label small fw-semibold text-muted mb-1">Remarks</label>
                        <textarea name="remarks" class="form-control form-control-sm" rows="1" maxlength="2000" style="resize: none;" placeholder="Printed on the Approved Budget row, e.g. PLEASE SEE ATTACHED BID PROPOSAL (SPECIFICATIONS) FOR EVALUATION">{{ $aoq->remarks }}</textarea>
                    </div>
                    @if ($aoq->technical_comment)
                        <div class="col-12 small">
                            <span class="fw-semibold text-muted">Technical comment (TWG):</span> {{ $aoq->technical_comment }}
                        </div>
                    @endif
                </div>
            </fieldset>

            <div class="d-flex flex-wrap align-items-center gap-2 border-top pt-3 mt-3">
                {{-- The printed names: defaults unless changed --}}
                <div class="small text-muted me-auto">
                    <i class="fas fa-signature me-1"></i>Signed by
                    <b class="text-body">{{ $signatories['prepared_by'][0] ?: '—' }}</b> (prepared),
                    <b class="text-body">{{ $signatories['certified_by'][0] ?: '—' }}</b> (certified) and the BAC
                    @if (! $signatories['bac_chair'])<span class="text-danger">· no BAC Chairperson set</span>@endif
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#AOQ_SIGNATORIES_MODAL"><i class="fas fa-signature me-1"></i> Signatories</button>
                @if ($canEdit)
                    <button type="submit" class="btn btn-sm btn-success px-3"><i class="fas fa-save me-1"></i> Save Details</button>
                @endif
            </div>
        </form>
    </div>
</div>
</div>{{-- one card --}}

{{-- The printed names: blank = the default in grey (Signatories & BAC, who prepared it, the RFQ's signatory). Can change after the award. --}}
<div class="modal fade" id="AOQ_SIGNATORIES_MODAL" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <form class="modal-content border-0 shadow" id="form_aoq_signatories" autocomplete="off">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold"><i class="fas fa-signature me-2"></i>Signatories</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 small">
                <p class="text-muted">Names printed on the AOQ. Leave a name blank to use the default shown in grey. "Evaluated by" is the TWG evaluator, chosen on the AOQ.</p>
                <div class="row g-3">
                    @foreach (['prepared_by' => 'Prepared by', 'certified_by' => 'Certified/Corrected by'] as $field => $label)
                        <div class="col-12"><div class="fw-semibold">{{ $label }}</div></div>
                        <div class="col-md-6">
                            <label class="form-label text-muted mb-1">Printed name</label>
                            @include('procurement.partials.signatory_select', ['name' => "{$field}_name", 'value' => $aoq->{"{$field}_name"}, 'designationField' => "{$field}_designation", 'people' => $people,
                                'placeholder' => $defaults[$field][0] ? $defaults[$field][0] . ' (default)' : 'Search or type a name'])
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted mb-1">Designation</label>
                            <input type="text" name="{{ $field }}_designation" class="form-control form-control-sm" maxlength="255" value="{{ $aoq->{"{$field}_designation"} }}" placeholder="{{ $defaults[$field][1] ?: 'Designation' }}">
                        </div>
                    @endforeach

                    <div class="col-12 mt-4"><div class="fw-semibold">Approved by: Bidding &amp; Awards Committee <span class="text-muted fw-normal">— defaults from Signatories &amp; BAC</span></div></div>
                    @foreach (['bac_chair_name' => ['BAC Chairperson', 'bac_chair'], 'bac_vice_chair_name' => ['BAC Vice-Chairperson', 'bac_vice'], 'hope_name' => ['Head of the Procuring Entity', 'hope']] as $field => [$label, $key])
                        <div class="col-md-4">
                            <label class="form-label text-muted mb-1">{{ $label }}</label>
                            @include('procurement.partials.signatory_select', ['name' => $field, 'value' => $aoq->{$field}, 'designationField' => '', 'people' => $people,
                                'placeholder' => $defaults[$key] ? $defaults[$key] . ' (default)' : 'Search or type a name'])
                        </div>
                    @endforeach
                    @foreach ($members->pad(max($members->count() + 1, count($defaults['bac_members']), 3), null) as $k => $member)
                        <div class="col-md-4">
                            <label class="form-label text-muted mb-1">BAC Member {{ $k + 1 }}</label>
                            @include('procurement.partials.signatory_select', ['name' => 'bac_members[]', 'value' => $member, 'designationField' => '', 'people' => $people,
                                'placeholder' => $members->isEmpty() && isset($defaults['bac_members'][$k]) ? $defaults['bac_members'][$k] . ' (default)' : 'Blank: not printed'])
                        </div>
                    @endforeach
                    <div class="col-12 form-text mt-1">The members print as a group: set any of them to replace the default members.</div>
                </div>
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
        // Reloaded: the evaluator also changes "Send to TWG" and the signatories
        post('{{ route("procurement.aoqs.header", $aoq) }}', $(this).serialize())
            .done((res) => { toastr.success(res.message, 'Success'); setTimeout(() => window.location.reload(), 500); })
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
            // Adding: open the supplier search right away
            el.addEventListener('shown.bs.modal', () => setTimeout(() => { if (!$('#form_aoq_supplier [name=id]').val()) $('#aoq_sup_name').select2('open'); }));
            el.addEventListener('hidden.bs.modal', function () { isModalOpen = false; $('#modal-body').html(''); });
        }).fail(function (xhr) { isModalOpen = false; toastr.error(errorText(xhr), 'Error'); });
    });

    $(document).off('submit', '#form_aoq_supplier').on('submit', '#form_aoq_supplier', function (e) {
        e.preventDefault();
        const btn = $('#btn_save_supplier').prop('disabled', true);
        // FormData: carries the PDFs
        $.ajax({ url: '{{ route("procurement.aoqs.suppliers.store", $aoq) }}', type: 'POST', data: new FormData(this), processData: false, contentType: false, headers: { Accept: 'application/json' } })
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

    // The RFQ's details, read-only
    $(document).on('click', '.js-rfq-details', function (e) {
        e.preventDefault();
        if (isModalOpen) return;
        isModalOpen = true;
        $.get('{{ route("procurement.aoqs.rfq", $aoq) }}', function (html) {
            $('#modal-body').html(html);
            const el = document.getElementById('RFQ_DETAILS_MODAL');
            new bootstrap.Modal(el).show();
            el.addEventListener('hidden.bs.modal', function () { isModalOpen = false; $('#modal-body').html(''); });
        }).fail(function (xhr) { isModalOpen = false; toastr.error(errorText(xhr), 'Error'); });
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
            html: @if ($perLot) 'All items go, as one lot, to the supplier with the lowest lot total that quoted every item, meets the specs and is within the total ABC. Without one, the lot fails. The quotations are then locked; reopen to correct them.' @else 'Each item goes to its lowest responsive quotation within the ABC. Items without one fail. The quotations are then locked; reopen to correct them.' @endif,
            icon: 'question', showCancelButton: true, confirmButtonColor: '#198754', confirmButtonText: 'Award', reverseButtons: true
        }).then((r) => {
            if (!r.isConfirmed) return;
            post('{{ route("procurement.aoqs.award", $aoq) }}', { ties: ties })
                .done((res) => { toastr.success(res.message, 'Success'); setTimeout(() => window.location.reload(), 600); })
                .fail((xhr) => toastr.error(errorText(xhr), 'Error'));
        });
    });

    // TWG evaluator ("Evaluated by"): TWG members with their designation and office
    const evaluator = $('#aoq_evaluator');
    if (evaluator.length) {
        evaluator.select2({
            theme: 'bootstrap-5', width: '100%', allowClear: true, placeholder: evaluator.data('placeholder'),
            selectionCssClass: 'select2--small', dropdownCssClass: 'select2--small',
            templateResult: (d) => d.element && d.element.dataset.sub ? $('<div>').append($('<div>').text(d.text), $('<div class="small text-muted">').text(d.element.dataset.sub)) : d.text,
        });
    }

    $(document).on('click', '#btn_twg', function () {
        Swal.fire({
            title: 'Send to the TWG?',
            html: 'It goes to <b>' + $('<div>').text($(this).data('name')).html() + '</b> for the technical evaluation, who is notified. The quotations are locked meanwhile.',
            icon: 'question', showCancelButton: true, confirmButtonColor: '#f59e0b', confirmButtonText: 'Send', reverseButtons: true
        }).then((r) => {
            if (!r.isConfirmed) return;
            post('{{ route("procurement.aoqs.twg", $aoq) }}', {})
                .done((res) => { toastr.success(res.message, 'Success'); setTimeout(() => window.location.reload(), 600); })
                .fail((xhr) => toastr.error(errorText(xhr), 'Error'));
        });
    });

    $('#form_aoq_signatories').on('submit', function (e) {
        e.preventDefault();
        post('{{ route("procurement.aoqs.header", $aoq) }}', $(this).serialize() + '&_token=' + csrf)
            .done((res) => { toastr.success(res.message, 'Saved'); setTimeout(() => window.location.reload(), 500); })
            .fail((xhr) => toastr.error(errorText(xhr), 'Error'));
    });

    $(document).on('click', '#btn_reopen', function () {
        Swal.fire({ title: $(this).data('title'), text: $(this).data('text'), icon: 'warning', showCancelButton: true, confirmButtonColor: '#f59e0b', confirmButtonText: 'Yes', reverseButtons: true })
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
