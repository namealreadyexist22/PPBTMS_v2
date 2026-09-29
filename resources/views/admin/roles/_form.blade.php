<div class="modal fade" id="ROLE_ENTRY_MODAL" tabindex="-1">
    <div class="modal-dialog">
        <form id="form_role_entry" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">New Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="modal_error_summary" class="alert alert-danger d-none"></div>
                <label class="form-label">Role name</label>
                <input type="text" name="name" class="form-control" required placeholder="e.g. Editor">
                <div class="invalid-feedback"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" id="btn_save_role" class="btn btn-primary">Create</button>
            </div>
        </form>
    </div>
</div>
