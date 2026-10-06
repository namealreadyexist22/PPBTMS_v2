{{--
    GPPB Market Scoping Checklist (NGPA standard form, RA 12009 Sec. 10) for one procurement project.
    Agency information and the project overview come from the PPMP; the rest from the project's checklist.
--}}
@php
    $ms = $item->market_scoping ?? [];
    $month = fn ($ym) => $ym ? \Carbon\Carbon::createFromFormat('Y-m', $ym)->format('m/Y') : '__________';
    $answers = config('market_scoping.answers');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Market Scoping Checklist — {{ \Illuminate\Support\Str::limit($item->description, 60) }}</title>
    <style>
        @page { size: A4 portrait; margin: 14mm 14mm 16mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 9.5pt; color: #000; margin: 0; background: #e5e7eb; }
        .sheet { background: #fff; width: 210mm; min-height: 297mm; margin: 12px auto; padding: 14mm; }
        .toolbar { position: sticky; top: 0; background: #1f2937; color: #fff; padding: 8px 16px; display: flex; gap: 12px; align-items: center; font-size: 13px; }
        .toolbar button { font-size: 13px; padding: 5px 12px; border-radius: 5px; border: 0; cursor: pointer; background: #2563eb; color: #fff; }
        .head { text-align: center; line-height: 1.3; margin-bottom: 10px; }
        .head .agency { font-weight: bold; font-size: 11pt; text-transform: uppercase; }
        .head .title { font-weight: bold; font-size: 12pt; margin-top: 8px; letter-spacing: .5px; }
        h3 { font-size: 9.5pt; margin: 12px 0 4px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1px solid #000; padding: 4px 6px; vertical-align: top; }
        th { background: #f2f2f2; font-size: 8.5pt; }
        td.label { width: 34%; font-weight: bold; background: #fafafa; }
        .box { display: inline-block; width: 11px; height: 11px; border: 1px solid #000; text-align: center; line-height: 10px; font-size: 9px; font-weight: bold; margin-right: 6px; vertical-align: -1px; }
        .acts div { margin: 3px 0; }
        .note { font-size: 8.5pt; margin: 4px 0 6px; }
        .center { text-align: center; }
        .sigs { display: flex; gap: 24mm; margin-top: 22px; }
        .sig { flex: 1; }
        .sig .name { border-bottom: 1px solid #000; text-align: center; font-weight: bold; text-transform: uppercase; min-height: 16px; margin-top: 26px; }
        .sig .pos { text-align: center; font-size: 8.5pt; }
        .sig .role { text-align: center; font-size: 8pt; font-style: italic; }
        @media print { body { background: #fff; } .toolbar { display: none; } .sheet { width: auto; min-height: 0; margin: 0; padding: 0; } }
    </style>
</head>
<body>
<div class="toolbar"><span>Market Scoping Checklist — {{ $ppmp->ppmp_no }}</span><span style="flex:1"></span><button onclick="window.print()">Print / Save as PDF</button></div>
<div class="sheet">
    <div class="head">
        <div class="agency">{{ setting('agency_name', 'Sugar Regulatory Administration') }}</div>
        <div>{{ setting('agency_address', 'Sugar Center Building, North Avenue, Diliman, Quezon City') }}</div>
        <div class="title">MARKET SCOPING CHECKLIST</div>
    </div>

    <h3>1. Agency Information</h3>
    <table>
        <tr><td class="label">Name of Procuring Entity</td><td>{{ setting('agency_name', 'Sugar Regulatory Administration') }}</td></tr>
        <tr><td class="label">End-User / Implementing Unit</td><td>{{ $ppmp->office->name }}</td></tr>
        <tr><td class="label">Name &amp; Designation of Representative</td><td>{{ $prepared?->name_snapshot }}{{ $prepared?->designation_snapshot ? ', ' . $prepared->designation_snapshot : '' }}</td></tr>
    </table>

    <h3>2. Project Overview</h3>
    <table>
        <tr><td class="label">Project Name</td><td>{{ $item->description }}</td></tr>
        <tr><td class="label">Estimated Budget</td><td>₱{{ number_format((float) $item->estimated_budget, 2) }}</td></tr>
        <tr><td class="label">Period of Market Scoping</td><td>From {{ $month($ms['period_from'] ?? null) }} To {{ $month($ms['period_to'] ?? null) }}</td></tr>
        <tr><td class="label">Expected Date of Delivery</td><td>{{ $item->delivery_period ?: $item->proc_end?->format('m/Y') }}</td></tr>
    </table>

    <h3>3. Market Scoping Activity/ies Conducted</h3>
    <div class="note">Conducted in accordance with Section 10 of Republic Act No. 12009 and its IRR, and considered in the Project Procurement Management Plan, consistent with the Principle of Proportionality.</div>
    <div class="acts">
        @foreach (config('market_scoping.activities') as $key => $label)
            <div><span class="box">{{ in_array($key, $ms['activities'] ?? []) ? '✓' : '' }}</span>{{ $label }}</div>
        @endforeach
        <div><span class="box">{{ ! empty($ms['activity_other']) ? '✓' : '' }}</span>Others (specify): {{ $ms['activity_other'] ?? '' }}</div>
    </div>

    <h3>4. Results of the Market Scoping</h3>
    <table>
        <thead><tr><th style="width: 38%;">Parameters</th><th style="width: 14%;">Yes / No / Not Applicable</th><th>Recommendations based on the Market Scoping<br><span style="font-weight: normal;">(attach additional documents if necessary)</span></th></tr></thead>
        <tbody>
            @foreach (config('market_scoping.parameters') as $key => $label)
                <tr>
                    <td>{{ $label }}</td>
                    <td class="center">{{ $answers[$ms['parameters'][$key]['answer'] ?? ''] ?? '' }}</td>
                    <td>{{ $ms['parameters'][$key]['recommendation'] ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($item->attachments->isNotEmpty())
        <h3>Attached Documents</h3>
        <table>
            @foreach ($item->attachments as $attachment)
                <tr><td style="width: 38%;">{{ $attachment->kindLabel() }}</td><td>{{ $attachment->original_name }}</td></tr>
            @endforeach
        </table>
    @endif

    <div class="sigs">
        <div class="sig">
            <div>Prepared by:</div>
            <div class="name">{{ $prepared?->name_snapshot }}</div>
            <div class="pos">{{ $prepared?->designation_snapshot }}</div>
            <div class="role">Personnel-in-Charge, End-User or Implementing Unit</div>
        </div>
        <div class="sig">
            <div>Noted by:</div>
            <div class="name">{{ $head?->fullname }}</div>
            <div class="pos">{{ $head?->designation }}</div>
            <div class="role">Head of the End-User or Implementing Unit</div>
        </div>
    </div>
</div>
</body>
</html>
