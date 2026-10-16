{{--
    Request for Quotation in the SRA style; one sheet per copy (one per supplier the canvassers visit).
    The supplier fills in its details, brand / model and prices. Expects: $rfq (with items.line.request), $copies.
--}}
@php
    use App\Enums\RfqStatus;
    $money = fn ($amount) => number_format((float) $amount, 2);
    $qty = fn ($q) => rtrim(rtrim(number_format((float) $q, 2), '0'), '.');
    $watermark = match ($rfq->status) {
        RfqStatus::Draft     => 'DRAFT',
        RfqStatus::Cancelled => 'CANCELLED',
        default              => null,
    };
    $requests = $rfq->requests()->map->title()->map(fn ($t) => preg_replace('/^(PR|JR) /', '', $t))->implode(', ');
    $deadline = $rfq->submission_deadline?->format('F d, Y \a\t g:i A');
    $terms = array_filter([
        'Delivery period'   => $rfq->delivery_period,
        'Place of delivery' => $rfq->delivery_place,
        'Price validity'    => $rfq->price_validity,
        'Warranty'          => $rfq->warranty,
        'Payment terms'     => $rfq->payment_terms,
    ]);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $rfq->title() }}</title>
    <style>
        @page { size: A4 portrait; margin: 10mm; }
        * { box-sizing: border-box; }
        body { font-family: Calibri, Carlito, "Segoe UI", Arial, sans-serif; font-size: 9pt; color: #000; margin: 0; background: #e5e7eb; }
        .sheet { background: #fff; width: 210mm; min-height: 297mm; margin: 12px auto; padding: 10mm; position: relative; display: flex; flex-direction: column; page-break-after: always; }
        .sheet:last-child { page-break-after: auto; }

        .toolbar { position: sticky; top: 0; z-index: 10; background: #1f2937; color: #fff; padding: 8px 16px; display: flex; gap: 12px; align-items: center; font: 13px Arial, sans-serif; }
        .toolbar button, .toolbar select { font-size: 13px; padding: 5px 12px; border-radius: 5px; border: 0; cursor: pointer; }
        .toolbar .primary { background: #2563eb; color: #fff; }
        .toolbar .spacer { flex: 1; }

        .label-top { text-align: right; color: #b91c1c; font-family: "Times New Roman", serif; font-size: 9pt; margin-bottom: 2px; text-transform: uppercase; }
        .form { border: 1px solid #000; flex: 1; display: flex; flex-direction: column; }
        .head { display: flex; align-items: center; justify-content: center; gap: 14px; padding: 6px 0 8px; border-bottom: 1px solid #000; }
        .head img { height: 62px; }
        .head .t { text-align: center; line-height: 1.2; }
        .head .t .title { font-size: 17pt; font-weight: bold; }
        .head .t .agency { font-size: 8pt; font-weight: bold; }

        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 3px 5px; vertical-align: top; }
        .meta td { border-bottom: 1px solid #000; height: 22px; vertical-align: middle; font-size: 8.5pt; }
        .meta td.k { font-weight: bold; width: 17%; white-space: nowrap; }
        .meta td.v { font-weight: bold; }
        .meta td.sep { border-left: 1px solid #000; }

        .intro { padding: 6px 8px; font-size: 8.5pt; line-height: 1.35; border-bottom: 1px solid #000; }
        .intro p { margin: 0 0 4px; text-indent: 30px; }
        .intro .sig { display: flex; justify-content: flex-end; margin-top: 18px; }
        .intro .sig div { text-align: center; min-width: 220px; }
        .intro .sig .name { font-weight: bold; text-transform: uppercase; border-top: 1px solid #000; padding-top: 1px; }

        /* The table grows to the bottom of the form; its last (fill) row takes the extra height, so the column rules run down */
        .lines { flex: 1; display: flex; flex-direction: column; }
        .lines table { flex: 1; table-layout: fixed; }
        .lines th { border-bottom: 1px solid #000; border-left: 1px solid #000; font-size: 8pt; text-align: center; vertical-align: middle; height: 30px; }
        .lines th:first-child, .lines td:first-child { border-left: 0; }
        .lines td { border-left: 1px solid #000; font-size: 8.5pt; }
        .lines td.c { text-align: center; }
        .lines td.r { text-align: right; }
        .lines .name { font-weight: bold; }
        .lines .specs { font-style: italic; white-space: pre-line; }
        .lines tbody tr { height: 1px; }          /* item rows keep their own height... */
        .lines tbody tr.fill { height: auto; }    /* ...and the empty last row takes the rest */
        .total td { border-top: 1px solid #000; font-size: 8.5pt; font-weight: bold; height: 20px; vertical-align: middle; }
        .total td + td { border-left: 1px solid #000; }

        .terms { border-top: 1px solid #000; padding: 4px 8px; font-size: 8.5pt; }
        .terms .h { font-weight: bold; margin-bottom: 2px; }
        .terms ol { margin: 0; padding-left: 18px; }
        .terms li { margin: 1px 0; }
        .terms .remarks { white-space: pre-line; }

        .conforme { border-top: 1px solid #000; padding: 4px 8px 6px; font-size: 8.5pt; }
        .conforme p { margin: 0 0 14px; }
        .conforme table td { padding: 0 6px; font-size: 8pt; text-align: center; }
        .conforme .line { border-top: 1px solid #000; padding-top: 1px; }

        .foot { text-align: right; font-family: "Times New Roman", serif; font-size: 7.5pt; margin-top: 3px; }
        .watermark { position: absolute; top: 40%; left: 0; right: 0; text-align: center; font-size: 80pt; color: rgba(220, 38, 38, .12); transform: rotate(-30deg); pointer-events: none; font-weight: bold; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; width: auto; min-height: 277mm; padding: 0; }
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
    <div class="label-top">{{ $rfq->procurementMode?->name }}</div>

    <div class="form">
        <div class="head">
            <img src="{{ asset('assets/img/sra-da.png') }}" alt="">
            <div class="t">
                <div class="title">REQUEST FOR QUOTATION</div>
                <div class="agency">SUGAR REGULATORY ADMINISTRATION</div>
                <div class="agency">North Avenue, Diliman, Quezon City</div>
                <div class="agency">Telefax No. (02) 8929-61-36</div>
            </div>
            <div style="width: 62px"></div>
        </div>

        {{-- Left: filled in by the supplier; right: the RFQ --}}
        <table class="meta">
            <tr><td class="k">Company Name:</td><td style="width: 43%"></td><td class="k sep">RFQ No.:</td><td class="v">{{ $rfq->rfq_no }}</td></tr>
            <tr><td class="k">Address:</td><td></td><td class="k sep">Date:</td><td class="v">{{ $rfq->issued_at?->format('M. d, Y') }}</td></tr>
            <tr><td class="k">TIN:</td><td></td><td class="k sep">PR / JR No.:</td><td class="v">{{ $requests }}</td></tr>
            <tr><td class="k">PhilGEPS Reg. No.:</td><td></td><td class="k sep">ABC:</td><td class="v">₱ {{ $money($rfq->total_abc) }}</td></tr>
        </table>

        <div class="intro">
            <div>Sir / Madam:</div>
            <p>Please quote your lowest price on the item/s listed below, subject to the terms and conditions stated herein, stating the
                shortest time of delivery, and submit your quotation duly signed by you or your duly authorized representative
                not later than <b>{{ $deadline ?? '______________________' }}</b>.</p>
            <p>Quotations above the Approved Budget for the Contract (ABC) shall be rejected.</p>
            <div class="sig">
                <div>
                    <div style="height: 16px"></div>
                    <div class="name">{{ $rfq->signatory_name }}</div>
                    <div>{{ $rfq->signatory_designation }}</div>
                </div>
            </div>
        </div>

        <div class="lines">
            <table>
                <colgroup>
                    <col style="width: 6%"><col style="width: 34%"><col style="width: 7%"><col style="width: 7%">
                    <col style="width: 11%"><col style="width: 13%"><col style="width: 10%"><col style="width: 12%">
                </colgroup>
                <thead>
                    <tr>
                        <th>Item No.</th><th>Item &amp; Description</th><th>Qty</th><th>Unit</th>
                        <th>ABC<br>(Unit Cost)</th><th>Brand / Model Offered</th><th>Unit Price</th><th>Total Price</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rfq->items as $i => $item)
                        <tr class="item">
                            <td class="c">{{ $i + 1 }}</td>
                            <td>
                                <div class="name">{{ $item->line->description }}</div>
                                @if ($item->line->specifications)<div class="specs">{{ $item->line->specifications }}</div>@endif
                            </td>
                            <td class="c">{{ $qty($item->line->quantity) }}</td>
                            <td class="c">{{ mb_strtoupper((string) $item->line->unit) }}</td>
                            <td class="r">{{ $money($item->line->unit_cost) }}</td>
                            <td></td><td></td><td></td>
                        </tr>
                    @endforeach
                    <tr class="fill">@for ($c = 0; $c < 8; $c++)<td></td>@endfor</tr>
                </tbody>
            </table>
        </div>
        <table class="total">
            <tr>
                <td style="width: 54%; text-align: right">TOTAL ABC: ₱ {{ $money($rfq->total_abc) }}</td>
                <td style="width: 34%; text-align: right">TOTAL QUOTED PRICE:</td>
                <td></td>
            </tr>
        </table>

        <div class="terms">
            <div class="h">Terms and conditions</div>
            <ol>
                @foreach ($terms as $label => $value)
                    <li>{{ $label }}: <b>{{ $value }}</b></li>
                @endforeach
                <li>Prices quoted must be inclusive of all applicable taxes, delivery and other charges.</li>
                <li>Award shall be made per {{ $rfq->items->count() > 1 ? 'item / lot' : 'item' }} to the lowest calculated and responsive quotation that complies with the specifications.</li>
                @if ($rfq->remarks)<li class="remarks">{{ $rfq->remarks }}</li>@endif
            </ol>
        </div>

        <div class="conforme">
            <p>After having carefully read and accepted the terms and conditions, I/We submit our quotation for the item/s as indicated above.</p>
            <table>
                <tr>
                    <td><div style="height: 18px"></div><div class="line">Signature over Printed Name</div></td>
                    <td><div style="height: 18px"></div><div class="line">Position / Designation</div></td>
                    <td><div style="height: 18px"></div><div class="line">Contact No. / E-mail</div></td>
                    <td style="width: 16%"><div style="height: 18px"></div><div class="line">Date</div></td>
                </tr>
            </table>
        </div>
    </div>

    <div class="foot">{{ $rfq->title() }}@if ($copies > 1) · copy {{ $copy }} of {{ $copies }}@endif</div>
</div>
@endfor
</body>
</html>
