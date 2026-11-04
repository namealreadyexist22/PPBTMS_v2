@extends('BackEnd.layouts.master')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h5 class="fw-bold mb-0">
        <i class="fas fa-store text-muted me-2"></i>Suppliers
        {{-- What the supplier list is for: on hover / focus, as on Standard Items --}}
        <a tabindex="0" role="button" class="text-muted ms-1 fs-6 align-middle js-help" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="right"
           title="What the supplier list is for"
           data-bs-content="Suppliers/dealers picked on an Abstract of Quotations, so their details are not typed again. A supplier first entered on an AOQ is added here automatically."><i class="far fa-question-circle"></i></a>
    </h5>
    <button class="btn btn-sm btn-success btn-supplier-entry"><i class="fas fa-plus me-1"></i> Add Supplier</button>
</div>

<div class="card border-0 shadow-sm" id="suppliers_card" style="border-radius: 12px; overflow: hidden;">
    <div class="card-header bg-white px-4 py-3" style="border-bottom: 1px solid #f1f5f9;">
        <div class="small text-muted mb-2"><i class="fas fa-info-circle me-1"></i>Changing a supplier's details does not change abstracts already made. Suppliers on an abstract can be deactivated but not deleted.</div>
        <form method="GET" class="d-flex flex-wrap align-items-center gap-2" id="suppliers_filter">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search name, address or TIN…" style="width: 260px;">
            <div class="d-flex align-items-center gap-2 ms-auto">
                <span class="spinner-border spinner-border-sm text-primary list-spinner" role="status" aria-label="Loading"></span>
                <select name="status" class="form-select form-select-sm" style="width: 160px;" aria-label="Status">
                    <option value="">All statuses</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                </select>
            </div>
        </form>
    </div>
    {{-- Server-side DataTable: one page of suppliers at a time; search, sort and paging run in the database --}}
    <div class="position-relative px-4 pb-3 pt-2">
        @include('procurement.partials.list_loading')
        {{ $dataTable->table(['class' => 'table table-sm table-hover align-middle small w-100 mb-0']) }}
    </div>
</div>
<div id="modal-body"></div>
@endsection

@push('script')
{{-- Before the table's own script, so the spinner shows for its first load too --}}
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-help').forEach((el) => bootstrap.Popover.getOrCreateInstance(el, { container: 'body' }));

    // Search, status, sorting and pages: the table asks the server; the URL follows
    const list = serverList({ table: '#tblSuppliers', card: '#suppliers_card', form: '#suppliers_filter', url: '{{ route("procurement.suppliers.index") }}' });
    const refreshView = () => list.reload();   // after saving / deleting: stay on the page

    $(document).on('click', '.btn-supplier-entry', function () {
        $.get('{{ route("procurement.suppliers.entry") }}', $(this).data('id') ? { id: $(this).data('id') } : {}, function (html) {
            $('#modal-body').html(html);
            const el = document.getElementById('SUPPLIER_MODAL');
            new bootstrap.Modal(el).show();
            el.addEventListener('shown.bs.modal', () => $('#form_supplier [name=name]').trigger('focus'));
            el.addEventListener('hidden.bs.modal', () => $('#modal-body').html(''));
        });
    });

    $(document).on('submit', '#form_supplier', function (e) {
        e.preventDefault();
        const form = $(this);
        form.find('.is-invalid').removeClass('is-invalid');
        $.post('{{ route("procurement.suppliers.store") }}', form.serialize())
            .done((res) => {
                toastr.success(res.message, 'Saved');
                bootstrap.Modal.getInstance(document.getElementById('SUPPLIER_MODAL'))?.hide();
                refreshView();
            })
            .fail(function (xhr) {
                const res = xhr.responseJSON || {};
                $.each(res.errors || {}, (k, m) => form.find('[name="' + k + '"]').addClass('is-invalid').siblings('.invalid-feedback').text(m[0]));
                if (!res.errors) toastr.error(res.message || 'Something went wrong.');
            });
    });

    $(document).on('click', '.btn-supplier-delete', function () {
        const id = $(this).data('id');
        Swal.fire({ title: 'Delete this supplier?', text: 'Only suppliers not on any abstract can be deleted.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Delete' }).then((r) => {
            if (!r.isConfirmed) return;
            $.ajax({ url: '{{ route("procurement.suppliers.destroy") }}', type: 'POST', data: { _token: '{{ csrf_token() }}', _method: 'DELETE', id: id } })
                .done((res) => { toastr.success(res.message); refreshView(); })
                .fail((xhr) => Swal.fire({ icon: 'error', title: 'Not deleted', text: xhr.responseJSON?.message ?? 'Something went wrong.' }));
        });
    });
});
</script>
{{ $dataTable->scripts() }}
@endpush
