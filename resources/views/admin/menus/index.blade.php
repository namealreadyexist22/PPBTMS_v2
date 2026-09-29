@extends('BackEnd.layouts.master')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card dt-modern-card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">

            <div class="card-header bg-white pt-4 pb-3 px-4 d-flex align-items-center justify-content-between w-100"
                style="border-bottom: 1px solid #f1f5f9;">

                <h5 class="m-0 fw-bold text-dark d-flex align-items-center" style="font-size: 1.05rem;">
                    <i class="fas fa-list text-muted me-2"></i>Menu Management
                </h5>

                <button id="btn_add_menu" class="btn btn-sm px-3 ms-auto text-white fw-medium shadow-sm"
                        style="background-color: #10b981; border: 1px solid #10b981; font-size: 0.85rem; padding: 0.45rem 1.1rem; border-radius: 6px; white-space: nowrap;">
                    <i class="fas fa-plus me-1"></i>New Menu Item
                </button>
            </div>

            <div class="card-body px-4 pb-4 pt-3 overflow-hidden">
                <div class="table-responsive">
                    {{ $dataTable->table(['class' => 'table align-middle border-0 w-100 mb-0']) }}
                </div>
            </div>
        </div>
    </div>

    <div id="modal-body"></div>
</div>
@endsection

@push('script')
    {{ $dataTable->scripts() }}

    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function () {
            let isModalOpen = false;

            function loadMenuFormModal(menuId = null) {
                if (isModalOpen) return;
                isModalOpen = true;

                $.ajax({
                    url: '{{ route("core.menus.entry") }}',
                    type: 'GET',
                    data: menuId ? { id: menuId } : {},
                    success: function (data) {
                        $('#modal-body').html(data);

                        const modalElement = document.getElementById('MENU_ENTRY_MODAL');
                        if (modalElement) {
                            const modalInstance = new bootstrap.Modal(modalElement);
                            modalInstance.show();

                            modalElement.addEventListener('hidden.bs.modal', function () {
                                isModalOpen = false;
                                $('#modal-body').html('');

                                if (document.querySelectorAll('.modal.show').length === 0) {
                                    document.body.classList.remove('modal-open');
                                    const backdrop = document.querySelector('.modal-backdrop');
                                    if (backdrop) backdrop.remove();
                                }
                            });
                        }
                    },
                    error: function (xhr) {
                        isModalOpen = false;
                        let msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Could not open form.';
                        toastr.error(msg, 'System Error');
                    }
                });
            }

            $('#btn_add_menu').on('click', function (e) {
                e.preventDefault();
                loadMenuFormModal();
            });

            $(document).on('click', '.btn-edit-menu', function (e) {
                e.preventDefault();
                loadMenuFormModal($(this).data('id'));
            });

            $(document).off('submit', '#form_menu_entry').on('submit', '#form_menu_entry', function (e) {
                e.preventDefault();

                const form = $(this);
                const saveBtn = $('#btn_save_menu');
                const originalBtnHtml = saveBtn.html();
                const errorSummary = $('#modal_error_summary');
                const actionUrl = form.find('[name="_action_url"]').val();

                form.find('.is-invalid').removeClass('is-invalid');
                form.find('.invalid-feedback').text('');
                errorSummary.addClass('d-none');

                saveBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Saving...');

                $.ajax({
                    url: actionUrl,
                    type: 'POST', // Laravel reads @method('PUT') spoof field for edits
                    data: form.serialize(),
                    success: function (response) {
                        saveBtn.prop('disabled', false).html(originalBtnHtml);

                        if (response.status === 'success') {
                            toastr.success(response.message, 'Success');

                            const modalEl = document.getElementById('MENU_ENTRY_MODAL');
                            if (modalEl) {
                                const instance = bootstrap.Modal.getInstance(modalEl);
                                if (instance) instance.hide();
                            }

                            if (window.LaravelDataTables && window.LaravelDataTables['tblMenus']) {
                                window.LaravelDataTables['tblMenus'].ajax.reload(null, false);
                            }
                        }
                    },
                    error: function (xhr) {
                        saveBtn.prop('disabled', false).html(originalBtnHtml);

                        if (xhr.status === 422) {
                            let errors = xhr.responseJSON.errors;
                            toastr.error('Please check the fields below.', 'Validation Error');
                            errorSummary.removeClass('d-none');

                            $.each(errors, function (key, messages) {
                                let inputElement = form.find('[name="' + key + '"]');
                                if (inputElement.length > 0) {
                                    inputElement.addClass('is-invalid');
                                    inputElement.siblings('.invalid-feedback').text(messages[0]);
                                }
                            });
                        } else {
                            let msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Something went wrong.';
                            toastr.error(msg, 'System Error');
                        }
                    }
                });
            });

            $(document).on('click', '.btn-delete-menu', function (e) {
                e.preventDefault();
                const menuId = $(this).data('id');

                Swal.fire({
                    title: 'Delete this menu item?',
                    text: 'Any child items under it will become top-level items.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Yes, Delete!',
                    cancelButtonText: 'Cancel',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: `/core/menus/${menuId}`,
                            type: 'DELETE',
                            data: { _token: '{{ csrf_token() }}' },
                            success: function (response) {
                                if (response.status === 'success') {
                                    toastr.success(response.message, 'Deleted');

                                    if (window.LaravelDataTables && window.LaravelDataTables['tblMenus']) {
                                        window.LaravelDataTables['tblMenus'].ajax.reload(null, false);
                                    }
                                }
                            },
                            error: function (xhr) {
                                let msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to delete.';
                                toastr.error(msg, 'System Error');
                            }
                        });
                    }
                });
            });
        });
    </script>
@endpush
