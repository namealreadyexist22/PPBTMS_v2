@extends('BackEnd.layouts.master')

@section('content')
<div class="card dt-modern-card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
    <div class="card-header bg-white pt-4 pb-0 px-4" style="border-bottom: 1px solid #f1f5f9;">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h5 class="m-0 fw-bold text-dark" style="font-size: 1.05rem;"><i class="fas fa-list-alt text-muted me-2"></i>Procurement Lookups</h5>
            <button id="btn_add" class="btn btn-sm px-3 text-white fw-medium shadow-sm" style="background-color: #10b981; border-radius: 6px;">
                <i class="fas fa-plus me-1"></i>Add {{ \Illuminate\Support\Str::singular($label) }}
            </button>
        </div>
        <ul class="nav nav-tabs border-0">
            @foreach ($types as $key => $typeLabel)
                <li class="nav-item">
                    <a class="nav-link {{ $key === $type ? 'active fw-semibold' : 'text-muted' }}" href="{{ route('core.lookups.index', ['type' => $key]) }}">{{ $typeLabel }}</a>
                </li>
            @endforeach
        </ul>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm table-hover align-middle small mb-0">
            <thead class="table-light"><tr><th class="ps-4">Code</th><th>Name</th>@if ($type === 'fund-sources')<th>APP</th>@endif<th class="text-center">Status</th><th class="text-center">Used by</th><th class="pe-4"></th></tr></thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr class="{{ $row->is_active ? '' : 'text-muted' }}">
                        <td class="ps-4 fw-semibold">{{ $row->code }}</td>
                        <td>{{ $row->name }}</td>
                        @if ($type === 'fund-sources')
                            <td><span class="badge {{ $row->fund_group === \App\Enums\FundGroup::Sida ? 'bg-warning text-dark' : 'bg-light text-dark border' }}">{{ $row->fund_group->label() }}</span></td>
                        @endif
                        <td class="text-center"><span class="badge {{ $row->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $row->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="text-center">{{ $row->used_count }}</td>
                        <td class="pe-4 text-end text-nowrap">
                            <button class="btn btn-sm btn-link p-0 me-2 btn-edit" data-id="{{ $row->id }}" data-code="{{ $row->code }}" data-name="{{ $row->name }}" data-active="{{ $row->is_active ? 1 : 0 }}" @if ($type === 'fund-sources') data-fund="{{ $row->fund_group->value }}" @endif title="Edit"><i class="fas fa-edit"></i></button>
                            @if ($row->used_count === 0)
                                <button class="btn btn-sm btn-link p-0 text-danger btn-delete" data-id="{{ $row->id }}" data-name="{{ $row->name }}" title="Delete"><i class="fas fa-trash-alt"></i></button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Nothing yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="small text-muted px-4 py-2">Inactive entries are hidden from new PPMP and APP entries but stay on records that already use them.</div>
    </div>
</div>

<div class="modal fade" id="LOOKUP_MODAL" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow" id="form_lookup" novalidate>
            <input type="hidden" name="type" value="{{ $type }}">
            <input type="hidden" name="id">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold" id="lookup_title"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 small">
                <div class="mb-3">
                    <label class="form-label fw-semibold text-muted mb-1">Code</label>
                    <input type="text" name="code" class="form-control form-control-sm" placeholder="e.g. GAA2017-CA" required>
                    <div class="invalid-feedback"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold text-muted mb-1">Name (as printed)</label>
                    <input type="text" name="name" class="form-control form-control-sm" placeholder="e.g. GAA 2017 - Continuing Appropriation" required>
                    <div class="invalid-feedback"></div>
                </div>
                @if ($type === 'fund-sources')
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted mb-1">APP</label>
                        <select name="fund_group" class="form-select form-select-sm">
                            @foreach (\App\Enums\FundGroup::cases() as $fund)
                                <option value="{{ $fund->value }}">{{ $fund->label() }} APP</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback"></div>
                        <div class="form-text">PPMP projects with this fund source go to the Regular or the SIDA APP.</div>
                    </div>
                @endif
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="lookup_active">
                    <label class="form-check-label" for="lookup_active">Active</label>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-primary px-3"><i class="fas fa-save me-1"></i> Save</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('script')
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function () {
    const csrf = '{{ csrf_token() }}';
    const form = $('#form_lookup');
    const modal = () => bootstrap.Modal.getOrCreateInstance(document.getElementById('LOOKUP_MODAL'));

    function open(data) {
        form.find('.is-invalid').removeClass('is-invalid');
        form.find('[name=id]').val(data.id || '');
        form.find('[name=code]').val(data.code || '');
        form.find('[name=name]').val(data.name || '');
        form.find('[name=is_active]').prop('checked', data.active === undefined ? true : data.active == 1);
        form.find('[name=fund_group]').val(data.fund || 'regular');
        $('#lookup_title').text(data.id ? 'Edit {{ \Illuminate\Support\Str::singular($label) }}' : 'Add {{ \Illuminate\Support\Str::singular($label) }}');
        modal().show();
    }

    $('#btn_add').on('click', () => open({}));
    $(document).on('click', '.btn-edit', function () { open($(this).data()); });

    form.on('submit', function (e) {
        e.preventDefault();
        form.find('.is-invalid').removeClass('is-invalid');
        $.post('{{ route("core.lookups.store") }}', form.serialize() + '&_token=' + csrf + (form.find('[name=is_active]').is(':checked') ? '' : '&is_active=0'))
            .done(function (res) { toastr.success(res.message, 'Saved'); setTimeout(() => window.location.reload(), 500); })
            .fail(function (xhr) {
                const res = xhr.responseJSON || {};
                $.each(res.errors || {}, (key, messages) => form.find('[name="' + key + '"]').addClass('is-invalid').siblings('.invalid-feedback').text(messages[0]));
                if (!res.errors) toastr.error(res.message || 'Something went wrong.', 'Error');
            });
    });

    $(document).on('click', '.btn-delete', function () {
        const id = $(this).data('id');
        Swal.fire({ title: 'Delete "' + $(this).data('name') + '"?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Delete', reverseButtons: true })
            .then((r) => {
                if (!r.isConfirmed) return;
                $.ajax({ url: '{{ route("core.lookups.destroy") }}', type: 'POST', data: { _token: csrf, _method: 'DELETE', type: '{{ $type }}', id: id } })
                    .done((res) => { toastr.success(res.message, 'Deleted'); setTimeout(() => window.location.reload(), 500); })
                    .fail((xhr) => toastr.error(xhr.responseJSON?.message ?? 'Could not delete.', 'Error'));
            });
    });
});
</script>
@endpush
