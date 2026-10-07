<div class="modal fade" id="ITEM_MODAL" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form class="modal-content border-0 shadow" id="form_item" novalidate autocomplete="off">
            @csrf
            @if ($item)<input type="hidden" name="id" value="{{ $item->id }}">@endif
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold"><i class="fas fa-tag me-2"></i>{{ $item ? 'Edit' : 'Add' }} Standard Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 small">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold text-muted mb-1">Code</label>
                        @if ($item)
                            <input type="text" name="code" class="form-control form-control-sm" value="{{ $item->code }}">
                            <div class="invalid-feedback"></div>
                        @else
                            <input type="text" class="form-control form-control-sm bg-light" id="item_code_preview" value="{{ $nextCodes[''] ?? '' }}" readonly>
                            <div class="form-text">Given on save, from the category.</div>
                        @endif
                    </div>
                    <div class="col-md-9">
                        <label class="form-label fw-semibold text-muted mb-1">Item name</label>
                        <input type="text" name="name" class="form-control form-control-sm" value="{{ $item->name ?? '' }}" placeholder='e.g. LED Smart TV, 55"'>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-muted mb-1">Category</label>
                        <select name="item_category_id" class="form-select form-select-sm" @unless ($item) onchange="document.getElementById('item_code_preview').value = ({{ json_encode($nextCodes) }})[this.value] || ''" @endunless>
                            <option value="">—</option>
                            @foreach ($categories as $category)<option value="{{ $category->id }}" @selected(($item->item_category_id ?? null) == $category->id)>{{ $category->name }}</option>@endforeach
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-muted mb-1">Unit</label>
                        <select name="unit_id" class="form-select form-select-sm">
                            <option value="">Choose…</option>
                            @foreach ($units as $unit)<option value="{{ $unit->id }}" @selected(($item->unit_id ?? null) == $unit->id)>{{ $unit->name }}</option>@endforeach
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-muted mb-1">Type of project</label>
                        <select name="project_type" class="form-select form-select-sm">
                            @foreach ($types as $type)<option value="{{ $type->value }}" @selected(($item?->project_type ?? \App\Enums\ProjectType::Goods) === $type)>{{ $type->label() }}</option>@endforeach
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-muted mb-1">Standard unit cost (PHP)</label>
                        <input type="text" inputmode="decimal" name="standard_unit_cost" class="form-control form-control-sm text-end" value="{{ $item?->standard_unit_cost !== null ? number_format((float) $item->standard_unit_cost, 2) : '' }}" placeholder="e.g. 50,000.00">
                        <div class="invalid-feedback"></div>
                        <div class="form-text">Leave blank for a catalog entry without a fixed price.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-muted mb-1">TWG reference</label>
                        <input type="text" name="twg_reference" class="form-control form-control-sm" value="{{ $item->twg_reference ?? '' }}" placeholder="e.g. TWG-ICT Res. No. 2026-03">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-muted mb-1">TWG approval date</label>
                        <input type="date" name="twg_approved_at" class="form-control form-control-sm" value="{{ $item?->twg_approved_at?->format('Y-m-d') }}">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold text-muted mb-1">Specifications (set by the TWG)</label>
                        <textarea name="specifications" class="form-control form-control-sm" rows="5" placeholder="e.g. 55-inch 4K UHD LED, Smart TV, 3 HDMI, wall mount bracket, 1-year warranty">{{ $item->specifications ?? '' }}</textarea>
                        <div class="invalid-feedback"></div>
                        <div class="form-text">Printed under the project on the PPMP. Offices cannot change it.</div>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="item_active" @checked($item->is_active ?? true)>
                            <label class="form-check-label" for="item_active">Active (offices can pick it)</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-primary px-3"><i class="fas fa-save me-1"></i> Save</button>
            </div>
        </form>
    </div>
</div>
