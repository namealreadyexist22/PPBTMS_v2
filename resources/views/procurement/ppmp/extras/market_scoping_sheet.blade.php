{{-- One Market Scoping Checklist sheet (NGPA form). Expects $ppmp, $item, $prepared, $head. --}}
@php
    $ms = $item->market_scoping ?? [];
    $month = fn ($ym) => $ym ? \Carbon\Carbon::createFromFormat('Y-m', $ym)->format('m/Y') : '__________';
    $answers = config('market_scoping.answers');
@endphp
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
