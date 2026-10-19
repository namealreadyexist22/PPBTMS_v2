{{--
    One Market Scoping Checklist sheet, as the SRA form (NGPA, RA 12009 Sec. 10): letterhead, agency
    information, project overview, activities with their documentation, results, signatures, footer logos.
    Expects $ppmp, $item, $prepared, $head, $endUser.
--}}
@php
    $ms = $item->market_scoping ?? [];
    $month = fn ($ym) => $ym ? \Carbon\Carbon::createFromFormat('Y-m', $ym)->format('m/Y') : '_______';
    $answers = ['yes' => 'Yes', 'no' => 'No', 'na' => 'Not Applicable'];
    $docs = $ms['documentation'] ?? [];
    // Expected delivery as mm/yyyy: the project's delivery period when it reads as a date ("June 2027"), else the end of procurement
    $delivery = rescue(fn () => $item->delivery_period ? \Carbon\Carbon::parse('1 ' . $item->delivery_period)->format('m/Y') : null, null, false)
        ?? $item->proc_end?->format('m/Y') ?? $item->delivery_period;
    $signedOn = ! empty($ms['signed_on']) ? \Carbon\Carbon::parse($ms['signed_on'])->format('F d, Y') : null;
    $checked = fn ($on) => $on ? '🗹' : '☐';
@endphp
<div class="sheet">
    <div class="letterhead">
        <img src="{{ asset('assets/img/sra-da.png') }}" alt="">
        <div class="lh-text">
            <div>Republic of the Philippines</div>
            <div>Department of Agriculture</div>
            <div class="lh-agency">SUGAR REGULATORY ADMINISTRATION</div>
            <div>Sugar Center Bldg., North Avenue, Diliman, Quezon City, Philippines 1101</div>
            <div>TIN 000-784-336</div>
            <div>Website: http://www.sra.gov.ph</div>
            <div>Email Address: srahead@sra.gov.ph</div>
            <div>Tel No. (632) 8929-3633, (632) 3455-2135, (632) 3455-3376</div>
        </div>
    </div>

    <div class="title">MARKET SCOPING CHECKLIST</div>

    <h3>AGENCY INFORMATION</h3>
    <table>
        <tr><td class="label">Name of Procuring Entity</td><td>Sugar Regulatory Administration</td></tr>
        <tr><td class="label">End-User/Implementing Unit</td><td>{{ $endUser }}</td></tr>
        <tr><td class="label">Name &amp; Designation of Representative</td><td>{{ $prepared['name'] }}{{ $prepared['position'] ? ' / ' . $prepared['position'] : '' }}</td></tr>
    </table>

    <h3>PROJECT OVERVIEW</h3>
    <table>
        <tr><td class="label">Project Name</td><td>{{ $item->description }}</td></tr>
        <tr><td class="label">Estimated Budget</td><td>{{ number_format((float) $item->estimated_budget, 2) }} Pesos</td></tr>
        <tr><td class="label">Period of Market Scoping<br><span class="sub">[From (mm/yyyy) To (mm/yyyy)]</span></td><td>{{ $month($ms['period_from'] ?? null) }} to {{ $month($ms['period_to'] ?? null) }}</td></tr>
        <tr><td class="label">Expected Date of Delivery (mm/yyyy)</td><td>{{ $delivery }}</td></tr>
    </table>

    <h3>MARKET SCOPING ACTIVITY/IES CONDUCTED <span class="normal">(Check all that apply)</span></h3>
    <p class="note">This confirms that market scoping activities were conducted in accordance with Section 10 of Republic Act No. 12009 and its Implementing Rules and Regulations (IRR), and considered in the Project Procurement Management Plan, consistent with the Principle of Proportionality.</p>
    <table>
        <thead><tr><th style="width: 12%;">Check (✓)</th><th>Activity/ies Conducted</th><th style="width: 30%;">Documentation<br><span class="normal">(as may be applicable)</span></th></tr></thead>
        <tbody>
            @foreach (config('market_scoping.activities') as $key => [$label])
                @php $on = in_array($key, $ms['activities'] ?? []); @endphp
                <tr>
                    <td class="center check">{{ $checked($on) }}</td>
                    <td>{{ $label }}</td>
                    <td class="center">{{ $on ? ($docs[$key] ?? '') : 'N/A' }}</td>
                </tr>
            @endforeach
            @php $other = trim((string) ($ms['activity_other'] ?? '')); @endphp
            <tr>
                <td class="center check">{{ $checked($other !== '') }}</td>
                <td>Other analogous market scoping activity/ies undertaken:<br>{!! $other !== '' ? '<u>' . e($other) . '</u>' : '_________________________' !!}</td>
                <td class="center">{{ $other !== '' ? ($docs['other'] ?? '') : 'N/A' }}</td>
            </tr>
        </tbody>
    </table>
    <div class="note">
        <div>Notes:</div>
        <ul>
            <li>The market scoping activities shall be identified and undertaken at the option of the End-User or Implementing Unit based on its needs and objectives.</li>
            <li>The list of supporting documents in the Documentation column is not exclusive and may include other documents that may be gathered by the End-User or Implementing Unit pertinent to the activity/ies conducted.</li>
        </ul>
    </div>

    <h3>MARKET SCOPING RESULTS</h3>
    <p class="note">Indicate recommendations in the column provided based on the results of the market scoping activities undertaken. These recommendations shall be considered in the development of a comprehensive and realistic PPMP, taking into account the parameters outlined under Section 10.4 of the IRR of RA 12009, as may be applicable.</p>
    <table>
        <thead><tr><th style="width: 36%;">Parameters</th><th style="width: 16%;">Considered?<br><span class="normal">(Yes/No/ Not Applicable)</span></th><th>Recommendations<br><span class="normal">based on the Market Scoping<br>(Attach additional documents if necessary)</span></th></tr></thead>
        <tbody>
            @foreach (config('market_scoping.parameters') as $key => [$label, $question])
                <tr>
                    <td><b>{{ $label }}</b><br><span class="sub">[{{ $question }}]</span></td>
                    <td class="center">{{ $answers[$ms['parameters'][$key]['answer'] ?? ''] ?? '' }}</td>
                    <td class="rec">{{ $ms['parameters'][$key]['recommendation'] ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="sigs">
        <tr>
            <td>
                <div>Prepared by:</div>
                <div class="role">Personnel-in-Charge, End-User or Implementing Unit</div>
                <div class="sign-line"></div>
                <div class="name">{{ $prepared['name'] }}</div>
                <div>{{ $prepared['position'] }}</div>
                <div>{{ $signedOn ?? 'Date: ______________' }}</div>
            </td>
            <td>
                <div>Approved by:</div>
                <div class="role">Head, End-User or Implementing Unit</div>
                <div class="sign-line"></div>
                <div class="name">{{ $head['name'] }}</div>
                <div>{{ $head['position'] }}</div>
                <div>{{ $signedOn ?? 'Date: ______________' }}</div>
            </td>
        </tr>
    </table>

    <div class="footer">
        <img src="{{ asset('assets/img/bagong-pilipinas.jpg') }}" alt="Bagong Pilipinas" class="bp">
        <span class="ft">Market Scoping Checklist</span>
        <img src="{{ asset('assets/img/tuv-iso-9001.jpg') }}" alt="ISO 9001:2015 certified" class="iso">
    </div>
</div>
