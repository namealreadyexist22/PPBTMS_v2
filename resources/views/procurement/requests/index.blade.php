@extends('BackEnd.layouts.master')

@section('content')
@php use App\Enums\RequestKind; use App\Enums\RequestStatus; @endphp

<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm" id="requests_card" style="border-radius: 12px; overflow: hidden;">
            <div class="card-header bg-white pt-4 pb-0 px-4" style="border-bottom: 1px solid #f1f5f9;">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <h5 class="m-0 fw-bold text-dark" style="font-size: 1.05rem;">
                        <i class="fas fa-file-invoice text-muted me-2"></i>Purchase &amp; Job Requests
                    </h5>
                    <button id="btn_add" data-kind="{{ $kind->value }}" class="btn btn-sm text-white fw-medium shadow-sm px-3" style="background-color: #10b981; border-color: #10b981; border-radius: 6px;">
                        <i class="fas fa-plus me-1"></i>New <span id="btn_add_kind">{{ $kind->short() }}</span>
                    </button>
                </div>
                <ul class="nav nav-tabs border-0" id="requests_tabs">
                    @foreach (RequestKind::cases() as $tab)
                        <li class="nav-item">
                            {{-- Its number and amount columns are named for the kind (J.R. No. / ABC for job requests) --}}
                            <a class="nav-link request-tab {{ $tab === $kind ? 'active fw-semibold' : 'text-muted' }}" data-kind="{{ $tab->value }}" data-short="{{ $tab->short() }}"
                               data-no-title="{{ $tab === RequestKind::Jr ? 'J.R. No.' : 'PR No.' }}" data-amount-title="{{ $tab === RequestKind::Jr ? 'ABC' : 'Total' }}"
                               href="{{ route('procurement.requests.index', ['kind' => $tab->value]) }}">
                                {{ $tab->label() }} <span class="badge bg-light text-dark border ms-1 js-count">{{ $counts[$tab->value] ?? 0 }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="card-body px-4 pb-4 pt-3">
                <form class="d-flex flex-wrap align-items-center gap-2 mb-2" method="GET" id="requests_filter">
                    <input type="hidden" name="kind" value="{{ $kind->value }}">
                    <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search number or purpose" style="width: 280px;">
                    <div class="d-flex align-items-center gap-2 ms-auto">
                        <span class="spinner-border spinner-border-sm text-primary list-spinner" role="status" aria-label="Loading"></span>
                        <select name="status" class="form-select form-select-sm" style="width: 200px;" aria-label="Status">
                            <option value="">All (except superseded)</option>
                            @foreach (RequestStatus::cases() as $s)
                                <option value="{{ $s->value }}" @selected($status === $s)>{{ $s->label() }}</option>
                            @endforeach
                        </select>
                        @include('procurement.partials.year_filter')
                    </div>
                </form>

                {{-- Server-side DataTable: one page at a time; search, filters and paging run in the database --}}
                <div class="position-relative">
                    @include('procurement.partials.list_loading')
                    {{ $dataTable->table(['class' => 'table table-hover align-middle small w-100 mb-0']) }}
                </div>
            </div>
        </div>
    </div>
    <div id="modal-body"></div>
</div>
@endsection

@push('script')
{{-- Before the table's own script, so the spinner shows for its first load too --}}
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function () {
    let isModalOpen = false;
    const modalName = 'REQUEST_ENTRY_MODAL';

    // Search, status, year and pages: the table asks the server; the URL follows (refresh / Back keep it)
    const list = serverList({ table: '#tblRequests', card: '#requests_card', form: '#requests_filter', url: '{{ route("procurement.requests.index") }}' });

    // The tab counts follow the year (each answer carries them)
    $('#tblRequests').on('xhr.dt', function (e, settings, json) {
        if (!json || !json.counts) return;
        $('.request-tab').each(function () { $(this).find('.js-count').text(json.counts[this.dataset.kind] || 0); });
    });

    // PR / JR tabs: the same table, for the other kind (its number and amount columns renamed)
    $(document).on('click', '.request-tab', function (e) {
        if (e.ctrlKey || e.metaKey || e.shiftKey) return;
        e.preventDefault();
        if ($(this).hasClass('active')) return;
        $('.request-tab').removeClass('active fw-semibold').addClass('text-muted');
        $(this).addClass('active fw-semibold').removeClass('text-muted');
        $('#requests_filter [name=kind]').val($(this).data('kind'));
        $('#btn_add').data('kind', $(this).data('kind'));
        $('#btn_add_kind').text($(this).data('short'));
        $(list.api().column(0).header()).find('.dt-column-title').text($(this).data('no-title'));
        $(list.api().column(4).header()).find('.dt-column-title').text($(this).data('amount-title'));
        list.apply();
    });

    // Status trail in a modal
    $(document).on('click', '.js-trail', function () {
        if (isModalOpen) return;
        isModalOpen = true;
        $.get($(this).data('url'), function (html) {
            $('#modal-body').html(html);
            const el = document.getElementById('REQUEST_TRAIL_MODAL');
            new bootstrap.Modal(el).show();
            el.addEventListener('hidden.bs.modal', function () { isModalOpen = false; $('#modal-body').html(''); });
        }).fail(function () { isModalOpen = false; toastr.error('Could not load the status trail.', 'Error'); });
    });

    $('#btn_add').on('click', function (e) {
        e.preventDefault();
        if (isModalOpen) return;
        isModalOpen = true;
        $.get('{{ route("procurement.requests.entry") }}', { kind: $(this).data('kind') }, function (html) {
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
{{ $dataTable->scripts() }}
@endpush
