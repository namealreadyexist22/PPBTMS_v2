{{--
    Request for Quotation, SRA form FM-AFD-PPS-015 Rev.02; one sheet per copy (one per supplier the canvassers visit).
    The supplier writes its name and address on the lines at the top left and its offers in the OFFER column.
    Expects: $rfq (with items.line.request), $copies.
--}}
@php
    use App\Enums\RfqStatus;
    $money = fn ($amount) => number_format((float) $amount, 2);
    $qty = fn ($q) => rtrim(rtrim(number_format((float) $q, 2), '0'), '.');
    $days = fn ($v) => is_numeric($v) ? (int) $v : '____';
    $watermark = match ($rfq->status) {
        RfqStatus::Draft     => 'DRAFT',
        RfqStatus::Cancelled => 'CANCELLED',
        default              => null,
    };
    $prNos = $rfq->requests()->pluck('request_no')->filter()->implode(', ');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $rfq->title() }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm 14mm; }
        * { box-sizing: border-box; }
        body { font-family: "Open Sans", "Segoe UI", Arial, sans-serif; font-size: 9.5pt; color: #000; margin: 0; background: #e5e7eb; }
        .sheet { background: #fff; width: 210mm; min-height: 297mm; margin: 12px auto; padding: 12mm 14mm; position: relative; page-break-after: always; }
        .sheet:last-child { page-break-after: auto; }

        .toolbar { position: sticky; top: 0; z-index: 10; background: #1f2937; color: #fff; padding: 8px 16px; display: flex; gap: 12px; align-items: center; font: 13px Arial, sans-serif; }
        .toolbar button, .toolbar select { font-size: 13px; padding: 5px 12px; border-radius: 5px; border: 0; cursor: pointer; }
        .toolbar .primary { background: #2563eb; color: #fff; }
        .toolbar .spacer { flex: 1; }

        .head { display: flex; align-items: center; gap: 14px; margin-left: 30px; }
        .head img { height: 82px; }
        .head .t { line-height: 1.3; font-size: 10pt; }
        .head .t b { font-size: 10.5pt; }
        .head .t .title { font-size: 14pt; font-weight: bold; margin-top: 2px; }

        .top { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 18px; }
        .supplier div { width: 135px; border-bottom: 1px solid #000; height: 15px; }
        .ref { display: flex; gap: 12px; align-items: center; }
        .ref table td { padding: 0 4px 0 0; font-size: 9pt; }
        .ref .qr { width: 64px; height: 64px; }
        .ref .qr img, .ref .qr canvas { width: 64px !important; height: 64px !important; }

        .intro { margin-top: 14px; font-size: 10.5pt; line-height: 1.6; text-align: justify; }
        .intro .due { font-weight: bold; text-decoration: underline; text-transform: uppercase; }
        .signatory { text-align: right; margin: 34px 70px 0 0; font-size: 9pt; }
        .signatory > div { display: inline-block; text-align: center; }
        .signatory .name { font-weight: bold; text-decoration: underline; }

        .note { display: flex; gap: 10px; margin-top: 26px; font-size: 8.3pt; }
        .note .k { width: 90px; flex-shrink: 0; }
        .note ol { margin: 0; padding-left: 16px; text-transform: uppercase; text-align: justify; }
        .note li { margin-bottom: 1px; }

        table.items { width: 100%; border-collapse: collapse; margin-top: 14px; font-size: 9pt; }
        table.items th, table.items td { border: 1px solid #000; padding: 4px 6px; vertical-align: middle; }
        table.items th { font-weight: bold; font-size: 8.5pt; height: 26px; text-align: center; }
        table.items td.c { text-align: center; vertical-align: top; }
        table.items td.r { text-align: right; }
        table.items .name { font-weight: bold; }
        table.items .specs { white-space: pre-line; }
        table.items td.offer { vertical-align: bottom; padding: 4px 6px 6px; }
        table.items td.offer div { border-bottom: 1px solid #000; height: 14px; }
        table.items tr.total td { font-weight: bold; }

        .accept { margin-top: 26px; font-size: 10.5pt; }
        .sign { display: flex; margin-top: 20px; font-size: 9pt; }
        .sign .canvass { width: 37%; }
        .sign .canvass .who { margin: 22px 0 0 75px; text-align: center; display: inline-block; text-transform: uppercase; }
        .sign .canvass .who div:last-child { text-transform: none; }
        .sign .owner { flex: 1; }
        .sign .owner .line { width: 235px; margin: 22px 0 0 128px; border-top: 1px solid #000; text-align: center; padding-top: 2px; }

        .foot { text-align: right; font-family: "Times New Roman", serif; font-size: 9pt; margin-top: 24px; line-height: 1.4; }
        .watermark { position: absolute; top: 40%; left: 0; right: 0; text-align: center; font-size: 80pt; color: rgba(220, 38, 38, .12); transform: rotate(-30deg); pointer-events: none; font-weight: bold; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; width: auto; min-height: 0; padding: 0; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <strong>{{ $rfq->title() }}</strong>
    <span>{{ $rfq->status->label() }}</span>
    <span class="spacer"></span>
    <label>Copies
        <select onchange="const u = new URL(location.href); u.searchParams.set('copies', this.value); location.href = u;">
            @for ($n = 1; $n <= 10; $n++)<option value="{{ $n }}" @selected($n === $copies)>{{ $n }}</option>@endfor
        </select>
    </label>
    <button class="primary" onclick="window.print()">Print / Save as PDF</button>
    <button onclick="window.close()">Close</button>
</div>

@for ($copy = 1; $copy <= $copies; $copy++)
<div class="sheet">
    @if ($watermark)<div class="watermark">{{ $watermark }}</div>@endif

    <div class="head">
        <img src="{{ asset('assets/img/sra-da.png') }}" alt="">
        <div class="t">
            <div>Republic of the Philippines</div>
            <div>Department of Agriculture</div>
            <b>SUGAR REGULATORY ADMINISTRATION</b>
            <div>North Avenue, Diliman, Quezon City</div>
            <div>Telefax No. (02) 8929-61-36</div>
            <div class="title">REQUEST FOR QUOTATION</div>
        </div>
    </div>

    <div class="top">
        {{-- The supplier's name and address, written in --}}
        <div class="supplier"><div></div><div></div><div></div></div>
        <div class="ref">
            <table>
                <tr><td>Quotation No.</td><td><b>{{ $rfq->rfq_no }}</b></td></tr>
                <tr><td>PR No.:</td><td><b>{{ $prNos }}</b></td></tr>
                <tr><td>RFQ Date:</td><td><b>{{ $rfq->issued_at?->format('F d, Y') }}</b></td></tr>
            </table>
            <div class="qr" data-text="{{ route('procurement.rfqs.show', $rfq) }}"></div>
        </div>
    </div>

    <div class="intro">
        Please quote your lowest price on the item/s listed below, subject to the General Conditions, stating the shortest time of
        delivery and submit your quotation duly signed by your representative not later than
        <span class="due">{{ $rfq->submission_deadline?->format('F d, Y') ?? '____________________' }}</span>.
    </div>

    <div class="signatory">
        <div>
            <div class="name">{{ $rfq->signatory_name }}</div>
            <div>{{ $rfq->signatory_designation }}</div>
        </div>
    </div>

    <div class="note">
        <div class="k">NOTE:</div>
        <ol>
            <li>Suppliers shall quote their <b>lowest net price</b> on the item/s listed below, giving <b>full and detailed description</b> of their offers to be submitted in a <b>sealed envelope</b> addressed to the Sugar Regulatory Administration.</li>
            <li>Delivery period shall be within {{ $days($rfq->delivery_period) }} calendar days.</li>
            <li>Price validity shall be for a period of {{ $days($rfq->price_validity) }} calendar days.</li>
            <li>Warranty shall be for a period of six (6) months for supplies &amp; materials, one (1) year for equipment, <b>from date of acceptance by the procuring entity.</b></li>
            <li><b>PhilGEPS certificate of registration and membership shall be attached upon submission of the quotation.</b></li>
            <li>Other requirements such as the updated <b>Mayor's/Business Permit, Income/Business Tax Return, Professional License/Curriculum Vitae (Consulting Services), PCAB License (Infrastructure Projects) and Omnibus Sworn Statement (for Small Value Procurement with Approved Budget for the Contract of ₱ 50,000.00 and above)</b> shall be submitted <b>prior to the issuance of Notice of Award.</b></li>
            @if ($rfq->isPerLot())<li><b>Quotations shall be evaluated and awarded per lot: quote all the items below; a quotation that leaves out an item shall not be considered.</b></li>@endif
            @if ($rfq->remarks)<li>{{ $rfq->remarks }}</li>@endif
        </ol>
    </div>

    <table class="items">
        <colgroup><col style="width: 8%"><col style="width: 7%"><col style="width: 10%"><col><col style="width: 12%"><col style="width: 11%"></colgroup>
        <thead>
            <tr><th>ITEM #</th><th>QTY.</th><th>UNIT</th><th>ITEM &amp; DESCRIPTION</th><th>ABC</th><th>OFFER</th></tr>
        </thead>
        <tbody>
            @foreach ($rfq->items as $i => $item)
                <tr>
                    <td class="c">{{ $i + 1 }}</td>
                    <td class="c">{{ $qty($item->line->quantity) }}</td>
                    <td class="c">{{ mb_strtoupper((string) $item->line->unit) }}</td>
                    <td>
                        <div class="name">{{ $item->line->description }}</div>
                        @if ($item->line->specifications)<div>@include('procurement.partials.specs', ['text' => $item->line->specifications, 'print' => true])</div>@endif
                    </td>
                    <td class="r">{{ $money($item->line->total_cost) }}</td>
                    <td class="offer"><div></div></td>
                </tr>
            @endforeach
            <tr class="total"><td colspan="4" class="r">TOTAL</td><td class="r">{{ $money($rfq->total_abc) }}</td><td></td></tr>
        </tbody>
    </table>

    <div class="accept">After having carefully read and accepted your General Conditions, I/We quote you on the item at prices noted above.</div>

    <div class="sign">
        <div class="canvass">
            <div>Canvassed by:</div>
            <div class="who">
                <div>{{ $rfq->canvassed_by_name ?: "\u{00A0}" }}</div>
                <div>Printed Name/ Signature</div>
            </div>
        </div>
        <div class="owner">
            <div>Owner/Authorized Representative:</div>
            <div class="line">Printed Name/ Signature</div>
            <div class="line">Tel. No. / Cellphone No.</div>
            <div class="line">Date</div>
        </div>
    </div>

    <div class="foot">FM-AFD-PPS-015,Rev.02<br>Effectivity Date: Sept 1, 2024</div>
</div>
@endfor

{{-- QR code of the RFQ (opens it in the system); skipped quietly when the library cannot load --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    if (window.QRCode) {
        document.querySelectorAll('.qr').forEach((el) => new QRCode(el, { text: el.dataset.text, width: 128, height: 128, correctLevel: QRCode.CorrectLevel.M }));
    }
</script>
</body>
</html>
