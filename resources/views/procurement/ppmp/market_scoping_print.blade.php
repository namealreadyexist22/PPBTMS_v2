{{--
    GPPB Market Scoping Checklist (NGPA standard form, RA 12009 Sec. 10): one project, or all of a
    PPMP's projects one per page. Expects $ppmp, $items, $prepared, $head.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Market Scoping Checklist{{ $items->count() > 1 ? 's' : '' }} — {{ $ppmp->ppmp_no }}</title>
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
        .sheet + .sheet { page-break-before: always; break-before: page; }
        @media print { body { background: #fff; } .toolbar { display: none; } .sheet { width: auto; min-height: 0; margin: 0; padding: 0; } }
    </style>
</head>
<body>
<div class="toolbar"><span>Market Scoping Checklist{{ $items->count() > 1 ? 's (' . $items->count() . ' projects)' : '' }} — {{ $ppmp->ppmp_no }}</span><span style="flex:1"></span><button onclick="window.print()">Print / Save as PDF</button></div>
@forelse ($items as $item)
    @include('procurement.ppmp.extras.market_scoping_sheet', ['item' => $item])
@empty
    <div class="sheet">No procurement projects yet.</div>
@endforelse
</body>
</html>
