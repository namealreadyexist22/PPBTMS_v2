@extends('BackEnd.layouts.master')

@section('content')
@php
    use App\Support\Money;
    $qty = fn ($q) => rtrim(rtrim(number_format((float) $q, 2), '0'), '.');
    $cents = fn (?int $c) => $c === null ? '—' : Money::format(Money::fromCents($c));
    $rfq = $aoq->rfq;
    $suppliers = $aoq->suppliers;
    $pdf = fn ($type, $owner) => route('procurement.aoqs.attachment', [$aoq, $type, $owner->id]);
@endphp

<style>
    .twg-card { border-radius: 12px; }
    .twg-label { font-size: .7rem; text-transform: uppercase; letter-spacing: .5px; font-weight: 600; color: var(--bs-secondary-color); }
    .twg-specs { white-space: pre-line; }
    .twg-offer.ok { background-color: var(--bs-success-bg-subtle); }
    .twg-offer.no { background-color: var(--bs-danger-bg-subtle); }
</style>

<div class="mb-3">
    <a href="{{ route('procurement.twg.index') }}" class="text-decoration-none small text-muted"><i class="fas fa-arrow-left me-1"></i> Back to TWG Evaluation</a>
</div>

<form id="form_twg_eval" autocomplete="off">
<div class="card border-0 shadow-sm twg-card mb-3">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
            <div>
                <div class="twg-label">Technical evaluation · {{ $rfq->procurementMode?->name ?? '—' }} · {{ $aoq->region->label() }}</div>
                <h4 class="fw-bold mb-1">
                    {{ $aoq->title() }}
                    <span class="badge rounded-pill bg-{{ $aoq->status->color() }}-subtle text-{{ $aoq->status->color() }}-emphasis border border-{{ $aoq->status->color() }}-subtle align-middle" style="font-size: .7rem;">{{ $aoq->status->label() }}</span>
                </h4>
                <div class="text-muted">{{ $rfq->label() }} · PR {{ $rfq->requests()->pluck('request_no')->filter()->implode(', ') }}</div>
                <div class="small text-muted mt-1">Evaluators: {{ $aoq->evaluators->pluck('fullname')->implode(', ') }} · sent {{ $aoq->sent_to_twg_at?->format('M d, Y g:i A') }}</div>
            </div>
            @if ($canEval)
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-outline-danger" id="btn_return"><i class="fas fa-undo me-1"></i> Return to BAC</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn_save"><i class="fas fa-save me-1"></i> Save</button>
                    <button type="button" class="btn btn-sm btn-success" id="btn_complete"><i class="fas fa-clipboard-check me-1"></i> Complete Evaluation</button>
                </div>
            @endif
        </div>

        @unless ($canEval)
            <div class="alert alert-secondary border-0 small mt-3 mb-0">
                @if ($aoq->evaluated_at)
                    Evaluated by {{ $aoq->evaluator?->fullname }} {{ $aoq->evaluated_at->format('M d, Y g:i A') }}. It is now {{ $aoq->status->label() }}; shown read-only.
                @else
                    It is {{ $aoq->status->label() }} with the BAC; shown read-only.
                @endif
            </div>
        @endunless

        {{-- The suppliers and their whole quotations --}}
        <div class="twg-label mt-4 mb-2">Suppliers</div>
        <div class="d-flex flex-wrap gap-2">
            @foreach ($suppliers as $s)
                <div class="border rounded-3 px-3 py-2 small" style="min-width: 220px;">
                    <div class="fw-semibold">{{ $s->name }}</div>
                    @if ($s->address)<div class="text-muted">{{ $s->address }}</div>@endif
                    @unless ($s->is_responsive)<span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle">Not responsive{{ $s->remarks ? ': ' . $s->remarks : '' }}</span>@endunless
                    @if ($s->attachment_path)
                        <a href="{{ $pdf('supplier', $s) }}" target="_blank" class="d-block text-decoration-none mt-1"><i class="fas fa-file-pdf text-danger me-1"></i>Quotation / proposal</a>
                    @else
                        <div class="text-muted fst-italic mt-1">No quotation PDF attached</div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Item by item: the specs asked, against each offer (lowest first) --}}
@if ($lot)
    {{-- Per lot: all items to one supplier --}}
    @php
        // Lowest lot total first (incomplete lots last); each item lists the suppliers in this order too
        $lotSuppliers = $suppliers->sortBy(fn ($s) => $lot['suppliers'][$s->id]['total_cents'] ?? PHP_INT_MAX)->values();
        $lotEligible = fn ($s) => $s->is_responsive && $lot['suppliers'][$s->id]['total_cents'] !== null && $lot['suppliers'][$s->id]['total_cents'] <= $lot['abc_cents'];
        $lotTotals = $lotSuppliers->filter($lotEligible)->map(fn ($s) => $lot['suppliers'][$s->id]['total_cents'])->unique()->values();
        $lotRanks = $lotSuppliers->filter($lotEligible)->mapWithKeys(fn ($s) => [$s->id => $lotTotals->search($lot['suppliers'][$s->id]['total_cents']) + 1]);
    @endphp
    <div class="alert alert-light border small mb-3">
        <div class="fw-semibold mb-1"><i class="fas fa-trophy text-success me-1"></i>Who wins: awarded per lot</div>
        This RFQ is awarded as <b>one lot</b>: all the items go to a single supplier. The TWG only marks each offer <b>Yes</b> or <b>No</b> against the specifications.
        On the award, the lot goes to the supplier with the <b>lowest lot total</b> that quoted <b>every item</b>, has <b>every offer marked Yes</b>, and is within the total ABC.
        One offer marked No takes the supplier out of the lot. Every item lists the suppliers in the lot's order (lowest lot total first), so check them from the top.
    </div>
    <div class="card border-0 shadow-sm twg-card mb-3">
        <div class="card-body p-4">
            <div class="twg-label mb-2">The lot · total ABC {{ $cents($lot['abc_cents']) }}</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle small mb-0">
                    <thead class="table-light"><tr class="text-nowrap"><th style="width: 70px;">Rank</th><th>Supplier</th><th class="text-end">Lot total</th><th>Offers checked</th></tr></thead>
                    <tbody>
                        @foreach ($lotSuppliers as $s)
                            @php
                                $st = $lot['suppliers'][$s->id];
                                $eligible = $lotEligible($s);
                                $lotRank = $lotRanks[$s->id] ?? null;
                            @endphp
                            <tr class="js-lot-supplier" data-id="{{ $s->id }}" data-name="{{ $s->name }}" data-total="{{ $st['total_cents'] }}" data-eligible="{{ $eligible ? 1 : 0 }}">
                                <td>
                                    @if ($lotRank === 1)<span class="badge bg-success">1 · lowest</span>@elseif ($lotRank)<span class="badge bg-light text-dark border">{{ $lotRank }}</span>@else<span class="text-muted">—</span>@endif
                                </td>
                                <td class="fw-semibold">{{ $s->name }}</td>
                                <td class="text-end text-nowrap fw-semibold">{{ $st['total_cents'] === null ? '—' : $cents($st['total_cents']) }}</td>
                                <td>
                                    @if (! $s->is_responsive)
                                        <span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle">Not responsive</span>
                                    @elseif ($st['total_cents'] === null)
                                        <span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle">Incomplete lot: not every item quoted</span>
                                    @elseif (! $eligible)
                                        <span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle">Lot over the ABC</span>
                                    @else
                                        <span class="js-lot-progress text-muted"></span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="js-lot-outcome alert border-0 small py-2 px-3 mt-2 mb-0"></div>
        </div>
    </div>
@else
<div class="alert alert-light border small mb-3">
    <div class="fw-semibold mb-1"><i class="fas fa-trophy text-success me-1"></i>Who wins an item</div>
    The TWG does not pick the winner: it only marks each offer <b>Yes</b> or <b>No</b> against the specifications.
    On the award, each item goes to the <b>lowest-priced offer marked Yes</b> (from a responsive supplier, within the ABC).
    If the lowest is marked No, the next lowest marked Yes wins; if none is Yes, the item fails.
    Offers are listed lowest first, so check them from the top; the line under each item shows who it goes to.
</div>
@endif
@foreach ($rows as $i => $row)
    @php
        $line = $row['item']->line;
        // Cheapest first; ranked by price among the offers that can be awarded on price (responsive, within the ABC),
        // whatever the specs finding, so the TWG checks the lowest first
        // Per lot: the lot's order instead (lowest lot total first), so every item lists the suppliers alike
        $byPrice = $lot ? $lotSuppliers : $suppliers->sortBy(fn ($s) => $row['quotes'][$s->id]['total_cents'] ?? PHP_INT_MAX)->values();
        $rankable = $byPrice->filter(fn ($s) => $s->is_responsive && $row['quotes'][$s->id]['total_cents'] !== null && $row['quotes'][$s->id]['total_cents'] <= $row['abc_cents']);
        $priceRank = $rankable->map(fn ($s) => $row['quotes'][$s->id]['total_cents'])->unique()->values()->flip();
    @endphp
    <div class="card border-0 shadow-sm twg-card mb-3">
        <div class="card-body p-4">
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="twg-label">Item {{ $i + 1 }} · {{ $qty($line->quantity) }} {{ $line->unit }} · ABC {{ $cents($row['abc_cents']) }}</div>
                    <div class="fw-bold mb-2">{{ $line->description }}</div>
                    <div class="twg-label mb-1">Specifications required</div>
                    <div class="small border rounded-3 p-2 bg-body-tertiary">
                        @if ($line->specifications)
                            @include('procurement.partials.specs', ['text' => $line->specifications])
                        @else
                            No specifications on the PR.
                        @endif
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle small mb-0">
                            <thead class="table-light">
                                <tr class="text-nowrap"><th style="width: 70px;">Rank</th><th>Supplier</th><th class="text-end">Offer</th><th>Brand / specs offered</th><th style="width: 190px;">Meets the specs?</th><th style="min-width: 180px;">Remarks</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($byPrice as $s)
                                    @php $q = $row['quotes'][$s->id]; $quote = $q['quote']; $rank = isset($priceRank[$q['total_cents']]) && $rankable->contains('id', $s->id) ? $priceRank[$q['total_cents']] + 1 : null; @endphp
                                    @if ($q['issue'] === 'No quote')
                                        <tr><td class="text-muted">—</td><td class="fw-semibold">{{ $s->name }}</td><td colspan="4" class="text-muted">No quote</td></tr>
                                        @continue
                                    @endif
                                    <tr class="twg-offer js-offer {{ $quote->is_compliant === true ? 'ok' : ($quote->is_compliant === false ? 'no' : '') }}"
                                        data-name="{{ $s->name }}" data-supplier="{{ $s->id }}" data-total="{{ $q['total_cents'] }}" data-eligible="{{ $rank ? 1 : 0 }}"
                                        data-verdict="{{ $quote->is_compliant === null ? '' : (int) $quote->is_compliant }}">
                                        <td class="text-nowrap">
                                            @if ($lot)
                                                {{-- The supplier's lot rank: the lot total counts, not this item's price --}}
                                                @php $lr = $lotRanks[$s->id] ?? null; @endphp
                                                @if ($lr === 1)
                                                    <span class="badge bg-success" title="Lowest lot total: check its offers first">Lot 1</span>
                                                @elseif ($lr)
                                                    <span class="badge bg-light text-dark border" title="Lot rank">Lot {{ $lr }}</span>
                                                @else
                                                    <span class="text-muted" title="Not in the lot (not responsive, incomplete or over the ABC)">—</span>
                                                @endif
                                            @elseif ($rank === 1)
                                                <span class="badge bg-success" title="Lowest offer: check it first">1 · lowest</span>
                                            @elseif ($rank)
                                                <span class="badge bg-light text-dark border">{{ $rank }}</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="fw-semibold">{{ $s->name }}@unless ($s->is_responsive)<div class="fw-normal text-danger">not responsive</div>@endunless</td>
                                        <td class="text-end text-nowrap">
                                            <div class="fw-semibold">{{ $cents($q['total_cents']) }}</div>
                                            <div class="text-muted">@ {{ number_format((float) $quote->unit_price, 2) }}</div>
                                            @if ($q['total_cents'] > $row['abc_cents'])<span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle">Over ABC</span>@endif
                                        </td>
                                        <td>
                                            {{ $quote->brand ?: '—' }}
                                            @if ($quote->attachment_path)
                                                <a href="{{ $pdf('quote', $quote) }}" target="_blank" class="d-block text-decoration-none"><i class="fas fa-file-pdf text-danger me-1"></i>Spec sheet</a>
                                            @elseif ($s->attachment_path)
                                                <div class="text-muted fst-italic">see the quotation PDF</div>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($canEval)
                                                <div class="btn-group btn-group-sm w-100" role="group" aria-label="Meets the specs?">
                                                    <input type="radio" class="btn-check js-verdict" name="findings[{{ $quote->id }}][is_compliant]" id="v{{ $quote->id }}_1" value="1" @checked($quote->is_compliant === true)>
                                                    <label class="btn btn-outline-success" for="v{{ $quote->id }}_1">Yes</label>
                                                    <input type="radio" class="btn-check js-verdict" name="findings[{{ $quote->id }}][is_compliant]" id="v{{ $quote->id }}_0" value="0" @checked($quote->is_compliant === false)>
                                                    <label class="btn btn-outline-danger" for="v{{ $quote->id }}_0">No</label>
                                                </div>
                                            @else
                                                {{ $quote->is_compliant === null ? '—' : ($quote->is_compliant ? 'Yes' : 'No') }}
                                            @endif
                                        </td>
                                        <td>
                                            @if ($canEval)
                                                <input type="text" name="findings[{{ $quote->id }}][twg_remarks]" class="form-control form-control-sm" maxlength="1000" value="{{ $quote->twg_remarks }}" placeholder="e.g. RAM 8 GB, 16 GB required">
                                            @else
                                                {{ $quote->twg_remarks ?: '—' }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($lot)
                        <div class="small text-muted mt-2"><i class="fas fa-layer-group me-1"></i>Awarded per lot: see who gets the lot above.</div>
                    @else
                        {{-- Who it goes to on the award, from the findings (filled in by the script below) --}}
                        <div class="js-outcome alert border-0 small py-2 px-3 mt-2 mb-0"></div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endforeach

<div class="card border-0 shadow-sm twg-card mb-3">
    <div class="card-body p-4">
        <label class="twg-label mb-1" for="technical_comment">Technical comment</label>
        @if ($canEval)
            <textarea name="technical_comment" id="technical_comment" class="form-control form-control-sm" rows="3" maxlength="2000" placeholder="Printed on the AOQ's Technical comment row, e.g. All offers comply except ...">{{ $aoq->technical_comment }}</textarea>
            <div class="form-text">Completing the evaluation signs you as "Evaluated by" on the AOQ.</div>
        @else
            <div class="small">{{ $aoq->technical_comment ?: '—' }}</div>
        @endif
    </div>
</div>
</form>
@endsection

@push('script')
<script type="text/javascript">
// Under each item: who it goes to on the award, from the findings so far (the same rule as the award:
// the lowest-priced offer marked Yes, from a responsive supplier within the ABC)
document.addEventListener('DOMContentLoaded', function () {
    const peso = (cents) => '₱' + (cents / 100).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const esc = (s) => $('<div>').text(s).html();
    const verdictOf = (row) => {
        const checked = row.find('.js-verdict:checked');
        return checked.length ? checked.val() : String(row.data('verdict'));
    };

    function outcome(card) {
        const offers = card.find('.js-offer').filter((_, r) => $(r).data('eligible') == 1).map((_, r) => {
            const row = $(r);
            return { name: row.data('name'), total: Number(row.data('total')), verdict: verdictOf(row) };
        }).get().sort((a, b) => a.total - b.total);

        let html, tone;
        if (!offers.length) {
            [tone, html] = ['danger', '<i class="fas fa-times-circle me-1"></i>No offer can be awarded on price (none from a responsive supplier within the ABC): <b>the item fails</b>.'];
        } else {
            const open = offers.find((o) => o.verdict === '');
            const winner = offers.find((o) => o.verdict === '1');
            if (open && (!winner || open.total <= winner.total)) {
                [tone, html] = ['warning', '<i class="fas fa-search me-1"></i>Check <b>' + esc(open.name) + '</b> (' + peso(open.total) + ') next: the lowest offer not yet marked. If it meets the specs, it gets the item.'];
            } else if (winner) {
                const tied = offers.filter((o) => o.verdict === '1' && o.total === winner.total);
                [tone, html] = tied.length > 1
                    ? ['info', '<i class="fas fa-equals me-1"></i>Tie at ' + peso(winner.total) + ' between ' + tied.map((o) => '<b>' + esc(o.name) + '</b>').join(' and ') + ': the BAC picks on the award.']
                    : ['success', '<i class="fas fa-trophy me-1"></i>Goes to <b>' + esc(winner.name) + '</b> on the award (' + peso(winner.total) + '): the lowest offer that meets the specs.'];
            } else {
                [tone, html] = ['danger', '<i class="fas fa-times-circle me-1"></i>No offer meets the specs: <b>the item fails</b> on the award.'];
            }
        }
        card.find('.js-outcome').attr('class', 'js-outcome alert alert-' + tone + ' border-0 small py-2 px-3 mt-2 mb-0').html(html);
    }

    // Per lot: the lot goes to the lowest lot total whose every offer is marked Yes
    function lotOutcome() {
        const box = $('.js-lot-outcome');
        if (!box.length) return;
        const suppliers = $('.js-lot-supplier').map((_, r) => {
            const row = $(r);
            const verdicts = $('.js-offer[data-supplier="' + row.data('id') + '"]').map((_, o) => verdictOf($(o))).get();
            const yes = verdicts.filter((v) => v === '1').length, no = verdicts.filter((v) => v === '0').length;
            const open = verdicts.length - yes - no;
            row.find('.js-lot-progress').html(
                no ? '<span class="text-danger"><i class="fas fa-times-circle me-1"></i>' + no + ' offer(s) do not meet the specs: out of the lot</span>'
                   : (open ? yes + ' of ' + verdicts.length + ' marked Yes, ' + open + ' to check' : '<span class="text-success"><i class="fas fa-check-circle me-1"></i>All ' + yes + ' offers meet the specs</span>'));
            return { name: row.data('name'), total: Number(row.data('total')), eligible: row.data('eligible') == 1, out: no > 0, open: open > 0 };
        }).get().filter((s) => s.eligible && !s.out).sort((a, b) => a.total - b.total);

        let html, tone;
        const first = suppliers[0];
        if (!first) {
            [tone, html] = ['danger', '<i class="fas fa-times-circle me-1"></i>No supplier can get the lot (every item quoted, every offer meeting the specs, within the total ABC): <b>the lot fails</b> on the award.'];
        } else if (first.open) {
            [tone, html] = ['warning', '<i class="fas fa-search me-1"></i>Check <b>' + esc(first.name) + '</b>\'s offers next (lot total ' + peso(first.total) + ', the lowest still in): if they all meet the specs, it gets the whole lot.'];
        } else {
            const tied = suppliers.filter((s) => !s.open && s.total === first.total);
            [tone, html] = tied.length > 1
                ? ['info', '<i class="fas fa-equals me-1"></i>Lot tie at ' + peso(first.total) + ' between ' + tied.map((s) => '<b>' + esc(s.name) + '</b>').join(' and ') + ': the BAC picks on the award.']
                : ['success', '<i class="fas fa-trophy me-1"></i>The whole lot goes to <b>' + esc(first.name) + '</b> on the award (' + peso(first.total) + '): the lowest lot total with every offer meeting the specs.'];
        }
        box.attr('class', 'js-lot-outcome alert alert-' + tone + ' border-0 small py-2 px-3 mt-2 mb-0').html(html);
    }

    $('.js-outcome').each(function () { outcome($(this).closest('.card')); });
    lotOutcome();
    $(document).on('change', '.js-verdict', function () {
        const card = $(this).closest('.card');
        if (card.find('.js-outcome').length) outcome(card);
        lotOutcome();
    });
});
</script>
@if ($canEval)
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function () {
    const csrf = '{{ csrf_token() }}';
    const form = $('#form_twg_eval');

    function errorText(xhr) {
        const res = xhr.responseJSON || {};
        return res.errors ? Object.values(res.errors)[0][0]
            : (res.message && res.message !== 'Server Error' ? res.message : 'Something went wrong (error ' + xhr.status + ').');
    }
    function post(url, data) {
        return $.ajax({ url: url, type: 'POST', data: data + '&_token=' + csrf, headers: { Accept: 'application/json' } });
    }

    form.on('change', '.js-verdict', function () {
        $(this).closest('tr').removeClass('ok no').addClass(this.value === '1' ? 'ok' : 'no');
    });

    $('#btn_save').on('click', function () {
        post('{{ route("procurement.twg.save", $aoq) }}', form.serialize())
            .done((res) => toastr.success(res.message, 'Saved'))
            .fail((xhr) => toastr.error(errorText(xhr), 'Error'));
    });

    $('#btn_complete').on('click', function () {
        Swal.fire({
            title: 'Complete the evaluation?',
            html: 'Offers marked <b>No</b> will not count in the award. The AOQ goes back to the BAC for the award and you are signed as "Evaluated by".',
            icon: 'question', showCancelButton: true, confirmButtonColor: '#198754', confirmButtonText: 'Complete', reverseButtons: true
        }).then((r) => {
            if (!r.isConfirmed) return;
            post('{{ route("procurement.twg.complete", $aoq) }}', form.serialize())
                .done((res) => { toastr.success(res.message, 'Success'); setTimeout(() => window.location.href = '{{ route("procurement.twg.index") }}', 700); })
                .fail((xhr) => toastr.error(errorText(xhr), 'Error'));
        });
    });

    $('#btn_return').on('click', function () {
        Swal.fire({
            title: 'Return to the BAC?', input: 'textarea', inputLabel: 'What should the BAC correct?',
            inputPlaceholder: 'e.g. The spec sheet of GPL Trading for item 2 is missing.', inputAttributes: { maxlength: 1000 },
            showCancelButton: true, confirmButtonColor: '#dc3545', confirmButtonText: 'Return', reverseButtons: true,
            inputValidator: (v) => !v.trim() && 'Say what the BAC should correct.'
        }).then((r) => {
            if (!r.isConfirmed) return;
            // Keep what was found so far, then return
            post('{{ route("procurement.twg.save", $aoq) }}', form.serialize())
                .then(() => post('{{ route("procurement.twg.return", $aoq) }}', $.param({ reason: r.value })))
                .done((res) => { toastr.success(res.message, 'Success'); setTimeout(() => window.location.href = '{{ route("procurement.twg.index") }}', 700); })
                .fail((xhr) => toastr.error(errorText(xhr), 'Error'));
        });
    });
});
</script>
@endif
@endpush
