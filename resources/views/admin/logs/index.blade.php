@extends('BackEnd.layouts.master')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card dt-modern-card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">

            <div class="card-header bg-white pt-4 pb-3 px-4 d-flex align-items-center justify-content-between w-100"
                style="border-bottom: 1px solid #f1f5f9;">

                <h5 class="m-0 fw-bold text-dark d-flex align-items-center" style="font-size: 1.05rem;">
                    <i class="fas fa-clipboard-list text-muted me-2"></i>Activity Logs
                </h5>

                @can('logs.clear')
                    <button id="btn_clear_logs" class="btn btn-sm px-3 ms-auto text-white fw-medium shadow-sm"
                            style="background-color: #ef4444; border: 1px solid #ef4444; font-size: 0.85rem; padding: 0.45rem 1.1rem; border-radius: 6px; white-space: nowrap;">
                        <i class="fas fa-trash-alt me-1"></i>Clear Log
                    </button>
                @endcan
            </div>

            <div class="card-body px-4 pb-4 pt-3 overflow-hidden">
                <p class="text-muted small">
                    A read-only record of who did what across the system — menu, role, permission, and user
                    changes are all logged automatically as they happen.
                </p>
                <div class="table-responsive">
                    {{ $dataTable->table(['class' => 'table align-middle border-0 w-100 mb-0']) }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script')
    {{ $dataTable->scripts() }}

    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function () {
            $('#btn_clear_logs').on('click', function (e) {
                e.preventDefault();

                Swal.fire({
                    title: 'Clear the entire activity log?',
                    text: 'This permanently deletes every logged entry. This cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Yes, Clear It!',
                    cancelButtonText: 'Cancel',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: '{{ route("core.logs.clear") }}',
                            type: 'DELETE',
                            data: { _token: '{{ csrf_token() }}' },
                            success: function (response) {
                                if (response.status === 'success') {
                                    toastr.success(response.message, 'Cleared');

                                    if (window.LaravelDataTables && window.LaravelDataTables['tblLogs']) {
                                        window.LaravelDataTables['tblLogs'].ajax.reload(null, false);
                                    }
                                }
                            },
                            error: function (xhr) {
                                let msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to clear log.';
                                toastr.error(msg, 'System Error');
                            }
                        });
                    }
                });
            });
        });
    </script>
@endpush