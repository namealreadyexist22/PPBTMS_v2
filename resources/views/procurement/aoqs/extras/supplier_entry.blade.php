{{-- A supplier's quotation on the AOQ: its details and a unit price per RFQ item (blank = no quote).
     Expects $aoq (with rfq.items.line), $supplier (null when adding), $known (suppliers of earlier abstracts). --}}
@php
    $qty = fn ($q) => rtrim(rtrim(number_format((float) $q, 2), '0'), '.');
    $quotes = $supplier?->quotes->keyBy('rfq_item_id') ?? collect();
@endphp
<div class="modal fade" id="{{ $modalName }}" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl modal-fullscreen-lg-down">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-white py-3">
                <div>
                    <h5 class="modal-title fw-bold mb-0">{{ $supplier ? 'Change Quotation' : 'Add Supplier' }}</h5>
                    <div class="small text-muted">{{ $aoq->title() }} · {{ $aoq->rfq->label() }}</div>
                </div>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form_aoq_supplier" autocomplete="off">
                @csrf
                <input type="hidden" name="id" value="{{ $supplier?->id }}">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted mb-1" for="aoq_sup_name">Supplier</label>
                            <input type="text" name="name" id="aoq_sup_name" class="form-control form-control-sm" value="{{ $supplier?->name }}" maxlength="255" list="aoq_known_suppliers" required placeholder="Business name">
                            <datalist id="aoq_known_suppliers">
                                @foreach ($known as $k)
                                    <option value="{{ $k->name }}" data-address="{{ $k->address }}" data-tin="{{ $k->tin }}" data-contact="{{ $k->contact }}"></option>
                                @endforeach
                            </datalist>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted mb-1">Address</label>
                            <input type="text" name="address" class="form-control form-control-sm" value="{{ $supplier?->address }}" maxlength="500">
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <label class="form-label small fw-semibold text-muted mb-1">TIN</label>
                            <input type="text" name="tin" class="form-control form-control-sm" value="{{ $supplier?->tin }}" maxlength="50">
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <label class="form-label small fw-semibold text-muted mb-1">Contact</label>
                            <input type="text" name="contact" class="form-control form-control-sm" value="{{ $supplier?->contact }}" maxlength="255" placeholder="Phone or email">
                        </div>
                        <div class="col-sm-6 col-md-2">
                            <label class="form-label small fw-semibold text-muted mb-1">Responsive</label>
                            <select name="is_responsive" class="form-select form-select-sm">
                                <option value="1" @selected($supplier?->is_responsive ?? true)>Yes</option>
                                <option value="0" @selected($supplier && ! $supplier->is_responsive)>No</option>
                            </select>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <label class="form-label small fw-semibold text-muted mb-1">Remarks</label>
                            <input type="text" name="remarks" class="form-control form-control-sm" value="{{ $supplier?->remarks }}" maxlength="500" placeholder="e.g. no PhilGEPS registration">
                        </div>
                    </div>

                    <div class="fw-semibold mt-4 mb-1">Offer per item</div>
                    <div class="small text-muted mb-2">Leave the unit price blank for an item it did not quote. Untick "Meets specs" when the offer does not comply.</div>
                    <div class="table-responsive border rounded-3">
                        <table class="table align-middle small mb-0">
                            <thead class="table-light">
                                <tr class="text-nowrap">
                                    <th class="ps-3" style="width: 40px;">#</th>
                                    <th>Item</th>
                                    <th class="text-end">ABC</th>
                                    <th style="width: 150px;">Unit price</th>
                                    <th class="text-end" style="width: 130px;">Total</th>
                                    <th style="min-width: 200px;">Brand / offer</th>
                                    <th class="text-center pe-3" style="width: 90px;">Meets specs</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($aoq->rfq->items as $i => $item)
                                    @php $line = $item->line; $q = $quotes->get($item->id); @endphp
                                    <tr class="js-quote-row" data-qty="{{ (float) $line->quantity }}" data-abc="{{ (float) $line->total_cost }}">
                                        <td class="ps-3 text-muted">{{ $i + 1 }}</td>
                                        <td>
                                            <div class="fw-semibold">{{ $line->description }}</div>
                                            <div class="text-muted">{{ $qty($line->quantity) }} {{ $line->unit }}</div>
                                        </td>
                                        <td class="text-end text-nowrap">{{ number_format((float) $line->total_cost, 2) }}<div class="text-muted">@ {{ number_format((float) $line->unit_cost, 2) }}</div></td>
                                        <td><input type="number" name="quotes[{{ $item->id }}][unit_price]" class="form-control form-control-sm text-end js-price" value="{{ $q?->unit_price }}" min="0" step="0.01" placeholder="No quote" aria-label="Unit price of {{ $line->description }}"></td>
                                        <td class="text-end text-nowrap fw-semibold js-total">—</td>
                                        <td><input type="text" name="quotes[{{ $item->id }}][brand]" class="form-control form-control-sm" value="{{ $q?->brand }}" maxlength="500" aria-label="Brand or offer for {{ $line->description }}"></td>
                                        <td class="text-center pe-3">
                                            <input type="hidden" name="quotes[{{ $item->id }}][is_compliant]" value="0">
                                            <input type="checkbox" class="form-check-input" name="quotes[{{ $item->id }}][is_compliant]" value="1" @checked($q?->is_compliant ?? true) aria-label="{{ $line->description }} meets the specifications">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="table-light fw-bold">
                                    <td colspan="4" class="text-end">Total quoted</td>
                                    <td class="text-end text-nowrap" id="aoq_sup_total">—</td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-body-tertiary border-0 py-2">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary px-3" id="btn_save_supplier"><i class="fas fa-save me-1"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
(function () {
    const fmt = (n) => n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    function totals() {
        let sum = 0, any = false;
        $('#form_aoq_supplier .js-quote-row').each(function () {
            const row = $(this), price = parseFloat(row.find('.js-price').val());
            const cell = row.find('.js-total').removeClass('text-danger').attr('title', '');
            if (isNaN(price)) { cell.text('—'); return; }
            const total = Math.round(price * row.data('qty') * 100) / 100;
            cell.text(fmt(total));
            if (total > row.data('abc')) cell.addClass('text-danger').attr('title', 'Over the ABC: it will not count');
            sum += total; any = true;
        });
        $('#aoq_sup_total').text(any ? fmt(sum) : '—');
    }
    $('#form_aoq_supplier').on('input', '.js-price', totals);
    totals();

    // A supplier from an earlier abstract fills in its details
    $('#aoq_sup_name').on('change', function () {
        const opt = $('#aoq_known_suppliers option').filter((_, o) => o.value === this.value).get(0);
        if (!opt) return;
        ['address', 'tin', 'contact'].forEach((f) => {
            const input = $('#form_aoq_supplier [name=' + f + ']');
            if (!input.val()) input.val(opt.dataset[f] || '');
        });
    });
})();
</script>
