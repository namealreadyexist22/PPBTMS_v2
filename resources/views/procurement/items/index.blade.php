@extends('BackEnd.layouts.master')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h5 class="fw-bold mb-0"><i class="fas fa-tags text-muted me-2"></i>Standard Items</h5>
        <div class="text-muted small">Articles the agency regularly buys, with the standard unit cost and the specifications set by the TWG. A PPMP project that picks one takes its unit, price and specs; offices enter only the quantity.</div>
    </div>
    <button class="btn btn-sm btn-success btn-item-entry"><i class="fas fa-plus me-1"></i> Add Standard Item</button>
</div>

<div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
    <div class="card-header bg-white px-4 py-3" style="border-bottom: 1px solid #f1f5f9;">
        <form method="GET" class="d-flex flex-wrap gap-2">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search code or name…" style="width: 240px;">
            <select name="category" class="form-select form-select-sm" style="width: 220px;" onchange="this.form.submit()">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-search"></i></button>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle small mb-0">
            <thead class="table-light">
                <tr class="text-nowrap"><th class="ps-4">Code</th><th>Item</th><th>Category</th><th>Unit</th><th class="text-end">Standard Cost</th><th>Specifications (TWG)</th><th class="text-center">Used by</th><th class="text-center">Status</th><th class="pe-4"></th></tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr class="{{ $item->is_active ? '' : 'text-muted' }}">
                        <td class="ps-4 fw-semibold text-nowrap">{{ $item->code }}</td>
                        <td>{{ $item->name }}<div class="text-muted">{{ $item->project_type?->label() }}</div></td>
                        <td>{{ $item->category?->name ?? '—' }}</td>
                        <td>{{ $item->unit?->name }}</td>
                        <td class="text-end text-nowrap fw-semibold">{{ $item->isStandard() ? '₱' . number_format((float) $item->standard_unit_cost, 2) : '—' }}</td>
                        <td style="max-width: 320px;">
                            <div style="white-space: pre-line;">{{ \Illuminate\Support\Str::limit($item->specifications, 160) ?: '—' }}</div>
                            @if ($item->twg_reference || $item->twg_approved_at)
                                <div class="text-muted mt-1"><i class="fas fa-stamp me-1"></i>{{ $item->twg_reference }}{{ $item->twg_approved_at ? ' · ' . $item->twg_approved_at->format('M d, Y') : '' }}</div>
                            @endif
                        </td>
                        <td class="text-center">{{ $item->ppmp_items_count }}</td>
                        <td class="text-center"><span class="badge {{ $item->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $item->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="pe-4 text-end text-nowrap">
                            <button class="btn btn-sm btn-link p-0 me-2 btn-item-entry" data-id="{{ $item->id }}" title="Edit"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-link p-0 text-danger btn-item-delete" data-id="{{ $item->id }}" title="Delete"><i class="fas fa-trash-alt"></i></button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted py-5"><i class="fas fa-tags fa-2x mb-2 d-block opacity-50"></i>No standard items yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="small text-muted px-4 py-2">Changing a standard cost or specs does not change PPMP projects already saved; they take the new values when re-saved (e.g. in an amendment). Items used by projects can be deactivated but not deleted.</div>
</div>
<div id="modal-body"></div>
@endsection

@push('script')
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function () {
    $(document).on('click', '.btn-item-entry', function () {
        $.get('{{ route("procurement.items.entry") }}', $(this).data('id') ? { id: $(this).data('id') } : {}, function (html) {
            $('#modal-body').html(html);
            const el = document.getElementById('ITEM_MODAL');
            new bootstrap.Modal(el).show();
            el.addEventListener('hidden.bs.modal', () => $('#modal-body').html(''));
        });
    });

    $(document).on('submit', '#form_item', function (e) {
        e.preventDefault();
        const form = $(this);
        form.find('.is-invalid').removeClass('is-invalid');
        $.post('{{ route("procurement.items.store") }}', form.serialize())
            .done((res) => { toastr.success(res.message, 'Saved'); setTimeout(() => window.location.reload(), 500); })
            .fail(function (xhr) {
                const res = xhr.responseJSON || {};
                $.each(res.errors || {}, (k, m) => form.find('[name="' + k + '"]').addClass('is-invalid').siblings('.invalid-feedback').text(m[0]));
                if (!res.errors) toastr.error(res.message || 'Something went wrong.');
            });
    });

    $(document).on('click', '.btn-item-delete', function () {
        const id = $(this).data('id');
        Swal.fire({ title: 'Delete this item?', text: 'Only items no PPMP project uses can be deleted.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Delete' }).then((r) => {
            if (!r.isConfirmed) return;
            $.ajax({ url: '{{ route("procurement.items.destroy") }}', type: 'POST', data: { _token: '{{ csrf_token() }}', _method: 'DELETE', id: id } })
                .done((res) => { toastr.success(res.message); setTimeout(() => window.location.reload(), 500); })
                .fail((xhr) => Swal.fire({ icon: 'error', title: 'Not deleted', text: xhr.responseJSON?.message ?? 'Something went wrong.' }));
        });
    });
});
</script>
@endpush
