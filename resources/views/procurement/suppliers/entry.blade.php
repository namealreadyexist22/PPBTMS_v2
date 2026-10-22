<div class="modal fade" id="SUPPLIER_MODAL" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form class="modal-content border-0 shadow" id="form_supplier" novalidate autocomplete="off">
            @csrf
            @if ($supplier)<input type="hidden" name="id" value="{{ $supplier->id }}">@endif
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold"><i class="fas fa-store me-2"></i>{{ $supplier ? 'Edit' : 'Add' }} Supplier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 small">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold text-muted mb-1">Business name</label>
                        <input type="text" name="name" class="form-control form-control-sm" value="{{ $supplier->name ?? '' }}" maxlength="255" placeholder="e.g. GBR-GEN General Merchandise">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold text-muted mb-1">Address</label>
                        <input type="text" name="address" class="form-control form-control-sm" value="{{ $supplier->address ?? '' }}" maxlength="500" placeholder="Printed under the name on the AOQ">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-muted mb-1">TIN</label>
                        <input type="text" name="tin" class="form-control form-control-sm" value="{{ $supplier->tin ?? '' }}" maxlength="50">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-muted mb-1">Contact</label>
                        <input type="text" name="contact" class="form-control form-control-sm" value="{{ $supplier->contact ?? '' }}" maxlength="255" placeholder="Phone or email">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-muted mb-1">PhilGEPS registration no.</label>
                        <input type="text" name="philgeps_no" class="form-control form-control-sm" value="{{ $supplier->philgeps_no ?? '' }}" maxlength="50">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="supplier_active" @checked($supplier->is_active ?? true)>
                            <label class="form-check-label" for="supplier_active">Active (can be picked on an AOQ)</label>
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
