@extends('BackEnd.layouts.master')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
            <div class="card-header bg-white pt-4 pb-3 px-4 d-flex flex-wrap align-items-center justify-content-between gap-2" style="border-bottom: 1px solid #f1f5f9;">
                <div>
                    <h5 class="m-0 fw-bold text-dark d-flex align-items-center" style="font-size: 1.05rem;">
                        <i class="fas fa-sitemap text-muted me-2"></i>Organization
                    </h5>
                    <div class="small text-muted">Departments, their divisions and sections. Users belong to a unit; a department receives the budget and every unit under it shares it.</div>
                </div>
                <div class="d-flex gap-2">
                    <input type="search" id="org_search" class="form-control form-control-sm" placeholder="Search units…" style="width: 200px;">
                    <button class="btn btn-sm btn-success text-nowrap btn-add-unit" data-type="department"><i class="fas fa-plus me-1"></i> Add Department</button>
                </div>
            </div>
            <div class="card-body p-0">
                @forelse ($roots as $root)
                    @include('BackEnd.offices.extras.office_node', ['office' => $root, 'depth' => 0])
                @empty
                    <div class="text-center text-muted py-5"><i class="fas fa-sitemap fa-2x mb-2 d-block opacity-50"></i>No units yet. Start with Add Department.</div>
                @endforelse
            </div>
            <div class="small text-muted px-4 py-2 border-top">
                <span class="badge bg-primary-subtle text-primary border me-1">Approves PPMPs</span> the head approves the PPMPs of the units below it (Division PPMP).
                Office No. is used in PPMP numbers (e.g. 05012-2027-V1).
            </div>
        </div>
    </div>

    <div id="modal-body"></div>
</div>
@endsection

@push('script')

    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function () {
        let isModalOpen = false;
        const modalName = 'OFFICE_ENTRY_MODAL';

        function reloadTable() {
            window.location.reload();
        }

        // Open Add / Edit modal
        function loadOfficeModal(officeId = null, extra = {}) {
            if (isModalOpen) return;
            isModalOpen = true;

            $.ajax({
                url: '{{ route("core.offices.entry") }}',
                type: 'GET',
                data: officeId ? { id: officeId } : extra,
                success: function (html) {
                    $('#modal-body').html(html);
                    const modalElement = document.getElementById(modalName);
                    new bootstrap.Modal(modalElement).show();

                    modalElement.addEventListener('hidden.bs.modal', function () {
                        isModalOpen = false;
                        $('#modal-body').html('');
                    });
                },
                error: function (xhr) {
                    isModalOpen = false;
                    toastr.error(xhr.responseJSON?.message ?? 'Could not open form.', 'System Error');
                }
            });
        }

        // Add a department, or a division / section under a unit
        $(document).on('click', '.btn-add-unit', function (e) {
            e.preventDefault();
            loadOfficeModal(null, { type: $(this).data('type'), parent_id: $(this).data('parent') || '' });
        });

        // Search: show matching units and the branches they sit in
        $('#org_search').on('input', function () {
            const q = this.value.trim().toLowerCase();
            $('.org-node').each(function () {
                const own = $(this).children('.org-row').text().toLowerCase();
                $(this).toggle(!q || own.includes(q) || $(this).find('.org-row').text().toLowerCase().includes(q));
            });
        });

        $(document).on('click', '.btn-edit-office', function (e) {
            e.preventDefault();
            loadOfficeModal($(this).data('id'));
        });

        // Save
        $(document).off('submit', '#form_office_entry').on('submit', '#form_office_entry', function (e) {
            e.preventDefault();

            const form = $(this);
            const saveBtn = $('#btn_save_office');
            const originalBtnHtml = saveBtn.html();
            const errorSummary = $('#modal_error_summary');

            form.find('.is-invalid').removeClass('is-invalid');
            form.find('.invalid-feedback').text('');
            errorSummary.addClass('d-none');
            saveBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Saving...');

            $.ajax({
                url: '{{ route("core.offices.store") }}',
                type: 'POST',
                data: form.serialize(),
                success: function (response) {
                    toastr.success(response.message, 'Success');
                    bootstrap.Modal.getInstance(document.getElementById(modalName)).hide();
                    reloadTable();
                },
                error: function (xhr) {
                    saveBtn.prop('disabled', false).html(originalBtnHtml);
                    const res = xhr.responseJSON || {};

                    if (xhr.status === 422 && res.errors) {
                        errorSummary.removeClass('d-none');
                        $.each(res.errors, function (key, messages) {
                            const input = form.find('[name="' + key + '"]');
                            input.addClass('is-invalid');
                            input.siblings('.invalid-feedback').text(messages[0]);
                        });
                    } else {
                        toastr.error(res.message ?? 'Something went wrong.', 'System Error');
                    }
                }
            });
        });

        // Delete
        $(document).on('click', '.btn-delete-office', function (e) {
            e.preventDefault();
            const officeId = $(this).data('id');

            Swal.fire({
                title: 'Delete this unit?',
                text: 'Only units with nothing under them, no users and no PPMPs can be deleted.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, Delete!',
                cancelButtonText: 'Cancel',
                reverseButtons: true
            }).then((result) => {
                if (!result.isConfirmed) return;

                $.ajax({
                    url: '{{ route("core.offices.destroy") }}',
                    type: 'POST',
                    data: { _token: '{{ csrf_token() }}', _method: 'DELETE', id: officeId },
                    success: function (response) {
                        toastr.success(response.message, 'Deleted');
                        reloadTable();
                    },
                    error: function (xhr) {
                        toastr.error(xhr.responseJSON?.message ?? 'Could not delete office.', 'Error');
                    }
                });
            });
        });
    });
    </script>
@endpush
