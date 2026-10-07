{{--
    PPMP in the GPPB revised format, matching the SRA form: PAP header rows with their
    projects, "PPMP NO. n", Prepared by / Submitted by. Used for the Division PPMP
    (official), its preview, and a section's own copy.

    Expects: $title, $number (int|null), $type (PpmpType), $fiscalYear, $endUser,
             $paps (PpmpPap with items), $total, $watermark (string|null),
             $prepared / $submitted (['name','position','date']), $footer
--}}
@php
    use App\Enums\PpmpType;
    $peso = fn ($amount) => '₱' . number_format((float) $amount, 2);
    // "8 units", "1 unit"
    $qty = fn ($item) => $item->quantity !== null
        ? rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') . ' '
            . ($item->unit ? \Illuminate\Support\Str::plural($item->unit->name, (float) $item->quantity == 1 ? 1 : 2) : '')
        : null;
    $lastItemId = $paps->flatMap->items->last()?->id;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { size: A4 landscape; margin: 10mm 10mm 12mm; }

        * { box-sizing: border-box; }
        body { font-family: "Times New Roman", Times, serif; font-size: 9pt; color: #000; margin: 0; background: #e5e7eb; }
        .sheet { background: #fff; width: 297mm; min-height: 210mm; margin: 12px auto; padding: 10mm; position: relative; }

        .toolbar { position: sticky; top: 0; z-index: 10; background: #1f2937; color: #fff; padding: 8px 16px; display: flex; gap: 12px; align-items: center; font: 13px Arial, sans-serif; }
        .toolbar button, .toolbar select { font-size: 13px; padding: 5px 12px; border-radius: 5px; border: 0; cursor: pointer; }
        .toolbar .primary { background: #2563eb; color: #fff; }
        .toolbar .spacer { flex: 1; }

        .head { text-align: center; line-height: 1.25; margin-bottom: 4px; }
        .head .agency { font-size: 11pt; font-weight: bold; text-transform: uppercase; }
        .head .address { font-size: 9pt; }
        .head .form-title { font-size: 10pt; font-weight: bold; margin-top: 2px; }
        .head .types { margin-top: 3px; }
        .box { display: inline-block; width: 11px; height: 11px; border: 1px solid #000; vertical-align: -1px; margin: 0 3px 0 14px; text-align: center; line-height: 10px; font-size: 9px; font-weight: bold; }

        .meta { margin: 6px 0 4px; line-height: 1.5; }
        .meta b { text-transform: uppercase; }

        table.ppmp { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.ppmp th, table.ppmp td { border: 1px solid #000; padding: 3px 4px; vertical-align: middle; word-wrap: break-word; }
        table.ppmp th { font-size: 7.5pt; text-align: center; background: #f2f2f2; overflow-wrap: normal; word-wrap: normal; }
        table.ppmp th.group { background: #d9d9d9; font-size: 8pt; }
        table.ppmp td { font-size: 8pt; }
        table.ppmp td.c { text-align: center; font-style: italic; }
        table.ppmp td.r { text-align: right; white-space: nowrap; font-style: italic; }
        table.ppmp td.desc { font-weight: bold; }
        table.ppmp td.qty { text-align: center; font-style: italic; }
        table.ppmp td.qty .specs { white-space: pre-line; }
        table.ppmp thead { display: table-header-group; }
        table.ppmp tr { page-break-inside: avoid; }
        table.ppmp .colno th { font-weight: normal; font-style: italic; background: #fff; font-size: 7pt; padding: 1px; }
        table.ppmp tr.pap td { background: #d9d9d9; font-weight: bold; text-align: left; font-style: normal; }
        table.ppmp tr.total td { font-weight: bold; border: 1px solid #000; }
        table.ppmp tbody.keep { break-inside: avoid; page-break-inside: avoid; }

        .signatories { display: flex; justify-content: flex-start; gap: 60mm; margin-top: 16px; page-break-inside: avoid; padding-left: 20mm; }
        .sig { width: 70mm; }
        .sig .label { margin-bottom: 24px; }
        .sig .name { border-bottom: 1px solid #000; text-align: center; font-weight: bold; text-transform: uppercase; min-height: 14px; }
        .sig .hint, .sig .pos, .sig .role { text-align: center; font-size: 8pt; }
        .sig .role { font-style: italic; }
        .sig .date { margin-top: 8px; font-size: 8pt; }

        .watermark { position: fixed; top: 40%; left: 0; right: 0; text-align: center; font-size: 80pt; font-weight: bold; color: rgba(220, 38, 38, .12); transform: rotate(-20deg); pointer-events: none; z-index: 0; font-family: Arial, sans-serif; }
        .footer-note { margin-top: 10px; font-size: 7pt; color: #444; display: flex; justify-content: space-between; font-family: Arial, sans-serif; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { width: auto; min-height: 0; margin: 0; padding: 0; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <strong>{{ $title }}</strong>
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

@if ($watermark)
    <div class="watermark">{{ $watermark }}</div>
@endif

<div class="sheet">
    <div class="head">
        <div class="agency">{{ setting('agency_name', 'Sugar Regulatory Administration') }}</div>
        <div class="address">{{ setting('agency_address', 'Sugar Center Building, North Avenue, Diliman, Quezon City') }}</div>
        <div class="form-title">PROJECT PROCUREMENT MANAGEMENT PLAN (PPMP){{ $number ? ' NO. ' . $number : '' }}</div>
        <div class="types">
            <span class="box">{{ $type === PpmpType::Indicative ? '✓' : '' }}</span>INDICATIVE
            <span class="box">{{ $type === PpmpType::Final ? '✓' : '' }}</span>FINAL
        </div>
    </div>

    <div class="meta">
        Fiscal Year: <b>{{ $fiscalYear }}</b><br>
        End-User or Implementing Unit: <b>{{ $endUser }}</b>
    </div>

    <table class="ppmp">
        <colgroup>
            <col style="width: 14%"><col style="width: 7%"><col style="width: 12%"><col style="width: 8.5%">
            <col style="width: 6.5%"><col style="width: 6.5%"><col style="width: 6.5%"><col style="width: 7.5%">
            <col style="width: 8%"><col style="width: 8%"><col style="width: 8.5%"><col style="width: 7%">
        </colgroup>
        <thead>
            <tr>
                <th class="group" colspan="5">PROCUREMENT PROJECT DETAILS</th>
                <th class="group" colspan="3">PROJECTED TIMELINE (MM/YYYY)</th>
                <th class="group" colspan="2">FUNDING DETAILS</th>
                <th class="group" rowspan="2">ATTACHED SUPPORTING DOCUMENTS</th>
                <th class="group" rowspan="2">REMARKS</th>
            </tr>
            <tr>
                <th>General Description and Objective of the Project to be Procured</th>
                <th>Type of the Project to be Procured (whether Goods, Infrastructure and Consulting Services)</th>
                <th>Quantity and Size of the Project to be Procured</th>
                <th>Recommended Mode of Procurement</th>
                <th>Pre-Procurement Conference, if applicable (Yes/No)</th>
                <th>Start of Procurement Activity</th>
                <th>End of Procurement Activity</th>
                <th>Expected Delivery / Implementation Period</th>
                <th>Source of Funds</th>
                <th>Estimated Budget / Authorized Budgetary Allocation (PhP)</th>
            </tr>
            <tr class="colno">
                @for ($c = 1; $c <= 12; $c++)<th>Column {{ $c }}</th>@endfor
            </tr>
        </thead>
        <tbody>
            @forelse ($paps as $pap)
                <tr class="pap"><td colspan="12">PAP CODE: {{ $pap->code }} - {{ $pap->title }}</td></tr>
                @foreach ($pap->items as $item)
                    @if ($item->id === $lastItemId)
                        </tbody><tbody class="keep">{{-- last row + total stay on one page --}}
                    @endif
                    <tr>
                        <td class="desc">{{ $item->description }}</td>
                        <td class="c">{{ \Illuminate\Support\Str::before($item->project_type->label(), ' Services') }}</td>
                        <td class="qty">
                            @if ($qty($item))QTY: {{ $qty($item) }}@endif
                            @if ($item->quantity_size)<div class="specs">Specs: {{ $item->quantity_size }}</div>@endif
                        </td>
                        <td class="c">{{ $item->procurementMode->name }}</td>
                        <td class="c">{{ $item->pre_proc_conference ? 'Yes' : 'No' }}</td>
                        <td class="c">{{ $item->proc_start->format('F Y') }}</td>
                        <td class="c">{{ $item->proc_end->format('F Y') }}</td>
                        <td class="c">{{ $item->delivery_period }}</td>
                        <td class="c">{{ $item->fundSource->name }} ({{ $item->fundSource->code }})</td>
                        <td class="r">{{ $peso($item->estimated_budget) }}</td>
                        <td class="c">
                            @if ($item->marketScopingComplete())Market Scoping Checklist<br>@endif
                            @foreach ($item->attachments->groupBy('kind') as $kind => $files){{ config("market_scoping.attachment_kinds.$kind", $kind) }}{{ $files->count() > 1 ? ' (' . $files->count() . ')' : '' }}<br>@endforeach
                            {{ $item->supporting_documents }}
                        </td>
                        <td class="c">@if ($item->is_epa)Early Procurement Activity<br>@endif{{ $item->remarks }}</td>
                    </tr>
                @endforeach
            @empty
                <tr><td colspan="12" style="text-align: center; padding: 14px;">No procurement projects.</td></tr>
            @endforelse
            {{-- Not a <tfoot>: browsers repeat tfoot on every printed page --}}
            <tr class="total">
                <td colspan="8" style="border: 0;"></td>
                <td>TOTAL BUDGET:</td>
                <td class="r" style="font-style: normal;">{{ $peso($total) }}</td>
                <td colspan="2" style="border: 0;"></td>
            </tr>
        </tbody>
    </table>

    <div class="signatories">
        <div class="sig">
            <div class="label">Prepared by:</div>
            <div class="name">{{ $prepared['name'] }}</div>
            <div class="hint">Signature over Printed Name</div>
            <div class="pos">{{ $prepared['position'] }}</div>
            <div class="role">[End-User or Implementing Unit]</div>
            <div class="date">Date: {{ $prepared['date']?->format('m/d/Y') ?? '____________' }}</div>
        </div>
        <div class="sig">
            <div class="label">Submitted by:</div>
            <div class="name">{{ $submitted['name'] }}</div>
            <div class="hint">Signature over Printed Name</div>
            <div class="pos">{{ $submitted['position'] }}</div>
            <div class="role">[Head of the End-User or Implementing Unit]</div>
            <div class="date">Date: {{ $submitted['date']?->format('m/d/Y') ?? '____________' }}</div>
        </div>
    </div>

    <div class="footer-note">
        <span>{{ $footer }}</span>
        <span>Printed {{ now()->format('m/d/Y h:i A') }} by {{ auth()->user()->fullname }}</span>
    </div>
</div>

<script>
    document.getElementById('paper').addEventListener('change', function () {
        let style = document.getElementById('page-size');
        if (!style) {
            style = document.createElement('style');
            style.id = 'page-size';
            document.head.appendChild(style);
        }
        style.textContent = '@page { size: ' + this.value + '; }';
    });
</script>
</body>
</html>
