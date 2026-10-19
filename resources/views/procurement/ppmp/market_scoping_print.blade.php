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
        @page { size: A4 portrait; margin: 12mm 14mm 12mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 9pt; color: #000; margin: 0; background: #e5e7eb; }
        .sheet { background: #fff; width: 210mm; min-height: 297mm; margin: 12px auto; padding: 12mm 14mm; display: flex; flex-direction: column; }
        .toolbar { position: sticky; top: 0; background: #1f2937; color: #fff; padding: 8px 16px; display: flex; gap: 12px; align-items: center; font-size: 13px; }
        .toolbar button { font-size: 13px; padding: 5px 12px; border-radius: 5px; border: 0; cursor: pointer; background: #2563eb; color: #fff; }

        .letterhead { display: flex; align-items: center; justify-content: center; gap: 14px; font-family: Calibri, Carlito, Arial, sans-serif; font-size: 10pt; line-height: 1.25; margin-bottom: 8px; }
        .letterhead img { height: 92px; }
        .letterhead .lh-text { text-align: center; }
        .letterhead .lh-agency { font-size: 16pt; font-weight: bold; }
        .title { text-align: center; font-weight: bold; font-size: 14pt; margin: 10px 0 6px; }

        h3 { font-size: 9pt; font-weight: bold; margin: 10px 0 4px; }
        .normal { font-weight: normal; }
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1px solid #000; padding: 3px 6px; vertical-align: top; }
        th { background: #f2f2f2; font-size: 8.5pt; text-align: center; vertical-align: middle; }
        td.label { width: 36%; font-weight: bold; background: #f2f2f2; }
        .sub { font-size: 8pt; font-weight: normal; }
        .center { text-align: center; vertical-align: middle; }
        td.check { font-family: "Segoe UI Symbol", "DejaVu Sans", sans-serif; font-size: 12pt; }
        td.rec { white-space: pre-line; }
        .note { font-size: 8pt; margin: 3px 0 5px; text-align: justify; }
        .note ul { margin: 2px 0 0; padding-left: 18px; }

        table.sigs { margin-top: 14px; }
        table.sigs td { border: 0; width: 50%; padding: 0 8px 0 0; font-size: 9pt; }
        table.sigs .role { font-size: 8.5pt; }
        table.sigs .sign-line { height: 26px; }
        table.sigs .name { font-weight: bold; text-transform: uppercase; }

        .footer { margin-top: auto; padding-top: 12px; display: flex; align-items: flex-end; justify-content: space-between; }
        .footer .bp { height: 58px; }
        .footer .iso { height: 36px; }
        .footer .ft { font-size: 8pt; color: #333; }

        .sheet + .sheet { page-break-before: always; break-before: page; }
        @media print { body { background: #fff; } .toolbar { display: none; } .sheet { width: auto; min-height: 270mm; margin: 0; padding: 0; } }
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
