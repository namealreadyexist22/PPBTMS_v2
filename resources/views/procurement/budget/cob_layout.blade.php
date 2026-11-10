{{--
    Print layout of the Corporate Operating Budget forms (A4 landscape, browser Print / Save as PDF).
    Sections: @yield('toolbar') (screen only), @yield('sheets') (one .sheet per printed page group).
    Signatories come from Signatories & BAC (agency-wide) and Budget Allocation (who submits, per department).
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 8.5pt; color: #000; margin: 0; background: #e5e7eb; }
        .sheet { background: #fff; width: 297mm; min-height: 210mm; margin: 12px auto; padding: 10mm; position: relative; page-break-after: always; }
        .sheet:last-child { page-break-after: auto; }

        .toolbar { position: sticky; top: 0; z-index: 10; background: #1f2937; color: #fff; padding: 8px 16px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; font-size: 13px; }
        .toolbar select, .toolbar button, .toolbar a.btn { font-size: 13px; padding: 5px 10px; border-radius: 5px; border: 0; cursor: pointer; text-decoration: none; }
        .toolbar a.btn { background: #374151; color: #fff; }
        .toolbar a.btn.active { background: #2563eb; }
        .toolbar .primary { background: #2563eb; color: #fff; }
        .toolbar .spacer { flex: 1; }
        .toolbar label { display: inline-flex; gap: 5px; align-items: center; cursor: pointer; }
        .notice { max-width: 297mm; margin: 10px auto 0; background: #fef3c7; border: 1px solid #f59e0b; color: #78350f; padding: 8px 12px; border-radius: 6px; font-size: 12px; }

        .form-tag { position: absolute; top: 8mm; right: 10mm; font-style: italic; font-weight: bold; font-size: 9pt; }
        .title { text-align: center; font-weight: bold; font-size: 10.5pt; line-height: 1.35; margin: 4mm 0 3mm; }
        .title .sub { font-weight: normal; }

        table.cob { width: 100%; border-collapse: collapse; }
        table.cob th, table.cob td { border: 1px solid #000; padding: 2px 4px; vertical-align: middle; }
        table.cob th { background: #9dc3e6; text-align: center; font-size: 8pt; }
        table.cob thead { display: table-header-group; }
        table.cob tr { page-break-inside: avoid; }
        table.cob td.r { text-align: right; white-space: nowrap; }
        table.cob td.c { text-align: center; }
        table.cob td.code { white-space: nowrap; font-weight: bold; text-align: center; }
        table.cob tr.fund td { background: #70ad47; font-weight: bold; }
        table.cob tr.division td { background: #a9d08e; font-weight: bold; }
        table.cob tr.section td.name { background: #bdd7ee; font-weight: bold; }
        table.cob tr.subtotal td { background: #f4b183; font-weight: bold; }
        table.cob tr.subtotal td.label { text-align: center; }
        table.cob tr.total td { background: #9dc3e6; font-weight: bold; }
        table.cob tr.grand td { background: #f4b183; font-weight: bold; font-size: 9pt; }
        table.cob tr.location td.name { padding-left: 14px; }
        table.cob td.muted { color: #555; font-style: italic; }
        .empty { text-align: center; color: #555; padding: 12mm 0; font-style: italic; }

        .signatories { margin-top: 8mm; page-break-inside: avoid; padding: 0 10mm; }
        .sig-row { display: grid; grid-template-columns: 1fr 1fr; gap: 30mm; margin-bottom: 8mm; }
        .sig .label { margin-bottom: 9mm; }
        .sig .sig-name { font-weight: bold; text-transform: uppercase; min-height: 12px; }
        .sig .sig-pos { min-height: 12px; }
        .sig .sig-blank { border-bottom: 1px solid #000; width: 60mm; }
        .sig .sig-blank span { color: #b45309; font-weight: normal; text-transform: none; font-size: 8pt; }

        @media print {
            body { background: #fff; }
            .toolbar, .notice, .no-print { display: none !important; }
            .sheet { margin: 0; padding: 0; width: auto; min-height: 0; }
            .form-tag { top: 0; right: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">@yield('toolbar')</div>
    @yield('notice')
    @yield('sheets')
</body>
</html>
