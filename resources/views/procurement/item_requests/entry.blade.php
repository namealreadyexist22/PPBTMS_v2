{{-- New item request: a standard item another office procures, the fiscal year, quantity and purpose. Expects $office, $items, $years. --}}
<div class="modal fade" id="ITEM_REQUEST_MODAL" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <form class="modal-content border-0 shadow js-ir-form" data-action="{{ route('procurement.item-requests.store') }}" autocomplete="off">
            @if ($office->id !== auth()->user()->office_id)<input type="hidden" name="office_id" value="{{ $office->id }}">@endif
            <div class="modal-header bg-light py-3">
                <div>
                    <h5 class="modal-title fw-bold"><i class="fas fa-hand-holding me-2"></i>New Item Request</h5>
                    <div class="small text-muted">For {{ $office->shortName() }} — {{ $office->name }}</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 small">
                @if ($items->isEmpty())
                    <div class="text-center text-muted py-4">No standard item is procured by another office for yours (a category with a designated office, set in Procurement Lookups).</div>
                @else
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold text-muted mb-1" for="ir_item">Item</label>
                            <select name="item_id" id="ir_item" class="form-select form-select-sm" required>
                                <option value="">Choose…</option>
                                @foreach ($items->groupBy(fn ($i) => $i->category->restrictedOffice?->shortName() ?? '—') as $by => $group)
                                    <optgroup label="Procured by {{ $by }}">
                                        @foreach ($group as $item)
                                            <option value="{{ $item->id }}" data-unit="{{ $item->unit?->name }}" data-by="{{ $by }}"
                                                    data-specs="{{ view('procurement.partials.specs', ['text' => $item->specifications])->render() }}">{{ $item->code }} — {{ $item->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fw-semibold text-muted mb-1" for="ir_fy">Fiscal year</label>
                            <select name="fiscal_year" id="ir_fy" class="form-select form-select-sm">
                                @foreach ($years as $y)<option value="{{ $y }}" @selected($y === now()->year + 1)>{{ $y }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fw-semibold text-muted mb-1" for="ir_qty">Quantity</label>
                            <div class="input-group input-group-sm">
                                <input type="number" name="quantity" id="ir_qty" class="form-control" min="1" step="1" required>
                                <span class="input-group-text" id="ir_unit">unit</span>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fw-semibold text-muted mb-1">Sent to</label>
                            <div class="form-control form-control-sm bg-body-tertiary" id="ir_by">—</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold text-muted mb-1" for="ir_purpose">Purpose</label>
                            <textarea name="purpose" id="ir_purpose" class="form-control form-control-sm" rows="2" maxlength="2000" placeholder="e.g. Replacement of 3 units issued in 2018 for the field staff"></textarea>
                        </div>
                        <div class="col-12 d-none" id="ir_specs_box">
                            <div class="fw-semibold text-muted mb-1">Specifications (TWG)</div>
                            <div id="ir_specs"></div>
                        </div>
                    </div>
                @endif
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-primary px-3" @disabled($items->isEmpty())><i class="fas fa-paper-plane me-1"></i> Send Request</button>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    // The item shows its unit, who procures it and its specs
    $('#ir_item').on('change', function () {
        const opt = this.selectedOptions[0];
        $('#ir_unit').text(opt?.dataset.unit || 'unit');
        $('#ir_by').text(opt?.dataset.by || '—');
        $('#ir_specs').html(opt?.dataset.specs || '');
        $('#ir_specs_box').toggleClass('d-none', !(opt && opt.dataset.specs));
    });
})();
</script>
