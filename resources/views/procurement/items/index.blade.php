@extends('BackEnd.layouts.master')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h5 class="fw-bold mb-0">
        <i class="fas fa-tags text-muted me-2"></i>Standard Items
        {{-- What standard items are: on hover / focus, as on Item Requests --}}
        <a tabindex="0" role="button" class="text-muted ms-1 fs-6 align-middle js-help" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="right"
           title="What standard items are"
           data-bs-content="Articles the agency regularly buys, with the standard unit cost and the specifications set by the TWG. A PPMP project that picks one takes its unit, price and specs; offices enter only the quantity."><i class="far fa-question-circle"></i></a>
    </h5>
    <button class="btn btn-sm btn-success btn-item-entry"><i class="fas fa-plus me-1"></i> Add Standard Item</button>
</div>

<div class="card border-0 shadow-sm" id="items_card" style="border-radius: 12px; overflow: hidden;">
    <div class="card-header bg-white px-4 py-3" style="border-bottom: 1px solid #f1f5f9;">
        <div class="small text-muted mb-2"><i class="fas fa-info-circle me-1"></i>Changing a standard cost or specs does not change PPMP projects already saved; they take the new values when re-saved (e.g. in an amendment). Items used by projects can be deactivated but not deleted.</div>
        <form method="GET" class="d-flex flex-wrap align-items-center gap-2" id="items_filter">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search code or name…" style="width: 240px;">
            <div class="d-flex align-items-center gap-2 ms-auto">
                <span class="spinner-border spinner-border-sm text-primary list-spinner" role="status" aria-label="Loading"></span>
                <select name="category" class="form-select form-select-sm" style="width: 220px;" aria-label="Category">
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>
    {{-- Server-side DataTable: one page of items at a time; search, sort and paging run in the database --}}
    <div class="position-relative px-4 pb-3 pt-2">
        @include('procurement.partials.list_loading')
        {{ $dataTable->table(['class' => 'table table-sm table-hover align-middle small w-100 mb-0']) }}
    </div>
</div>

{{-- Full specifications of one item --}}
<div class="modal fade" id="SPECS_MODAL" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-3">
                <div>
                    <h5 class="modal-title fw-bold"><i class="fas fa-list-alt text-muted me-2"></i>Specifications (TWG)</h5>
                    <div class="small text-muted" id="specs_title"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 small">
                <div id="specs_body"></div>
                <div class="text-muted mt-3 d-none" id="specs_twg"><i class="fas fa-stamp me-1"></i><span></span></div>
            </div>
            <div class="modal-footer bg-light py-2"><button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>
<div id="modal-body"></div>
@endsection

@push('script')
{{-- Before the table's own script, so the spinner shows for its first load too --}}
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-help').forEach((el) => bootstrap.Popover.getOrCreateInstance(el, { container: 'body' }));

    // Search, category, sorting and pages: the table asks the server; the URL follows
    const list = serverList({ table: '#tblItems', card: '#items_card', form: '#items_filter', url: '{{ route("procurement.items.index") }}' });
    const refreshView = () => list.reload();   // after saving / deleting: stay on the page

    $(document).on('click', '.btn-view-specs', function () {
        const data = $(this).data();
        $('#specs_title').text(data.title);
        $('#specs_body').html(data.html);   // the specs table (rendered by the server, escaped)
        $('#specs_twg').toggleClass('d-none', !data.twg).find('span').text(data.twg || '');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('SPECS_MODAL')).show();
    });

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
            .done((res) => {
                toastr.success(res.message, 'Saved');
                bootstrap.Modal.getInstance(document.getElementById('ITEM_MODAL'))?.hide();
                refreshView();
            })
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
                .done((res) => { toastr.success(res.message); refreshView(); })
                .fail((xhr) => Swal.fire({ icon: 'error', title: 'Not deleted', text: xhr.responseJSON?.message ?? 'Something went wrong.' }));
        });
    });
});
</script>
{{ $dataTable->scripts() }}
@endpush
