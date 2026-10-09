{{--
    "Prepared by / Submitted by" dialog for a PPMP or Division PPMP.
    Expects: $modalId, $action (POST url), $record (has prepared_by_name, ... columns),
             $defaults (['prepared' => ['name','position'], 'submitted' => [...]]), $people (each ['name','designation']),
             $note (what the names are for), $reloadOnSave (bool)
--}}
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form class="modal-content border-0 shadow js-signatories-form" autocomplete="off" data-action="{{ $action }}" data-reload="{{ ($reloadOnSave ?? false) ? 1 : 0 }}">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold"><i class="fas fa-signature me-2"></i>Signatories</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 small">
                <p class="text-muted">{{ $note }} Leave a name blank to use the default shown in grey.</p>
                <div class="row g-3">
                    @foreach (['prepared' => ['Prepared by', 'Head of the end-user office or its authorized representative'], 'submitted' => ['Submitted by', 'Head of the end-user unit / approving head']] as $who => [$label, $hint])
                        <div class="col-12"><div class="fw-semibold">{{ $label }} <span class="text-muted fw-normal">— {{ $hint }}</span></div></div>
                        <div class="col-md-6">
                            <label class="form-label text-muted mb-1">Printed name</label>
                            @include('procurement.partials.signatory_select', [
                                'name' => "{$who}_by_name", 'value' => $record->{$who . '_by_name'}, 'designationField' => "{$who}_by_designation",
                                'people' => $people, 'placeholder' => ($defaults[$who]['name'] ?? null) ? $defaults[$who]['name'] . ' (default)' : 'Search or type a name',
                            ])
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted mb-1">Designation</label>
                            <input type="text" name="{{ $who }}_by_designation" class="form-control form-control-sm" maxlength="255"
                                   value="{{ $record->{$who . '_by_designation'} }}" placeholder="{{ $defaults[$who]['position'] ?? 'Designation' }}">
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-primary px-3"><i class="fas fa-save me-1"></i> Save</button>
            </div>
        </form>
    </div>
</div>
@once
    @push('script')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        $(document).on('submit', '.js-signatories-form', function (e) {
            e.preventDefault();
            const form = $(this);
            $.post(form.data('action'), form.serialize() + '&_token={{ csrf_token() }}')
                .done(function (res) {
                    toastr.success(res.message, 'Saved');
                    bootstrap.Modal.getInstance(form.closest('.modal')[0]).hide();
                    if (form.data('reload')) setTimeout(() => window.location.reload(), 500);
                })
                .fail((xhr) => toastr.error(xhr.responseJSON?.message || Object.values(xhr.responseJSON?.errors || {})[0]?.[0] || 'Could not save.', 'Error'));
        });
    });
    </script>
    @endpush
@endonce
