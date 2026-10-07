{{--
    Purchase Request (FM-AFD-PPS-003) / Job Request (FM-AFD-PPS-001) in the SRA form.
    Expects: $pr (with office, pap, items), $department, $section, $jrType
--}}
@php
    use App\Enums\RequestKind;
    use App\Enums\RequestStatus;
    $jr = $pr->kind === RequestKind::Jr;
    $money = fn ($amount) => number_format((float) $amount, 2);
    $qty = fn ($q) => rtrim(rtrim(number_format((float) $q, 2), '0'), '.');
    $watermark = match ($pr->status) {
        RequestStatus::Draft      => 'DRAFT',
        RequestStatus::Superseded => 'SUPERSEDED',
        RequestStatus::Cancelled  => 'CANCELLED',
        default                   => null,
    };
    $number = ($pr->request_no ?? '') . ($pr->revision ? " Rev. {$pr->revision}" : '');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $pr->title() }}</title>
    <style>
        @page { size: A4 portrait; margin: 10mm; }
        * { box-sizing: border-box; }
        body { font-family: Calibri, Carlito, "Segoe UI", Arial, sans-serif; font-size: 9pt; color: #000; margin: 0; background: #e5e7eb; }
        .sheet { background: #fff; width: 210mm; min-height: 297mm; margin: 12px auto; padding: 10mm; position: relative; display: flex; flex-direction: column; }

        .toolbar { position: sticky; top: 0; z-index: 10; background: #1f2937; color: #fff; padding: 8px 16px; display: flex; gap: 12px; align-items: center; font: 13px Arial, sans-serif; }
        .toolbar button { font-size: 13px; padding: 5px 12px; border-radius: 5px; border: 0; cursor: pointer; }
        .toolbar .primary { background: #2563eb; color: #fff; }
        .toolbar .spacer { flex: 1; }

        .label-top { text-align: right; color: #b91c1c; font-family: "Times New Roman", serif; font-size: 9pt; margin-bottom: 2px; }
        .form { border: 1px solid #000; flex: 1; display: flex; flex-direction: column; }
        .head { display: flex; align-items: center; justify-content: center; gap: 14px; padding: 6px 0 8px; border-bottom: 1px solid #000; }
        .head img { height: 62px; }
        .head .t { text-align: center; line-height: 1.2; }
        .head .t .title { font-size: 17pt; font-weight: bold; }
        .head .t .agency { font-size: 8pt; font-weight: bold; }

        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 3px 5px; vertical-align: top; }
        .meta td { border-bottom: 1px solid #000; height: 22px; vertical-align: middle; font-size: 8.5pt; }
        .meta td.k { font-weight: bold; width: 15%; }
        .meta td.v { font-weight: bold; }
        .meta td.n { white-space: nowrap; }
        .meta td.sep { border-left: 1px solid #000; }

        .lines { flex: 1; display: flex; }
        .lines table { height: 100%; table-layout: fixed; }
        .lines th { border-bottom: 1px solid #000; border-left: 1px solid #000; font-size: 8.5pt; text-align: center; vertical-align: middle; height: 26px; }
        .lines th:first-child, .lines td:first-child { border-left: 0; }
        .lines td { border-left: 1px solid #000; font-size: 8.5pt; }
        .lines td.c { text-align: center; }
        .lines td.r { text-align: right; }
        .lines .name { font-weight: bold; }
        .lines .specs { font-style: italic; white-space: pre-line; }
        .lines tr.fill td { height: 100%; }

        .bar td { border-top: 1px solid #000; font-size: 8.5pt; vertical-align: middle; height: 20px; }
        .bar td.sep { border-left: 1px solid #000; }
        .cert { border-top: 1px solid #000; padding: 3px 5px 2px; font-size: 8.5pt; }
        .cert .h { text-align: center; font-weight: bold; letter-spacing: 3px; margin: 3px 0; }
        .cert p { margin: 0 40px; line-height: 1.3; }
        .cert .sig { text-align: right; margin: 16px 10px 0 0; } .cert .sig span { border-top: 1px solid #000; padding: 0 18px; }
        .purpose { border-top: 1px solid #000; padding: 3px 5px; min-height: 22px; font-size: 8.5pt; }

        .sign td { border-top: 1px solid #000; font-size: 8.5pt; vertical-align: middle; }
        .sign td + td { border-left: 1px solid #000; }
        .sign td.k { width: 18%; }
        .sign td.v { text-align: center; }
        .sign .name { font-weight: bold; text-transform: uppercase; }

        .foot { text-align: right; font-family: "Times New Roman", serif; font-size: 7.5pt; margin-top: 3px; line-height: 1.3; }
        .watermark { position: absolute; top: 40%; left: 0; right: 0; text-align: center; font-size: 80pt; color: rgba(220, 38, 38, .12); transform: rotate(-30deg); pointer-events: none; font-weight: bold; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; width: auto; min-height: 277mm; padding: 0; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <strong>{{ $pr->title() }}</strong>
    <span>{{ $pr->status->label() }}</span>
    <span class="spacer"></span>
    <button class="primary" onclick="window.print()">Print / Save as PDF</button>
    <button onclick="window.close()">Close</button>
</div>

<div class="sheet">
    @if ($watermark)<div class="watermark">{{ $watermark }}</div>@endif
    <div class="label-top">{{ $pr->procurementLabel() }}</div>

    <div class="form">
        <div class="head">
            <img src="{{ asset('assets/img/sra-da.png') }}" alt="">
            <div class="t">
                <div class="title">{{ $jr ? 'JOB REQUEST' : 'PURCHASE REQUEST' }}</div>
                <div class="agency">SUGAR REGULATORY ADMINISTRATION</div>
                <div class="agency">North Avenue, Diliman, Quezon City</div>
                <div class="agency">Telefax No. (02) 8929-61-36</div>
            </div>
            <div style="width: 62px"></div>
        </div>

        <table class="meta">
            <tr>
                <td class="k">Department:</td>
                <td class="v" style="width: 38%">{{ mb_strtoupper($department) }}</td>
                <td class="k sep" style="width: 12%">{{ $jr ? 'J.R. No.:' : 'PR No.:' }}</td>
                <td class="v n">{{ $number }}</td>
                @unless ($jr)
                    <td class="k" style="width: 8%">Date:</td>
                    <td class="v n">{{ $pr->submitted_at?->format('M. d, Y') }}</td>
                @endunless
            </tr>
            <tr>
                <td class="k">Section/Unit:</td>
                <td class="v">{{ $section }}</td>
                @if ($jr)
                    <td class="k sep">Date:</td>
                    <td class="v n">{{ $pr->submitted_at?->format('M. d, Y') }}</td>
                @else
                    <td class="k sep">SAI No.:</td>
                    <td class="v">{{ $pr->sai_no }}</td>
                    <td class="k">Date:</td>
                    <td class="v">{{ $pr->sai_date?->format('M. d, Y') }}</td>
                @endif
            </tr>
        </table>

        <div class="lines">
            <table>
                <colgroup>
                    @if ($jr)
                        <col style="width: 10%"><col style="width: 6%"><col><col style="width: 9%"><col style="width: 24%">
                    @else
                        <col style="width: 10%"><col style="width: 13%"><col><col style="width: 15%"><col style="width: 12%"><col style="width: 13%">
                    @endif
                </colgroup>
                <thead>
                    <tr>
                        @if ($jr)
                            <th>Property No.</th><th>Unit</th><th>Item Description</th><th>Quantity</th><th>Nature of Work</th>
                        @else
                            <th>Stock No.</th><th>Unit</th><th>Item Description</th><th>Quantity</th><th>Unit Cost</th><th>Total Cost</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pr->items as $line)
                        <tr>
                            <td class="c">{{ $line->stock_no }}</td>
                            <td class="c">{{ mb_strtoupper((string) $line->unit) }}</td>
                            <td>
                                <div class="name">{{ $line->description }}</div>
                                @if ($line->specifications)<div class="specs">{{ $line->specifications }}</div>@endif
                            </td>
                            <td class="c">{{ $qty($line->quantity) }}</td>
                            @if ($jr)
                                <td>{{ $line->nature_of_work }}</td>
                            @else
                                <td class="r">{{ $money($line->unit_cost) }}</td>
                                <td class="r">{{ $money($line->total_cost) }}</td>
                            @endif
                        </tr>
                    @endforeach
                    <tr class="fill">@for ($i = 0; $i < ($jr ? 5 : 6); $i++)<td></td>@endfor</tr>
                </tbody>
            </table>
        </div>

        <table class="bar">
            <tr>
                <td>CHARGE TO: <b>{{ $pr->pap->code }}</b> - {{ $pr->pap->title }}</td>
                <td class="sep" style="width: {{ $jr ? '9%' : '12%' }}; text-align: right"><b>{{ $jr ? 'ABC:' : 'TOTAL' }}</b></td>
                <td style="width: {{ $jr ? '24%' : '13%' }}; text-align: right"><b>{{ $money($pr->total_amount) }}</b></td>
            </tr>
        </table>

        @if ($jr)
            <div class="cert">
                To be certified by General Services / MIS / Authorized Personnel in case of repair and replacement of parts.
                <div class="h">CERTIFICATION</div>
                <p>I hereby certify that the repair and replacement of parts of the items described above are necessary in the interest of
                    public service and that all defects and/or damages were caused due to wear and tear and not through fault, negligence
                    or carelessness of the accountable/responsible officer/employee.</p>
                <div class="sig"><span>(Signature over printed name)</span></div>
            </div>
        @endif

        <div class="purpose">Purpose: {{ $pr->purpose }}@if ($jrType) <span style="float: right">JR Type: {{ $jrType }}</span>@endif</div>

        <table class="sign">
            <tr>
                <td class="k" rowspan="2">Signature</td>
                <td style="width: 41%; height: 16px">Requested by:</td>
                <td>Approved by:</td>
            </tr>
            <tr><td style="height: 20px"></td><td></td></tr>
            <tr>
                <td class="k">Printed Name:</td>
                <td class="v name">{{ mb_strtoupper((string) $pr->requested_by_name) }}</td>
                <td class="v name">{{ mb_strtoupper((string) $pr->approved_by_name) }}</td>
            </tr>
            <tr>
                <td class="k">Designation:</td>
                <td class="v">{{ mb_strtoupper((string) $pr->requested_by_designation) }}</td>
                <td class="v">{{ mb_strtoupper((string) $pr->approved_by_designation) }}</td>
            </tr>
        </table>
    </div>

    <div class="foot">{{ $jr ? 'FM-AFD-PPS-001,Rev.00' : 'FM-AFD-PPS-003,Rev.00' }}<br>Effectivity Date: March 12, 2015</div>
</div>
</body>
</html>
