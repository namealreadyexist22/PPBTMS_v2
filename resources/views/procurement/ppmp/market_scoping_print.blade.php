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
        <tr><td class="label">Period of Market Scoping<br><span style="font-weight: normal; font-size: 8.5pt;">[From (mm/yyyy) To (mm/yyyy)]</span></td><td>From {{ $month($ms['period_from'] ?? null) }} To {{ $month($ms['period_to'] ?? null) }}</td></tr>
        <tr><td class="label">Expected Date of Delivery (mm/yyyy)</td><td>{{ $item->delivery_period ?: $item->proc_end?->format('m/Y') }}</td></tr>
    </table>

    <h3>3. Market Scoping Activity/ies Conducted <span style="text-transform: none; font-weight: normal;">(Check all that apply)</span></h3>
    <div class="note">This confirms that market scoping activities were conducted in accordance with Section 10 of Republic Act No. 12009 and its Implementing Rules and Regulations (IRR), and considered in the Project Procurement Management Plan, consistent with the Principle of Proportionality.</div>
    <table>
        <thead><tr><th style="width: 9%;">Check (✓)</th><th style="width: 45%;">Activity/ies Conducted</th><th>Documentation (as may be applicable)</th></tr></thead>
        <tbody>
            @foreach (config('market_scoping.activities') as $key => [$label, $docs])
                <tr>
                    <td class="center"><span class="box" style="margin: 0;">{{ in_array($key, $ms['activities'] ?? []) ? '✓' : '' }}</span></td>
                    <td>{{ $label }}</td>
                    <td style="font-size: 8.5pt;">{{ $docs }}</td>
                </tr>
            @endforeach
            <tr>
                <td class="center"><span class="box" style="margin: 0;">{{ ! empty($ms['activity_other']) ? '✓' : '' }}</span></td>
                <td colspan="2">Other analogous market scoping activity/ies undertaken: <u>{{ $ms['activity_other'] ?? '' }}</u></td>
            </tr>
        </tbody>
    </table>
    <div class="note" style="margin-top: 6px;">
        Notes: i. The market scoping activities shall be identified and undertaken at the option of the End-User or Implementing Unit based on its needs and objectives.
        ii. The list of supporting documents in the Documentation column is not exclusive and may include other documents that may be gathered by the End-User or Implementing Unit pertinent to the activity/ies conducted.
    </div>

    <h3>4. Market Scoping Results</h3>
    <div class="note">Indicate recommendations in the column provided based on the results of the market scoping activities undertaken. These recommendations shall be considered in the development of a comprehensive and realistic PPMP, taking into account the parameters outlined under Section 10.4 of the IRR of RA 12009, as may be applicable.</div>
    <table>
        <thead><tr><th style="width: 40%;">Parameters</th><th style="width: 15%;">Considered?<br><span style="font-weight: normal;">(Yes/No/Not Applicable)</span></th><th>Recommendations based on the Market Scoping<br><span style="font-weight: normal;">(Attach additional documents if necessary)</span></th></tr></thead>
        <tbody>
            @foreach (array_values(array_keys(config('market_scoping.parameters'))) as $i => $key)
                @php [$label, $question] = config("market_scoping.parameters.$key"); @endphp
                <tr>
                    <td><b>{{ chr(97 + $i) }}. {{ $label }}</b><br><span style="font-size: 8.5pt;">[{{ $question }}]</span></td>
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
                <tr><td style="width: 40%;">{{ $attachment->kindLabel() }}</td><td>{{ $attachment->original_name }}</td></tr>
            @endforeach
        </table>
    @endif

    <div class="sigs">
        <div class="sig">
            <div>Prepared by:</div>
            <div class="role" style="text-align: left;">Personnel-in-Charge, End-User or Implementing Unit</div>
            <div class="name">{{ $prepared?->name_snapshot }}</div>
            <div class="pos">[Signature over Printed Name]</div>
            <div class="pos">{{ $prepared?->designation_snapshot ?: '[Position/Designation]' }}</div>
            <div class="pos">Date: ______________</div>
        </div>
        <div class="sig">
            <div>Approved by:</div>
            <div class="role" style="text-align: left;">Head, End-User or Implementing Unit</div>
            <div class="name">{{ $head?->fullname }}</div>
            <div class="pos">[Signature over Printed Name]</div>
            <div class="pos">{{ $head?->designation ?: '[Position/Designation]' }}</div>
            <div class="pos">Date: ______________</div>
        </div>
    </div>
</div>
</body>
</html>
