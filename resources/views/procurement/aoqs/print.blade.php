{{--
    Abstract of Quotation, SRA form: one column per supplier/dealer (its name, then per item its total,
    unit price and brand, or "Invited but no quote"), the technical comment and approved budget rows, the
    Prepared / Evaluated / Certified signatories and the BAC's approval. Once the TWG has evaluated, the lowest
    counting quote per item (once awarded, the awarded one) is underlined.
    Expects: $aoq (with rfq.items.line.request.office/pap, suppliers.quotes), $rows (AoqService::evaluate),
             $signatories (AoqService::signatories: set on the AOQ, else the defaults), $lot (AoqService::lotStanding when awarded per lot, else null).
--}}
@php
    use App\Enums\AoqStatus;
    use App\Support\Money;
    $qty = fn ($q) => rtrim(rtrim(number_format((float) $q, 2), '0'), '.');
    $cents = fn (?int $c) => $c === null ? '' : Money::format(Money::fromCents($c));
    $upper = fn ($s) => mb_strtoupper((string) $s);
    $rfq = $aoq->rfq;
    $suppliers = $aoq->suppliers;
    $awarded = $aoq->status === AoqStatus::Awarded;
    $requests = $rfq->requests();
    $prNos = $requests->pluck('request_no')->filter()->implode(', ');
    // Under "Description of Articles": the requesting office and PAP, then the end user
    $charged = $requests->map(fn ($pr) => collect([$pr->office?->acronym ?: $pr->office?->name, $pr->pap?->code])->filter()->implode(' - '))
        ->filter()->unique()->implode('; ');
    $endUsers = $requests->pluck('requested_by_name')->filter()->map($upper)->unique()->implode('; ');
    $failed = $rows->filter(fn ($r) => $r['lowest'] === [])->keys()->map(fn ($k) => $k + 1);
    $date = ($aoq->opened_at ?? $aoq->created_at)?->format('F d, Y');
    $landscape = $suppliers->count() > 3;
    $members = collect($signatories['bac_members'])->filter(fn ($m) => filled($m));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $aoq->title() }}</title>
    <style>
        @page {
            size: A4 {{ $landscape ? 'landscape' : 'portrait' }}; margin: 10mm 12mm 12mm;
            @bottom-right { content: "Page " counter(page) " of " counter(pages); font: 8.5pt "Times New Roman", serif; }
        }
        * { box-sizing: border-box; }
        body { font-family: "Times New Roman", Times, serif; font-size: 10pt; color: #000; margin: 0; background: #e5e7eb; }
        .sheet { background: #fff; width: {{ $landscape ? '297mm' : '210mm' }}; min-height: {{ $landscape ? '210mm' : '297mm' }}; margin: 12px auto; padding: 10mm 12mm; position: relative; }

        .toolbar { position: sticky; top: 0; z-index: 10; background: #1f2937; color: #fff; padding: 8px 16px; display: flex; gap: 12px; align-items: center; font: 13px Arial, sans-serif; }
        .toolbar button { font-size: 13px; padding: 5px 12px; border-radius: 5px; border: 0; cursor: pointer; }
        .toolbar .primary { background: #2563eb; color: #fff; }
        .toolbar .spacer { flex: 1; }

        .top { display: flex; justify-content: space-between; align-items: flex-start; }
        .head { display: flex; align-items: center; gap: 12px; }
        .head img { height: 76px; }
        .head .t { line-height: 1.25; font-size: 10pt; }
        .head .t b { font-size: 10.5pt; }
        .head .t .title { font-size: 13.5pt; font-weight: bold; margin-top: 3px; }
        .ref { text-align: right; line-height: 1.35; }
        .ref .qr { display: inline-block; width: 60px; height: 60px; }
        .ref .qr img, .ref .qr canvas { width: 60px !important; height: 60px !important; }
        .ref .no { font-size: 12pt; font-weight: bold; }
        .pr { font-weight: bold; margin: 6px 0 4px; font-size: 10.5pt; }

        table.abs { width: 100%; border-collapse: collapse; font-size: 9pt; }
        table.abs th, table.abs td { border: 1px solid #000; padding: 4px 5px; vertical-align: top; }
        table.abs th { text-align: center; vertical-align: middle; font-weight: bold; }
        table.abs td.c { text-align: center; }
        table.abs .specs { white-space: pre-line; font-style: italic; margin-top: 8px; }
        table.abs td.q { text-align: center; }
        table.abs td.q .total { font-weight: bold; }
        table.abs td.q .low { text-decoration: underline; }
        table.abs td.q .unit { margin-top: 8px; }
        table.abs .tag { font-size: 7.5pt; font-style: italic; }
        table.abs td.q.out { color: #555; }
        table.abs tr.spacer td { height: 9px; padding: 0; }
        table.abs .note { text-align: center; font-style: italic; vertical-align: middle; }

        .failed { margin-top: 4px; font-size: 9pt; }
        .signs { display: flex; justify-content: space-between; gap: 24px; margin-top: 18px; font-size: 10pt; page-break-inside: avoid; }
        .signs .s { flex: 1; }
        .signs .n { margin-top: 30px; font-weight: bold; text-decoration: underline; text-transform: uppercase; font-size: 11pt; }
        .bac { margin-top: 34px; font-size: 10pt; page-break-inside: avoid; }
        .bac .who { font-weight: bold; margin-top: 4px; }
        .bac .row { display: flex; flex-wrap: wrap; justify-content: space-around; gap: 30px 24px; margin-top: 34px; }
        .bac .m { width: 200px; text-align: center; }
        .bac .m .line { border-bottom: 1px solid #000; min-height: 14px; }
        .bac .m .n { font-weight: bold; text-transform: uppercase; margin-top: 2px; }
        .watermark { position: absolute; top: 35%; left: 0; right: 0; text-align: center; font-size: 80pt; color: rgba(220, 38, 38, .12); transform: rotate(-30deg); pointer-events: none; font-weight: bold; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; width: auto; min-height: 0; padding: 0; }
            table.abs thead { display: table-header-group; }
            table.abs tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <strong>{{ $aoq->title() }}</strong>
    <span>{{ $aoq->status->label() }}</span>
    <span class="spacer"></span>
    <button class="primary" onclick="window.print()">Print / Save as PDF</button>
    <button onclick="window.close()">Close</button>
</div>

<div class="sheet">
    @unless ($awarded)<div class="watermark">DRAFT</div>@endunless

    <div class="top">
        <div class="head">
            <img src="{{ asset('assets/img/sra-da.png') }}" alt="">
            <div class="t">
                <div>Republic of the Philippines</div>
                <div>Department of Agriculture</div>
                <b>SUGAR REGULATORY ADMINISTRATION</b>
                <div>North Avenue, Diliman, Quezon City</div>
                <div>Telefax No. (02) 8929-61-36</div>
                <div class="title">ABSTRACT OF QUOTATION</div>
            </div>
        </div>
        <div class="ref">
            <div class="qr" data-text="{{ route('procurement.aoqs.show', $aoq) }}"></div>
            <div class="no">AQ. No. {{ $rfq->rfq_no }}</div>
            <div>DATE: {{ $date }}</div>
        </div>
    </div>

    <div class="pr">Purchase Request No. {{ $prNos }}</div>

    <table class="abs">
        <colgroup>
            <col style="width: 6%"><col style="width: 5%"><col style="width: 7%"><col>
            @foreach ($suppliers as $s)<col style="width: {{ $landscape ? round(60 / $suppliers->count(), 1) : 16 }}%">@endforeach
        </colgroup>
        <thead>
            <tr>
                <th rowspan="3">Item<br>No.</th>
                <th rowspan="3">Qty</th>
                <th rowspan="3">Unit</th>
                <th>Description of Articles</th>
                @foreach ($suppliers as $s)<th>SUPPLIER/DEALER</th>@endforeach
            </tr>
            {{-- The supplier's name only (no address), over both rows --}}
            <tr>
                <th>{{ $charged }}</th>
                @foreach ($suppliers as $s)<th rowspan="2">{{ $upper($s->name) }}</th>@endforeach
            </tr>
            <tr>
                <th>{{ $endUsers }}</th>
            </tr>
        </thead>
        <tbody>
            <tr class="spacer"><td></td><td></td><td></td><td></td>@foreach ($suppliers as $s)<td></td>@endforeach</tr>
            @foreach ($rows as $i => $row)
                @php $line = $row['item']->line; @endphp
                <tr>
                    <td class="c">{{ $i + 1 }}</td>
                    <td class="c">{{ $qty($line->quantity) }}</td>
                    <td class="c">{{ $upper($line->unit) }}</td>
                    <td>
                        <div>{{ $line->description }}</div>
                        @if ($line->specifications)<div style="margin-top: 6px;">@include('procurement.partials.specs', ['text' => $line->specifications, 'print' => true])</div>@endif
                    </td>
                    @foreach ($suppliers as $s)
                        @php
                            $q = $row['quotes'][$s->id];
                            // Underlined once the TWG has evaluated (lowest that meets the specs), or the award
                            $mark = $awarded ? $row['awarded'] === $s->id : ($aoq->status === AoqStatus::Evaluated && in_array($s->id, $row['lowest'], true));
                        @endphp
                        @if ($q['issue'] === 'No quote')
                            <td class="q" style="vertical-align: middle;">Invited but no quote</td>
                        @else
                            <td class="q {{ $q['issue'] ? 'out' : '' }}">
                                <div class="total {{ $mark ? 'low' : '' }}">₱ {{ $cents($q['total_cents']) }}</div>
                                <div class="unit">{{ number_format((float) $q['quote']->unit_price, 2) }}/{{ mb_strtolower((string) ($line->unit ?: 'unit')) }}</div>
                                @if ($q['quote']->brand)<div>Brand: {{ $q['quote']->brand }}</div>@endif
                                @if ($q['issue'])<div class="tag">({{ $q['issue'] }})</div>@endif
                            </td>
                        @endif
                    @endforeach
                </tr>
            @endforeach
            {{-- Per lot: each supplier's lot total (underlined as its items are) --}}
            @if ($lot)
                <tr>
                    <td></td><td></td><td></td>
                    <td><b>TOTAL (1 LOT)</b></td>
                    @foreach ($suppliers as $s)
                        @php
                            $st = $lot['suppliers'][$s->id];
                            $mark = $awarded ? $rows->first()['awarded'] === $s->id : ($aoq->status === AoqStatus::Evaluated && in_array($s->id, $lot['lowest'], true));
                        @endphp
                        <td class="q">
                            @if ($st['total_cents'] === null)
                                <span class="tag">Incomplete lot</span>
                            @else
                                <div class="total {{ $mark ? 'low' : '' }}">₱ {{ $cents($st['total_cents']) }}</div>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endif
            {{-- Each supplier's remarks, e.g. (PLEASE SEE ATTACHED) --}}
            <tr>
                <td></td><td></td><td></td><td style="height: 30px;"></td>
                @foreach ($suppliers as $s)
                    <td class="c">{{ $s->remarks ? '(' . $upper($s->remarks) . ')' : '' }}@unless ($s->is_responsive)<div class="tag">Not responsive</div>@endunless</td>
                @endforeach
            </tr>
            <tr class="spacer"><td></td><td></td><td></td><td></td>@foreach ($suppliers as $s)<td></td>@endforeach</tr>
            <tr>
                <td></td><td></td><td></td>
                <td colspan="{{ $suppliers->count() + 1 }}"><i>Technical comment:</i> {{ $aoq->technical_comment }}</td>
            </tr>
            <tr>
                <td></td><td></td><td></td>
                <td style="vertical-align: middle;">Approved Budget: Php {{ number_format((float) $rfq->total_abc, 2) }}</td>
                @if ($suppliers->isNotEmpty())<td colspan="{{ $suppliers->count() }}" class="note">{{ $upper($aoq->remarks) }}</td>@endif
            </tr>
        </tbody>
    </table>
    @if ($awarded && $failed->isNotEmpty())
        <div class="failed">{{ $lot ? 'Failed: no supplier quoted the whole lot meeting the specs within the approved budget.' : 'Failed (no responsive quotation within the approved budget): item no. ' . $failed->implode(', ') . '.' }}</div>
    @endif

    <div class="signs">
        @foreach (['prepared_by' => 'Prepared by:', 'evaluated_by' => 'Evaluated by:', 'certified_by' => 'Certified/Corrected by:'] as $field => $label)
            <div class="s">
                <div>{{ $label }}</div>
                <div class="n">{{ $signatories[$field][0] ?: "\u{00A0}" }}</div>
                <div>{{ $upper($signatories[$field][1]) }}</div>
            </div>
        @endforeach
    </div>

    <div class="bac">
        <div>Approved by:</div>
        <div class="who">BIDDING &amp; AWARDS COMMITTEE</div>
        <div class="row">
            @foreach ($members as $member)
                <div class="m"><div class="line"></div><div class="n">{{ $member }}</div><div>Member</div></div>
            @endforeach
        </div>
        <div class="row">
            <div class="m"><div class="line"></div><div class="n">{{ $signatories['bac_vice'] ?: "\u{00A0}" }}</div><div>Vice-Chairman</div></div>
            <div class="m"><div class="line"></div><div class="n">{{ $signatories['bac_chair'] ?: "\u{00A0}" }}</div><div>Chairperson</div></div>
        </div>
    </div>
</div>

{{-- QR code of the AOQ (opens it in the system); skipped quietly when the library cannot load --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    if (window.QRCode) {
        document.querySelectorAll('.qr').forEach((el) => new QRCode(el, { text: el.dataset.text, width: 128, height: 128, correctLevel: QRCode.CorrectLevel.M }));
    }
</script>
</body>
</html>
