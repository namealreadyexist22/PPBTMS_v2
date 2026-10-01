@php
    use App\Enums\PpmpStatus;
    use App\Enums\PpmpType;

    $isFinalStatus = in_array($ppmp->status, [PpmpStatus::Approved, PpmpStatus::Superseded], true);
    $watermark = match ($ppmp->status) {
        PpmpStatus::Approved   => null,
        PpmpStatus::Superseded => 'SUPERSEDED',
        PpmpStatus::Submitted  => 'FOR APPROVAL',
        default                => 'DRAFT',
    };
    $mmYyyy = fn ($date) => $date ? $date->format('m/Y') : '';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>PPMP {{ $ppmp->ppmp_no }}</title>
    <style>
        @page { size: A4 landscape; margin: 10mm 10mm 12mm; }

        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 9pt; color: #000; margin: 0; background: #e5e7eb; }
        .sheet { background: #fff; width: 297mm; min-height: 210mm; margin: 12px auto; padding: 10mm; position: relative; }

        .toolbar { position: sticky; top: 0; z-index: 10; background: #1f2937; color: #fff; padding: 8px 16px; display: flex; gap: 12px; align-items: center; font-size: 13px; }
        .toolbar button, .toolbar select { font-size: 13px; padding: 5px 12px; border-radius: 5px; border: 0; cursor: pointer; }
        .toolbar .primary { background: #2563eb; color: #fff; }
        .toolbar .spacer { flex: 1; }

        .head { display: flex; align-items: center; justify-content: center; gap: 12px; text-align: center; margin-bottom: 6px; }
        .head img { height: 52px; }
        .head .agency { font-size: 12pt; font-weight: bold; text-transform: uppercase; }
        .head .office { font-size: 9pt; }
        h1 { text-align: center; font-size: 13pt; margin: 6px 0 2px; letter-spacing: .5px; }

        .meta { display: flex; justify-content: space-between; align-items: flex-end; margin: 8px 0 6px; gap: 20px; }
        .meta .field { white-space: nowrap; }
        .meta .line { display: inline-block; border-bottom: 1px solid #000; min-width: 60mm; padding: 0 4px; font-weight: bold; }
        .box { display: inline-block; width: 11px; height: 11px; border: 1px solid #000; vertical-align: -1px; margin: 0 3px 0 10px; text-align: center; line-height: 10px; font-size: 9px; font-weight: bold; }

        table.ppmp { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.ppmp th, table.ppmp td { border: 1px solid #000; padding: 3px 4px; vertical-align: top; word-wrap: break-word; }
        table.ppmp th { font-size: 7pt; text-align: center; vertical-align: middle; background: #f2f2f2; overflow-wrap: normal; word-wrap: normal; hyphens: manual; }
        table.ppmp th.group { background: #d9d9d9; font-size: 8pt; }
        table.ppmp td.c { text-align: center; }
        table.ppmp td.r { text-align: right; white-space: nowrap; }
        table.ppmp thead { display: table-header-group; }   /* repeat column headers on every page */
        table.ppmp tr { page-break-inside: avoid; }
        table.ppmp .colno th { font-weight: normal; font-style: italic; background: #fff; font-size: 7pt; padding: 1px; }
        table.ppmp tr.total td { font-weight: bold; background: #f2f2f2; }

        .signatories { display: flex; justify-content: space-between; gap: 24px; margin-top: 18px; page-break-inside: avoid; }
        .sig { flex: 1; }
        .sig .label { margin-bottom: 26px; }
        .sig .name { border-top: 1px solid #000; text-align: center; font-weight: bold; text-transform: uppercase; padding-top: 2px; }
        .sig .pos, .sig .date { text-align: center; font-size: 8pt; }

        .watermark { position: fixed; top: 40%; left: 0; right: 0; text-align: center; font-size: 90pt; font-weight: bold; color: rgba(220, 38, 38, .12); transform: rotate(-20deg); pointer-events: none; z-index: 0; }
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
    <strong>PPMP {{ $ppmp->ppmp_no }}</strong>
    <span>{{ $ppmp->status->label() }}</span>
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
        <img src="{{ asset('assets/img/SRALOGO.png') }}" alt="">
        <div>
            <div class="agency">{{ setting('agency_name', 'Sugar Regulatory Administration') }}</div>
            <div class="office">{{ $ppmp->office->name }}</div>
        </div>
    </div>

    <h1>PROJECT PROCUREMENT MANAGEMENT PLAN (PPMP)</h1>

    <div class="meta">
        <div class="field">
            <span class="box">{{ $ppmp->type === PpmpType::Indicative ? '✓' : '' }}</span>INDICATIVE
            <span class="box">{{ $ppmp->type === PpmpType::Final ? '✓' : '' }}</span>FINAL
        </div>
        <div class="field">PPMP No.: <span class="line" style="min-width: 35mm;">{{ $ppmp->ppmp_no }}</span></div>
        <div class="field">Fiscal Year: <span class="line" style="min-width: 25mm;">{{ $ppmp->fiscal_year }}</span></div>
        <div class="field">End-User or Implementing Unit: <span class="line">{{ $ppmp->office->code }} — {{ $ppmp->office->name }}</span></div>
    </div>

    <table class="ppmp">
        <colgroup>
            <col style="width: 17%"><col style="width: 8%"><col style="width: 7%"><col style="width: 8%">
            <col style="width: 7.5%"><col style="width: 7%"><col style="width: 7%"><col style="width: 8.5%">
            <col style="width: 5.5%"><col style="width: 9%"><col style="width: 8%"><col style="width: 7.5%">
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
                <th>Type of the Project to be Procured (Goods, Infrastructure and Consulting Services)</th>
                <th>Quantity and Size of the Project to be Procured</th>
                <th>Recommended Mode of Procurement</th>
                <th>Pre-Procure&shy;ment Conference, if applicable (Yes/No)</th>
                <th>Start of Procure&shy;ment Activity</th>
                <th>End of Procure&shy;ment Activity</th>
                <th>Expected Delivery / Implemen&shy;tation Period</th>
                <th>Source of Funds</th>
                <th>Estimated Budget / Authorized Budgetary Allocation (PhP)</th>
            </tr>
            <tr class="colno">
                @for ($c = 1; $c <= 12; $c++)<th>Column {{ $c }}</th>@endfor
            </tr>
        </thead>
        <tbody>
            @forelse ($ppmp->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="c">{{ $item->project_type->label() }}</td>
                    <td class="c">
                        @if ($item->quantity !== null)
                            {{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }} {{ $item->unit?->name }}
                        @endif
                        @if ($item->quantity_size)<div>{{ $item->quantity_size }}</div>@endif
                    </td>
                    <td class="c">{{ $item->procurementMode->name }}</td>
                    <td class="c">{{ $item->pre_proc_conference ? 'Yes' : 'No' }}</td>
                    <td class="c">{{ $mmYyyy($item->proc_start) }}</td>
                    <td class="c">{{ $mmYyyy($item->proc_end) }}</td>
                    <td class="c">{{ $item->delivery_period }}</td>
                    <td class="c">{{ $item->fundSource->code }}</td>
                    <td class="r">{{ number_format((float) $item->estimated_budget, 2) }}</td>
                    <td>{{ $item->supporting_documents }}</td>
                    <td>{{ $item->remarks }}</td>
                </tr>
            @empty
                <tr><td colspan="12" class="c" style="padding: 14px;">No procurement projects.</td></tr>
            @endforelse
            {{-- Not a <tfoot>: browsers repeat tfoot on every printed page --}}
            <tr class="total">
                <td colspan="9" class="r">TOTAL</td>
                <td class="r">{{ number_format((float) $ppmp->total_budget, 2) }}</td>
                <td colspan="2"></td>
            </tr>
    </table>

    <div class="signatories">
        <div class="sig">
            <div class="label">Prepared by:</div>
            <div class="name">{{ $prepared?->name_snapshot }}</div>
            <div class="pos">{{ $prepared?->designation_snapshot ?: 'End-User' }}</div>
            <div class="date">Date: {{ $prepared?->signed_at?->format('m/d/Y') }}</div>
        </div>
        <div class="sig">
            <div class="label">Submitted by:</div>
            <div class="name">{{ $submitted?->name_snapshot }}</div>
            <div class="pos">{{ $submitted?->designation_snapshot ?: 'End-User / Implementing Unit' }}</div>
            <div class="date">Date: {{ $submitted?->signed_at?->format('m/d/Y') }}</div>
        </div>
        <div class="sig">
            <div class="label">Approved by:</div>
            <div class="name">{{ $approved?->name_snapshot ?? $approver?->fullname }}</div>
            <div class="pos">{{ $approved?->designation_snapshot ?? $approver?->designation ?? 'Head of Office' }}</div>
            <div class="date">Date: {{ $approved?->signed_at?->format('m/d/Y') }}</div>
        </div>
    </div>

    <div class="footer-note">
        <span>Version {{ str_pad($ppmp->version, 2, '0', STR_PAD_LEFT) }}{{ $ppmp->amended_from_id ? ' (amendment)' : '' }} · Status: {{ $ppmp->status->label() }}</span>
        <span>Printed {{ now()->format('m/d/Y h:i A') }} by {{ auth()->user()->fullname }}</span>
    </div>
</div>

<script>
    // Switch the @page size for the chosen paper
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
