@extends('BackEnd.layouts.master')

@section('content')

<div class="row">
    <div class="col-12">
        <div class="card dt-modern-card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">

            <div class="card-header bg-white pt-4 pb-3 px-4 d-flex align-items-center justify-content-between w-100"
                style="border-bottom: 1px solid #f1f5f9;">

                <h5 class="m-0 fw-bold text-dark d-flex align-items-center" style="font-size: 1.05rem;">
                    <i class="fas fa-clipboard-list text-muted me-2"></i>PPMP List
                </h5>

                <button id="btn_add" class="btn btn-sm px-3 ms-auto text-white fw-medium shadow-sm"
                        style="background-color: #10b981; border: 1px solid #10b981; font-size: 0.85rem; padding: 0.45rem 1.1rem; border-radius: 6px; white-space: nowrap;"
                        onmouseover="this.style.backgroundColor='#059669'"
                        onmouseout="this.style.backgroundColor='#10b981'">
                    <i class="fas fa-plus me-1"></i>Create New PPMP
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

    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function () {
        let isModalOpen = false;
        const modalName = 'PPMP_ENTRY_MODAL';

        // Open the Create PPMP modal
        $('#btn_add').on('click', function (e) {
            e.preventDefault();
            if (isModalOpen) return;
            isModalOpen = true;

            $.ajax({
                url: '{{ route("procurement.ppmp.entry") }}',
                type: 'GET',
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
        });

        // Save
        $(document).off('submit', '#form_ppmp_entry').on('submit', '#form_ppmp_entry', function (e) {
            e.preventDefault();

            const form = $(this);
            const saveBtn = $('#btn_save_ppmp');
            const originalBtnHtml = saveBtn.html();
            const errorSummary = $('#modal_error_summary');

            form.find('.is-invalid').removeClass('is-invalid');
            form.find('.invalid-feedback').text('');
            errorSummary.addClass('d-none');
            saveBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Saving...');

            $.ajax({
                url: '{{ route("procurement.ppmp.store") }}',
                type: 'POST',
                data: form.serialize(),
                success: function (response) {
                    toastr.success(response.message, 'Success');
                    bootstrap.Modal.getInstance(document.getElementById(modalName)).hide();
                    window.LaravelDataTables['tblPpmp'].ajax.reload(null, false);
                },
                error: function (xhr) {
                    saveBtn.prop('disabled', false).html(originalBtnHtml);
                    const res = xhr.responseJSON || {};

                    if (xhr.status === 422 && res.errors) {
                        // Field validation errors -> highlight each field
                        errorSummary.removeClass('d-none').find('span').text('Please correct the highlighted errors below.');
                        $.each(res.errors, function (key, messages) {
                            const input = form.find('[name="' + key + '"]');
                            input.addClass('is-invalid');
                            input.siblings('.invalid-feedback').text(messages[0]);
                        });
                    } else if (xhr.status === 422 && res.message) {
                        // Business rule, e.g. "already has a PPMP for FY 2027"
                        errorSummary.removeClass('d-none').find('span').text(res.message);
                    } else {
                        toastr.error(res.message ?? 'Something went wrong.', 'System Error');
                    }
                }
            });
        });
    });
    </script>

@endpush