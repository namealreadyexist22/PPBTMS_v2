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

@endpush