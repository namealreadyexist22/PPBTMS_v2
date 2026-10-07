@extends('BackEnd.layouts.master')

@section('content')
@php use App\Enums\RequestKind; use App\Enums\RequestStatus; @endphp

<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
            <div class="card-header bg-white pt-4 pb-0 px-4" style="border-bottom: 1px solid #f1f5f9;">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <h5 class="m-0 fw-bold text-dark" style="font-size: 1.05rem;">
                        <i class="fas fa-file-invoice text-muted me-2"></i>Purchase &amp; Job Requests
                    </h5>
                    <button id="btn_add" class="btn btn-sm text-white fw-medium shadow-sm px-3" style="background-color: #10b981; border-color: #10b981; border-radius: 6px;">
                        <i class="fas fa-plus me-1"></i>New {{ $kind->short() }}
                    </button>
                </div>
                <ul class="nav nav-tabs border-0">
                    @foreach (RequestKind::cases() as $tab)
                        <li class="nav-item">
                            <a class="nav-link {{ $tab === $kind ? 'active fw-semibold' : 'text-muted' }}" href="{{ route('procurement.requests.index', ['kind' => $tab->value]) }}">
                                {{ $tab->label() }} <span class="badge bg-light text-dark border ms-1">{{ $counts[$tab->value] ?? 0 }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="card-body px-4 pb-4 pt-3">
                <form class="row g-2 mb-3" method="GET">
                    <input type="hidden" name="kind" value="{{ $kind->value }}">
                    <div class="col-12 col-md-5">
                        <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="Search number or purpose">
                    </div>
                    <div class="col-8 col-md-3">
                        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">All (except superseded)</option>
                            @foreach (RequestStatus::cases() as $s)
                                <option value="{{ $s->value }}" @selected($status === $s)>{{ $s->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-4 col-md-2"><button class="btn btn-sm btn-outline-secondary w-100"><i class="fas fa-search me-1"></i>Filter</button></div>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover align-middle small mb-0">
                        <thead class="table-light">
                            <tr class="text-nowrap">
                                <th>{{ $kind === RequestKind::Jr ? 'J.R. No.' : 'PR No.' }}</th>
                                <th>Date</th>
                                <th>Office</th>
                                <th>Charge to</th>
                                <th>Purpose</th>
                                <th class="text-end">{{ $kind === RequestKind::Jr ? 'ABC' : 'Total' }}</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($requests as $row)
                                <tr role="button" onclick="window.location='{{ route('procurement.requests.show', $row) }}'">
                                    <td class="fw-semibold text-nowrap">
                                        {{ $row->request_no ?? 'Draft' }}@if ($row->revision) <span class="badge bg-info text-dark">Rev. {{ $row->revision }}</span>@endif
                                    </td>
                                    <td class="text-nowrap">{{ ($row->submitted_at ?? $row->created_at)->format('M d, Y') }}</td>
                                    <td>{{ $row->office->shortName() }}</td>
                                    <td class="text-nowrap">{{ $row->pap?->code }}</td>
                                    <td class="text-truncate" style="max-width: 320px;">{{ $row->purpose }}</td>
                                    <td class="text-end text-nowrap">{{ number_format((float) $row->total_amount, 2) }}</td>
                                    <td class="text-center"><span class="badge bg-{{ $row->status->color() }}">{{ $row->status->label() }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted py-4">No {{ strtolower($kind->label()) }}s yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $requests->links() }}</div>
            </div>
        </div>
    </div>
    <div id="modal-body"></div>
</div>
@endsection

@push('script')
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function () {
    let isModalOpen = false;
    const modalName = 'REQUEST_ENTRY_MODAL';

    $('#btn_add').on('click', function (e) {
        e.preventDefault();
        if (isModalOpen) return;
        isModalOpen = true;
        $.get('{{ route("procurement.requests.entry", ["kind" => $kind->value]) }}', function (html) {
            $('#modal-body').html(html);
            const el = document.getElementById(modalName);
            new bootstrap.Modal(el).show();
            el.addEventListener('hidden.bs.modal', function () { isModalOpen = false; $('#modal-body').html(''); });
        }).fail(function (xhr) {
            isModalOpen = false;
            toastr.error(xhr.responseJSON?.message ?? 'Could not open form.', 'Error');
        });
    });

    $(document).off('submit', '#form_request_entry').on('submit', '#form_request_entry', function (e) {
        e.preventDefault();
        const form = $(this);
        const errorSummary = $('#request_error_summary');
        form.find('.is-invalid').removeClass('is-invalid');
        errorSummary.addClass('d-none');
        $('#btn_save_request').prop('disabled', true);

        $.post('{{ route("procurement.requests.store") }}', form.serialize())
            .done(function (res) {
                toastr.success(res.message, 'Success');
                window.location.href = res.url;
            })
            .fail(function (xhr) {
                $('#btn_save_request').prop('disabled', false);
                const res = xhr.responseJSON || {};
                errorSummary.removeClass('d-none');
                if (res.errors) {
                    errorSummary.find('span').text(Object.values(res.errors)[0][0]);
                    $.each(res.errors, (key) => form.find('[name="' + key + '"]').addClass('is-invalid'));
                } else {
                    errorSummary.find('span').text(res.message ?? 'Something went wrong.');
                }
            });
    });
});
</script>
@endpush
