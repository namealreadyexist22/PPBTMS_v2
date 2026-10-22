@extends('BackEnd.layouts.master')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h5 class="fw-bold mb-0"><i class="fas fa-store text-muted me-2"></i>Suppliers</h5>
        <div class="text-muted small">Suppliers/dealers picked on an Abstract of Quotations, so their details are not typed again. A supplier first entered on an AOQ is added here automatically.</div>
    </div>
    <button class="btn btn-sm btn-success btn-supplier-entry"><i class="fas fa-plus me-1"></i> Add Supplier</button>
</div>

<div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
    <div class="card-header bg-white px-4 py-3" style="border-bottom: 1px solid #f1f5f9;">
        <form method="GET" class="d-flex flex-wrap gap-2" id="suppliers_filter">
            <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="Search name, address or TIN…" style="width: 260px;">
            <select name="status" class="form-select form-select-sm" style="width: 160px;">
                <option value="">All statuses</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>
        </form>
    </div>
    {{-- Redrawn in place on search / filter and after adding, editing or deleting (no full reload) --}}
    <div id="suppliers_view">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle small mb-0">
            <thead class="table-light">
                <tr class="text-nowrap"><th class="ps-4">Supplier</th><th>Address</th><th>TIN</th><th>Contact</th><th>PhilGEPS No.</th><th class="text-center">On AOQs</th><th class="text-center">Status</th><th class="pe-4"></th></tr>
            </thead>
            <tbody>
                @forelse ($suppliers as $supplier)
                    <tr class="{{ $supplier->is_active ? '' : 'text-muted' }}">
                        <td class="ps-4 fw-semibold">{{ $supplier->name }}</td>
                        <td>{{ $supplier->address ?: '—' }}</td>
                        <td class="text-nowrap">{{ $supplier->tin ?: '—' }}</td>
                        <td>{{ $supplier->contact ?: '—' }}</td>
                        <td class="text-nowrap">{{ $supplier->philgeps_no ?: '—' }}</td>
                        <td class="text-center">{{ $supplier->aoq_suppliers_count }}</td>
                        <td class="text-center"><span class="badge {{ $supplier->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $supplier->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="pe-4 text-end text-nowrap">
                            <button class="btn btn-sm btn-link p-0 me-2 btn-supplier-entry" data-id="{{ $supplier->id }}" title="Edit"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-link p-0 text-danger btn-supplier-delete" data-id="{{ $supplier->id }}" title="Delete"><i class="fas fa-trash-alt"></i></button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-5"><i class="fas fa-store fa-2x mb-2 d-block opacity-50"></i>{{ $search !== '' || request()->filled('status') ? 'No suppliers match the search.' : 'No suppliers yet.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($suppliers->hasPages())<div class="px-4 py-2 border-top">{{ $suppliers->links() }}</div>@endif
    <div class="small text-muted px-4 py-2">Changing a supplier's details does not change abstracts already made. Suppliers on an abstract can be deactivated but not deleted.</div>
    </div>{{-- /#suppliers_view --}}
</div>
<div id="modal-body"></div>
@endsection

@push('script')
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function () {
    // Redraw the table from the server; the search box keeps its focus while typing
    let pending = null;
    function refreshView(url = window.location.href) {
        pending?.abort();
        $('#suppliers_view').css('opacity', .6);
        pending = $.get(url).done(function (html) {
            const fresh = $('<div>').append($.parseHTML(html)).find('#suppliers_view');
            fresh.length ? $('#suppliers_view').replaceWith(fresh) : window.location.reload();
        }).fail((xhr) => { if (xhr.statusText !== 'abort') window.location.reload(); })
          .always(() => $('#suppliers_view').css('opacity', ''));
        return pending;
    }

    function applyFilter() {
        const params = new URLSearchParams();
        $('#suppliers_filter').serializeArray().forEach((f) => { if (f.value.trim()) params.set(f.name, f.value.trim()); });
        const url = '{{ route("procurement.suppliers.index") }}' + (params.toString() ? '?' + params : '');
        history.replaceState(null, '', url);
        refreshView(url);
    }
    let typing = null;
    $('#suppliers_filter').on('submit', function (e) { e.preventDefault(); clearTimeout(typing); applyFilter(); });
    $('#suppliers_filter [name=q]').on('input', function () { clearTimeout(typing); typing = setTimeout(applyFilter, 300); });
    $('#suppliers_filter [name=status]').on('change', applyFilter);

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
@endpush
