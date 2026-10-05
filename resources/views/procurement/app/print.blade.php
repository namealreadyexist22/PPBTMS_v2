{{-- Annual Procurement Plan, laid out like the SRA APP form. --}}
@php
    use App\Enums\AppType;
    $peso = fn ($amount) => '₱' . number_format((float) $amount, 2);
    $bac = $app->region->bac();
    $mmYyyy = fn ($date) => $date->format('n/Y');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $app->title() }}</title>
    <style>
        @page { size: A4 landscape; margin: 10mm 10mm 12mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 8pt; color: #000; margin: 0; background: #e5e7eb; }
        .sheet { background: #fff; width: 297mm; min-height: 210mm; margin: 12px auto; padding: 10mm; position: relative; }

        .toolbar { position: sticky; top: 0; z-index: 10; background: #1f2937; color: #fff; padding: 8px 16px; display: flex; gap: 12px; align-items: center; font-size: 13px; }
        .toolbar button, .toolbar select { font-size: 13px; padding: 5px 12px; border-radius: 5px; border: 0; cursor: pointer; }
        .toolbar .primary { background: #2563eb; color: #fff; }
        .toolbar .spacer { flex: 1; }

        .head { text-align: center; line-height: 1.5; margin-bottom: 8px; }
        .head .agency { font-weight: bold; font-size: 9pt; }
        .head .title { font-weight: bold; font-size: 9pt; }
        .box { display: inline-block; width: 22px; height: 12px; border: 1px solid #000; vertical-align: -2px; margin: 0 3px 0 14px; text-align: center; line-height: 11px; font-size: 9px; font-weight: bold; }
        .blank { display: inline-block; min-width: 30px; border-bottom: 1px solid #000; text-align: center; }

        table.app { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.app th, table.app td { border: 1px solid #000; padding: 3px 4px; vertical-align: middle; word-wrap: break-word; }
        table.app th { font-size: 7pt; text-align: center; overflow-wrap: normal; word-wrap: normal; }
        table.app thead { display: table-header-group; }
        table.app tr { page-break-inside: avoid; }
        table.app .colno th { font-weight: bold; padding: 1px; }
        table.app td.c { text-align: center; }
        table.app td.r { text-align: right; white-space: nowrap; }
        table.app tr.group td { font-weight: bold; text-align: left; }
        table.app tbody.keep { break-inside: avoid; page-break-inside: avoid; }

        .totals { margin: 10px 0 0 auto; width: 120mm; page-break-inside: avoid; }
        .totals td { padding: 1px 4px; font-weight: bold; }
        .totals td.r { text-align: right; width: 32mm; }

        .signatories { display: flex; justify-content: space-between; margin-top: 18px; page-break-inside: avoid; }
        .sig { width: 70mm; }
        .sig .label { margin-bottom: 6px; }
        .sig .by { min-height: 12px; margin-bottom: 18px; }
        .sig .name { border-bottom: 1px solid #000; font-weight: bold; text-transform: uppercase; text-align: center; min-height: 13px; }
        .sig .hint, .sig .pos, .sig .role { text-align: center; }
        .sig .role { font-style: italic; text-decoration: underline; }
        .sig .date { margin-top: 6px; }

        .watermark { position: fixed; top: 40%; left: 0; right: 0; text-align: center; font-size: 80pt; font-weight: bold; color: rgba(220, 38, 38, .12); transform: rotate(-20deg); pointer-events: none; }
        .footer-note { margin-top: 10px; font-size: 7pt; color: #444; display: flex; justify-content: space-between; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { width: auto; min-height: 0; margin: 0; padding: 0; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <strong>{{ $app->title() }}</strong>
    <span class="spacer"></span>
    <label>Paper
        <select id="paper">
            <option value="A4 landscape">A4</option>
            <option value="legal landscape">Legal</option>
            <option value="8.5in 13in landscape">Long (8.5 × 13)</option>
        </select>
    </label>
    <button class="primary" onclick="window.print()">Print / Save as PDF</button>
    <button onclick="window.close()">Close</button>
</div>

@if ($watermark)<div class="watermark">{{ $watermark }}</div>@endif

<div class="sheet">
    <div class="head">
        <div class="agency">{{ strtoupper(setting('agency_name', 'Sugar Regulatory Administration')) }}</div>
        <div>{{ setting('agency_address', 'Sugar Center Building, North Avenue, Diliman, Quezon City') }}</div>
        <div class="title">ANNUAL PROCUREMENT PLAN FOR FY {{ $app->fiscal_year }}{{ $app->region === \App\Enums\Region::Vis ? ' - VISAYAS' : '' }}</div>
        <div>
            <span class="box">{{ $app->type === AppType::Indicative ? '✓' : '' }}</span>INDICATIVE
            <span class="box">{{ $app->type === AppType::Final ? '✓' : '' }}</span>FINAL
            <span class="box">{{ $app->type === AppType::Updated ? '✓' : '' }}</span>UPDATED [Version No. <span class="blank">{{ $app->type === AppType::Updated ? $app->version : '' }}</span>]
        </div>
    </div>

    <table class="app">
        <colgroup>
            <col style="width: 15%"><col style="width: 8%"><col style="width: 12%"><col style="width: 7.5%">
            <col style="width: 6.5%"><col style="width: 7%"><col style="width: 6%"><col style="width: 6%">
            <col style="width: 7%"><col style="width: 7.5%"><col style="width: 8%"><col style="width: 9.5%">
        </colgroup>
        <thead>
            <tr>
                <th colspan="6">PROCUREMENT PROJECT DETAILS</th>
                <th colspan="2">PROJECTED TIMELINE (MM/YYYY)</th>
                <th colspan="2">FUNDING DETAILS</th>
                <th rowspan="2">PROCUREMENT STRATEGY OR TOOLS</th>
                <th rowspan="2">REMARKS<br><span style="font-weight: normal;">(Other relevant descriptions of the procurement project, if applicable)</span></th>
            </tr>
            <tr>
                <th>Project Title</th>
                <th>End-User or Implementing Unit</th>
                <th>General Description of the Project</th>
                <th>Mode of Procurement</th>
                <th>To be covered by an Early Procurement Activity? (Yes/No)</th>
                <th>Criteria for Bid Evaluation (Including Sustainability and Domestic Preference)</th>
                <th>Start of Procurement Activity</th>
                <th>End of Procurement Activity</th>
                <th>Source of Fund</th>
                <th>Estimated Budget / Approved Budget for the Contract (PhP)</th>
            </tr>
            <tr class="colno">@for ($c = 1; $c <= 12; $c++)<th>Column {{ $c }}</th>@endfor</tr>
        </thead>
        <tbody>
            @foreach ($lines['main'] as $group => $groupLines)
                @if ($group !== '')
                    <tr class="group"><td colspan="12">{{ $group }}</td></tr>
                @endif
                @foreach ($groupLines as $line)
                    @include('procurement.app.extras.app_print_row')
                @endforeach
            @endforeach

            @if ($lines['cse']->isNotEmpty())
                <tr class="group"><td colspan="12">Common Use Supplies and Equipment (CSE) to be purchased from PS-DBM (kindly indicate the summary/total amounts only)</td></tr>
                @foreach ($lines['cse'] as $line)
                    @include('procurement.app.extras.app_print_row')
                @endforeach
            @endif

            @if ($app->items->isEmpty())
                <tr><td colspan="12" class="c" style="padding: 14px;">No procurement projects.</td></tr>
            @endif
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Total Amount of Estimated Budget for EPA Projects:</td><td class="r">{{ number_format($epaTotal, 2) }}</td></tr>
        <tr><td>Total Amount of CSEs to be purchased from PS-DBM:</td><td class="r">{{ number_format($cseTotal, 2) }}</td></tr>
        <tr><td style="text-align: right;">Total Amount of Estimated Budget:</td><td class="r">{{ number_format((float) $app->total_budget, 2) }}</td></tr>
    </table>

    <div class="signatories">
        <div class="sig">
            <div class="label">Prepared by:</div>
            <div class="by"></div>
            <div class="name">{{ $prepared['name'] }}</div>
            <div class="hint">Signature over Printed Name</div>
            <div class="pos">{{ $prepared['position'] ?: 'Head - Secretariat' }}</div>
            <div class="role">{{ $bac }}</div>
            <div class="date">Date : {{ $prepared['date']?->format('n/j/Y') ?? '______________' }}</div>
        </div>
        <div class="sig">
            <div class="label">Recommended by:</div>
            <div class="by">By the Authority of the {{ $bac }}:</div>
            <div class="name">{{ $recommended['name'] }}</div>
            <div class="hint">Signature over Printed Name</div>
            <div class="pos">{{ $recommended['position'] ?: 'Chairperson' }}</div>
            <div class="role">{{ $bac }}</div>
            <div class="date">Date : {{ $recommended['date']?->format('n/j/Y') ?? '______________' }}</div>
        </div>
        <div class="sig">
            <div class="label">Approved by:</div>
            <div class="by"></div>
            <div class="name">{{ $approved['name'] }}</div>
            <div class="hint">Signature over Printed Name</div>
            <div class="pos">{{ $approved['position'] ?: 'Administrator/CEO' }}</div>
            <div class="role">Head of the Procuring Entity</div>
            <div class="date">Date : {{ $approved['date']?->format('n/j/Y') ?? '______________' }}</div>
        </div>
    </div>

    <div class="footer-note">
        <span>{{ $app->title() }} · {{ $app->status->label() }}</span>
        <span>Printed {{ now()->format('m/d/Y h:i A') }} by {{ auth()->user()->fullname }}</span>
    </div>
</div>

<script>
    document.getElementById('paper').addEventListener('change', function () {
        let style = document.getElementById('page-size');
        if (!style) { style = document.createElement('style'); style.id = 'page-size'; document.head.appendChild(style); }
        style.textContent = '@page { size: ' + this.value + '; }';
    });
</script>
</body>
</html>
