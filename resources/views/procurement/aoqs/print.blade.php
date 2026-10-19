{{--
    Abstract of Quotations (standard layout until the office form is supplied): one column pair per
    supplier (unit price, total), the lowest counting quote per item marked, the award per item and the
    BAC's signatures, approved by the HOPE. Expects: $aoq (with rfq.items.line.request, suppliers.quotes), $rows (AoqService::evaluate).
--}}
@php
    use App\Enums\AoqStatus;
    use App\Support\Money;
    $qty = fn ($q) => rtrim(rtrim(number_format((float) $q, 2), '0'), '.');
    $cents = fn (?int $c) => $c === null ? '' : Money::format(Money::fromCents($c));
    $rfq = $aoq->rfq;
    $suppliers = $aoq->suppliers;
    $awarded = $aoq->status === AoqStatus::Awarded;
    $prNos = $rfq->requests()->pluck('request_no')->filter()->implode(', ');
    // Award summary: supplier => [item numbers, total]
    $summary = $rows->filter(fn ($r) => $r['awarded'])->groupBy('awarded', true)->map(fn ($group, $sid) => [
        'name'  => $suppliers->firstWhere('id', $sid)->name,
        'items' => $group->keys()->map(fn ($k) => $k + 1)->implode(', '),
        'total' => $group->sum(fn ($r) => $r['quotes'][$sid]['total_cents']),
    ]);
    $failed = $rows->filter(fn ($r) => $r['lowest'] === [])->keys()->map(fn ($k) => $k + 1);
    $signers = collect([
        [$aoq->bac_chair_name, 'BAC Chairperson'],
        [$aoq->bac_vice_chair_name, 'BAC Vice-Chairperson'],
    ])->merge(collect($aoq->bac_members ?? [])->map(fn ($m) => [$m, 'BAC Member']))->filter(fn ($s) => filled($s[0]));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $aoq->title() }}</title>
    <style>
        @page { size: A4 landscape; margin: 10mm 12mm; }
        * { box-sizing: border-box; }
        body { font-family: "Open Sans", "Segoe UI", Arial, sans-serif; font-size: 9pt; color: #000; margin: 0; background: #e5e7eb; }
        .sheet { background: #fff; width: 297mm; min-height: 210mm; margin: 12px auto; padding: 10mm 12mm; position: relative; }

        .toolbar { position: sticky; top: 0; z-index: 10; background: #1f2937; color: #fff; padding: 8px 16px; display: flex; gap: 12px; align-items: center; font: 13px Arial, sans-serif; }
        .toolbar button { font-size: 13px; padding: 5px 12px; border-radius: 5px; border: 0; cursor: pointer; }
        .toolbar .primary { background: #2563eb; color: #fff; }
        .toolbar .spacer { flex: 1; }

        .head { display: flex; align-items: center; justify-content: center; gap: 14px; }
        .head img { height: 70px; }
        .head .t { line-height: 1.3; font-size: 9.5pt; text-align: center; }
        .head .t b { font-size: 10.5pt; }
        h1 { text-align: center; font-size: 13pt; margin: 10px 0 8px; letter-spacing: .5px; }

        .ref { display: flex; justify-content: space-between; gap: 20px; font-size: 9pt; margin-bottom: 6px; }
        .ref table td { padding: 0 6px 1px 0; vertical-align: top; }

        table.abs { width: 100%; border-collapse: collapse; font-size: 8.3pt; }
        table.abs th, table.abs td { border: 1px solid #000; padding: 3px 4px; vertical-align: top; }
        table.abs th { text-align: center; vertical-align: middle; font-weight: bold; }
        table.abs td.c { text-align: center; }
        table.abs td.r { text-align: right; white-space: nowrap; }
        table.abs .desc { font-weight: bold; }
        table.abs .brand { font-size: 7.5pt; font-style: italic; }
        table.abs .low { font-weight: bold; text-decoration: underline; }
        table.abs .out { color: #555; }
        table.abs .tag { font-size: 7pt; font-weight: normal; }
        table.abs tr.total td { font-weight: bold; }

        .award { margin-top: 10px; font-size: 9pt; }
        .award table { border-collapse: collapse; margin-top: 3px; }
        .award td, .award th { border: 1px solid #000; padding: 2px 8px; }
        .award th { text-align: left; }
        .remarks { margin-top: 6px; font-size: 9pt; white-space: pre-line; }

        .signs { display: flex; flex-wrap: wrap; gap: 28px 40px; margin-top: 30px; font-size: 9pt; }
        .signs .s { width: 190px; text-align: center; }
        .signs .s .n { border-bottom: 1px solid #000; font-weight: bold; text-transform: uppercase; min-height: 15px; }
        .approve { margin-top: 26px; font-size: 9pt; }
        .approve .s { width: 230px; text-align: center; margin: 24px 0 0 40px; }
        .approve .s .n { border-bottom: 1px solid #000; font-weight: bold; text-transform: uppercase; min-height: 15px; }
        .watermark { position: absolute; top: 35%; left: 0; right: 0; text-align: center; font-size: 80pt; color: rgba(220, 38, 38, .12); transform: rotate(-20deg); pointer-events: none; font-weight: bold; }

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

    <div class="head">
        <img src="{{ asset('assets/img/sra-da.png') }}" alt="">
        <div class="t">
            <div>Republic of the Philippines</div>
            <div>Department of Agriculture</div>
            <b>SUGAR REGULATORY ADMINISTRATION</b>
            <div>North Avenue, Diliman, Quezon City</div>
        </div>
    </div>
    <h1>ABSTRACT OF QUOTATIONS</h1>

    <div class="ref">
        <table>
            <tr><td>Quotation No.:</td><td><b>{{ $rfq->rfq_no }}</b></td></tr>
            <tr><td>PR No.:</td><td><b>{{ $prNos }}</b></td></tr>
            <tr><td>Mode of Procurement:</td><td><b>{{ $rfq->procurementMode?->name }}</b></td></tr>
        </table>
        <table>
            <tr><td>RFQ Date:</td><td><b>{{ $rfq->issued_at?->format('F d, Y') }}</b></td></tr>
            <tr><td>Date Opened:</td><td><b>{{ $aoq->opened_at?->format('F d, Y g:i A') }}</b></td></tr>
            <tr><td>ABC:</td><td><b>₱ {{ number_format((float) $rfq->total_abc, 2) }}</b></td></tr>
        </table>
    </div>

    <table class="abs">
        <thead>
            <tr>
                <th rowspan="2" style="width: 30px;">ITEM #</th>
                <th rowspan="2" style="width: 40px;">QTY.</th>
                <th rowspan="2" style="width: 45px;">UNIT</th>
                <th rowspan="2">ITEM &amp; DESCRIPTION</th>
                <th rowspan="2" style="width: 75px;">ABC</th>
                @foreach ($suppliers as $s)
                    <th colspan="2">{{ $s->name }}@unless ($s->is_responsive)<div class="tag">(not responsive{{ $s->remarks ? ': ' . $s->remarks : '' }})</div>@endunless</th>
                @endforeach
                <th rowspan="2" style="width: 110px;">AWARDED TO</th>
            </tr>
            <tr>
                @foreach ($suppliers as $s)<th style="width: 62px;">Unit Price</th><th style="width: 70px;">Total</th>@endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $i => $row)
                @php $line = $row['item']->line; @endphp
                <tr>
                    <td class="c">{{ $i + 1 }}</td>
                    <td class="c">{{ $qty($line->quantity) }}</td>
                    <td class="c">{{ mb_strtoupper((string) $line->unit) }}</td>
                    <td><div class="desc">{{ $line->description }}</div></td>
                    <td class="r">{{ $cents($row['abc_cents']) }}</td>
                    @foreach ($suppliers as $s)
                        @php
                            $q = $row['quotes'][$s->id];
                            $mark = $row['awarded'] === $s->id || (! $awarded && in_array($s->id, $row['lowest'], true));
                        @endphp
                        @if ($q['issue'] === 'No quote')
                            <td class="c" colspan="2">—</td>
                        @else
                            <td class="r {{ $q['issue'] ? 'out' : '' }}">{{ number_format((float) $q['quote']->unit_price, 2) }}</td>
                            <td class="r {{ $mark ? 'low' : ($q['issue'] ? 'out' : '') }}">
                                {{ $cents($q['total_cents']) }}
                                @if ($q['issue'])<div class="tag">{{ $q['issue'] }}</div>@endif
                                @if ($q['quote']->brand)<div class="brand" style="white-space: normal; text-align: left;">{{ $q['quote']->brand }}</div>@endif
                            </td>
                        @endif
                    @endforeach
                    <td>
                        @if ($row['awarded'])
                            {{ $suppliers->firstWhere('id', $row['awarded'])->name }}
                        @elseif ($row['lowest'] === [])
                            Failed
                        @endif
                    </td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="4" class="r">TOTAL</td>
                <td class="r">{{ number_format((float) $rfq->total_abc, 2) }}</td>
                @foreach ($suppliers as $s)
                    @php $quoted = $rows->map(fn ($r) => $r['quotes'][$s->id]['total_cents'])->filter(fn ($c) => $c !== null); @endphp
                    <td></td><td class="r">{{ $quoted->isEmpty() ? '' : $cents($quoted->sum()) }}</td>
                @endforeach
                <td></td>
            </tr>
        </tbody>
    </table>

    <div class="award">
        @if ($awarded && $summary->isNotEmpty())
            Based on the above abstract, the BAC recommends the award per item to the supplier with the lowest calculated and responsive quotation:
            <table>
                <tr><th>Supplier</th><th>Item #</th><th>Amount</th></tr>
                @foreach ($summary as $s)
                    <tr><td>{{ $s['name'] }}</td><td>{{ $s['items'] }}</td><td style="text-align: right;">₱ {{ $cents($s['total']) }}</td></tr>
                @endforeach
            </table>
        @else
            Underlined: lowest calculated and responsive quotation per item.
        @endif
        @if ($failed->isNotEmpty())<div style="margin-top: 4px;">Failed (no responsive quotation within the ABC): item # {{ $failed->implode(', ') }}.</div>@endif
    </div>
    @if ($aoq->remarks)<div class="remarks"><b>Remarks:</b> {{ $aoq->remarks }}</div>@endif

    <div class="signs">
        @forelse ($signers as [$name, $role])
            <div class="s"><div class="n">{{ $name }}</div><div>{{ $role }}</div></div>
        @empty
            <div class="s"><div class="n"></div><div>BAC Chairperson</div></div>
            <div class="s"><div class="n"></div><div>BAC Vice-Chairperson</div></div>
            <div class="s"><div class="n"></div><div>BAC Member</div></div>
        @endforelse
    </div>

    <div class="approve">
        Approved:
        <div class="s"><div class="n">{{ $aoq->hope_name }}</div><div>Head of the Procuring Entity</div></div>
    </div>
</div>
</body>
</html>
