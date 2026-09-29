@extends('BackEnd.layouts.master')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card dt-modern-card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">

            <div class="card-header bg-white pt-4 pb-3 px-4 d-flex align-items-center justify-content-between w-100"
                style="border-bottom: 1px solid #f1f5f9;">

                <h5 class="m-0 fw-bold text-dark d-flex align-items-center" style="font-size: 1.05rem;">
                    <i class="fas fa-users text-muted me-2"></i>User List
                </h5>

                <button id="btn_add" class="btn btn-sm px-3 ms-auto text-white fw-medium shadow-sm"
                        style="background-color: #10b981; border: 1px solid #10b981; font-size: 0.85rem; padding: 0.45rem 1.1rem; border-radius: 6px; white-space: nowrap;"
                        onmouseover="this.style.backgroundColor='#059669'"
                        onmouseout="this.style.backgroundColor='#10b981'">
                    <i class="fas fa-plus me-1"></i>Add New User
                </button>
            </div>

            <div class="card-body px-4 pb-4 pt-3 overflow-hidden">
                <div class="table-responsive">
                    {{ $dataTable->table(['class' => 'table align-middle border-0 w-100 mb-0']) }}
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Changed this from a raw class to an explicit ID container for your AJAX script target -->
    <div id="modal-body"></div>
</div>
@endsection

@push('script')
    {{ $dataTable->scripts() }}

    <!-- 3. Wrapped your click handler script cleanly inside a document ready function -->
    <script type="text/javascript">
      document.addEventListener('DOMContentLoaded', function() {
            let isModalOpen = false;
            const modalName = 'USER_ENTRY_MODAL';

            // ==========================================
            // MODULE 1: MODAL MARKUP LAYOUT LOADER
            // ==========================================
            function loadUserFormModal(userId = null) {
                if (isModalOpen) return;
                isModalOpen = true;

                let payload = { modalName: modalName };
                if (userId) {
                    payload.id = userId;
                }

                $.ajax({
                    url: '{{ route("core.users.entry") }}',
                    type: 'GET',
                    data: payload,
                    success: function (data) {
                        $('#modal-body').html(data);

                        const modalElement = document.getElementById(modalName);
                        if (modalElement) {
                            const modalInstance = new bootstrap.Modal(modalElement);
                            modalInstance.show();

                            // Standard backdrop cleanup sequence
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
                        let errorMsg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Could not open form layout view.';
                        toastr.error(errorMsg, 'System Error');
                    }
                });
            }

            // Trigger: Creation Event
            $('#btn_add').on('click', function(e) {
                e.preventDefault();
                loadUserFormModal();
            });

            // Trigger: Edition Event (DataTables delegation handler)
            $(document).on('click', '.btn-edit-user', function(e) {
                e.preventDefault();
                const userId = $(this).data('id');
                loadUserFormModal(userId);
            });

            // Dynamic local live-preview handler routine
            $(document).on('change', '#avatar_file_input', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        $('#avatar_preview').attr('src', e.target.result).removeClass('d-none');
                        $('#avatar_placeholder').addClass('d-none');
                    }
                    reader.readAsDataURL(file);
                }
            });

            // Update form submission routine
            $(document).off('submit', '#form_user_entry').on('submit', '#form_user_entry', function(e) {
                e.preventDefault();

                const form = $(this);
                const saveBtn = $('#btn_save_user');
                const originalBtnHtml = saveBtn.html();
                const errorSummary = $('#modal_error_summary');

                // Clear old errors
                form.find('.is-invalid').removeClass('is-invalid');
                form.find('.invalid-feedback').text('');
                errorSummary.addClass('d-none');

                saveBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1.5"></i> Saving...');

                // CRITICAL: Construct FormData to support stream binary payloads
                let formData = new FormData(form[0]);

                $.ajax({
                    url: '{{ route("core.users.store") }}',
                    type: 'POST',
                    data: formData,
                    processData: false, // Tell jQuery not to process data parameters
                    contentType: false, // Tell jQuery not to set content-type header
                    success: function(response) {
                        saveBtn.prop('disabled', false).html(originalBtnHtml);

                        if (response.status === 'success') {
                            toastr.success(response.message, 'Success');

                            const modalEl = document.getElementById('USER_ENTRY_MODAL');
                            if (modalEl) {
                                const instance = bootstrap.Modal.getInstance(modalEl);
                                if (instance) instance.hide();
                            }

                            if (window.LaravelDataTables && window.LaravelDataTables["tblUsers"]) {
                                window.LaravelDataTables["tblUsers"].ajax.reload(null, false);
                            }
                        }
                    },
                    error: function(xhr) {
                        saveBtn.prop('disabled', false).html(originalBtnHtml);

                        if (xhr.status === 422) {
                            let errors = xhr.responseJSON.errors;
                            toastr.error('The submitted configuration values failed validation.', 'Validation Error');
                            errorSummary.removeClass('d-none');

                            $.each(errors, function(key, messages) {
                                // Check if error belongs to the file upload input element channel
                                if(key === 'avatar') {
                                    $('#avatar_file_input').addClass('is-invalid');
                                    $('#avatar_error_node').text(messages[0]).show();
                                    return;
                                }

                                let inputElement = form.find('[name="' + key + '"]');
                                if (inputElement.length > 0) {
                                    inputElement.addClass('is-invalid');
                                    let feedbackContainer = inputElement.siblings('.invalid-feedback');
                                    if (feedbackContainer.length === 0) {
                                        feedbackContainer = inputElement.closest('.input-group').find('.invalid-feedback');
                                    }
                                    feedbackContainer.text(messages[0]);
                                }
                            });
                        } else {
                            let fallbackMsg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Persistence processing exceptions thrown.';
                            toastr.error(fallbackMsg, 'System Error');
                        }
                    }
                });
            });


           // 1. Open the Password Modal Dynamically
            $(document).on('click', '.btn-change-password', function(e) {
                e.preventDefault();
                const userId = $(this).data('id');

                $.ajax({
                    url: '{{ route("core.users.cpass") }}',
                    type: 'GET',
                    data: { id: userId },
                    success: function (data) {

                        $('#modal-body').html(data);

                        // FIXED: Removed the '#' character from getElementById
                        const modalElement = document.getElementById('CHANGE_PASSWORD_MODAL');

                        if (modalElement) {
                            const modalInstance = new bootstrap.Modal(modalElement);
                            modalInstance.show();

                            // FIXED: Reset listener binds cleanly to avoid duplicate trigger stacks
                            $(modalElement).off('hidden.bs.modal').on('hidden.bs.modal', function () {
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
                        let errorMsg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Could not open form layout view.';
                        toastr.error(errorMsg, 'System Error');
                    }
                });
            });

            // 2. Intercept and Handle Password Form Form Submission Subroutines
            $(document).off('submit', '#form_change_password').on('submit', '#form_change_password', function(e) {
                e.preventDefault();

                const form = $(this);
                const saveBtn = $('#btn_update_password');
                const originalBtnHtml = saveBtn.html();
                const errorSummary = $('#password_error_summary');

                form.find('.is-invalid').removeClass('is-invalid');
                form.find('.invalid-feedback').text('');
                errorSummary.addClass('d-none');

                saveBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1.5"></i> Modifying...');

                $.ajax({
                    url: '{{ route("core.users.upass") }}',
                    type: 'POST',
                    data: form.serialize(),
                    success: function(response) {
                        saveBtn.prop('disabled', false).html(originalBtnHtml);

                        if (response.status === 'success') {
                            toastr.success(response.message, 'Success');

                            const modalEl = document.getElementById('CHANGE_PASSWORD_MODAL');
                            if (modalEl) {
                                const instance = bootstrap.Modal.getInstance(modalEl);
                                if (instance) instance.hide();
                            }
                        }
                    },
                    error: function(xhr) {
                        saveBtn.prop('disabled', false).html(originalBtnHtml);

                        if (xhr.status === 422) {
                            let errors = xhr.responseJSON.errors;

                            toastr.error('The security configurations failed verification checks.', 'Validation Error');
                            errorSummary.removeClass('d-none');

                            $.each(errors, function(key, messages) {
                                let inputElement = form.find('[name="' + key + '"]');
                                if (inputElement.length > 0) {
                                    inputElement.addClass('is-invalid');
                                    inputElement.siblings('.invalid-feedback').text(messages[0]);
                                }
                            });
                        } else {
                            let fallbackMsg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Credentials modifier execution anomaly encountered.';
                            toastr.error(fallbackMsg, 'System Error');
                        }
                    }
                });
            });

            // Drop this directly inside your standard JavaScript document script stack/ready wrapper
            $(document).on('click', '.btn-toggle-status', function(e) {
                e.preventDefault();

                // Pull parameters straight from the clicked element's data attributes
                const userId = $(this).data('id');
                const targetStatus = $(this).data('status');

                const isActivating = (targetStatus === 1);
                const actionText = isActivating ? 'Activate' : 'Deactivate';
                const confirmColor = isActivating ? '#10b981' : '#ef4444'; // Clean Emerald vs Red accent

                Swal.fire({
                    title: 'Are you sure?',
                    text: `You are about to ${actionText.toLowerCase()} this system user account context.`,
                    icon: isActivating ? 'success' : 'warning',
                    showCancelButton: true,
                    confirmButtonColor: confirmColor,
                    cancelButtonColor: '#64748b', // Slate gray secondary alignment
                    confirmButtonText: `Yes, ${actionText}!`,
                    cancelButtonText: 'Cancel',
                    reverseButtons: true
                    // customClass removed completely to prevent layout ballooning/broken text styling
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: '{{ route("core.users.ustat") }}',
                            type: 'POST',
                            data: {
                                _token: '{{ csrf_token() }}',
                                id: userId,
                                is_activated: targetStatus
                            },
                            success: function(response) {
                                if (response.status === 'success') {
                                    toastr.success(response.message, 'Status Updated');

                                    if (window.LaravelDataTables && window.LaravelDataTables["tblUsers"]) {
                                        window.LaravelDataTables["tblUsers"].ajax.reload(null, false);
                                    }
                                }
                            },
                            error: function(xhr) {
                                let fallbackMsg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to alter user lifecycle state parameters.';
                                toastr.error(fallbackMsg, 'System Error');
                            }
                        });
                    }
                });
            });

            $(document).on('click', '.btn-delete-user', function(e) {
                e.preventDefault();

                const userId = $(this).data('id');
                const confirmColor = '#ef4444'; // Modern crisp red accent color

                Swal.fire({
                    title: 'Delete this account?',
                    text: 'This action is permanent and will completely remove this system user record profile context.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: confirmColor,
                    cancelButtonColor: '#64748b', // Modern slate gray
                    confirmButtonText: 'Yes, Delete Record!',
                    cancelButtonText: 'Cancel',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: '{{ route("core.users.destroy") }}', // Update to your exact route mapping name
                            type: 'POST', // Sent as POST but spoofed to DELETE via Laravel _method metadata
                            data: {
                                _token: '{{ csrf_token() }}',
                                _method: 'DELETE',
                                id: userId
                            },
                            success: function(response) {
                                if (response.status === 'success') {
                                    toastr.success(response.message, 'Record Purged');

                                    // Dynamically reload table context safely without losing pagination position
                                    if (window.LaravelDataTables && window.LaravelDataTables["tblUsers"]) {
                                        window.LaravelDataTables["tblUsers"].ajax.reload(null, false);
                                    }
                                }
                            },
                            error: function(xhr) {
                                let fallbackMsg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to execute structural data deletion parameters.';
                                toastr.error(fallbackMsg, 'System Error');
                            }
                        });
                    }
                });
            });
        });
    </script>
@endpush
