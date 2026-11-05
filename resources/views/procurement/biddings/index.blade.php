@extends('BackEnd.layouts.master')

@section('content')
@php use App\Enums\BiddingStatus; @endphp

<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm" id="biddings_card" style="border-radius: 12px; overflow: hidden;">
            <div class="card-header bg-white pt-4 pb-3 px-4" style="border-bottom: 1px solid #f1f5f9;">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <h5 class="m-0 fw-bold text-dark" style="font-size: 1.05rem;">
                        <i class="fas fa-gavel text-muted me-2"></i>Competitive Bidding
                        {{-- How it works: on hover / focus, as on Standard Items --}}
                        <a tabindex="0" role="button" class="text-muted ms-1 fs-6 fw-normal align-middle js-help" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="right" data-bs-html="true"
                           title="How a bidding goes"
                           data-bs-content="<ol class='ps-3 mb-0'><li class='mb-1'>The BAC receives the PRs / JRs first (<b>PR Receiving</b>).</li><li class='mb-1'>Create a bidding for a fund source: it is numbered right away, e.g. 2026-SIDA-0001. Put the Competitive Bidding items of received PRs / JRs into lots.</li><li class='mb-1'>Record each step: pre-procurement conference, advertisement, pre-bid conference, supplemental bulletins and the public bidding. A re-set bidding date keeps the earlier one, with the reason.</li><li>Each step shows on the PRs' status trail for the requesting offices.</li></ol>"><i class="far fa-question-circle"></i></a>
                    </h5>
                    <button id="btn_add" class="btn btn-sm text-white fw-medium shadow-sm px-3" style="background-color: #10b981; border-color: #10b981; border-radius: 6px;">
                        <i class="fas fa-plus me-1"></i>New Bidding
                    </button>
                </div>
            </div>

            <div class="card-body px-4 pb-4 pt-3">
                <form class="d-flex flex-wrap align-items-center gap-2 mb-2" method="GET" id="biddings_filter">
                    <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search bidding no., title or PR / JR no." style="width: 280px;">
                    <div class="d-flex align-items-center gap-2 ms-auto">
                        <span class="spinner-border spinner-border-sm text-primary list-spinner" role="status" aria-label="Loading"></span>
                        <select name="status" class="form-select form-select-sm" style="width: 160px;" aria-label="Status">
                            <option value="">All statuses</option>
                            @foreach (BiddingStatus::cases() as $s)
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
</div>

{{-- New bidding --}}
<div class="modal fade" id="BIDDING_NEW_MODAL" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-white py-3">
                <h5 class="modal-title fw-bold mb-0">New Bidding</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form_bidding_new" autocomplete="off">
                @csrf
                <div class="modal-body p-4">
                    <div class="alert alert-danger d-none py-2 px-3 small" id="bidding_new_error"></div>
                    <label class="form-label small fw-semibold text-muted mb-1">Fund source</label>
                    <select name="fund_source_id" class="form-select form-select-sm" required>
                        <option value="">Choose…</option>
                        @foreach ($funds as $fund)
                            <option value="{{ $fund->id }}">{{ $fund->code }} — {{ $fund->name }}</option>
                        @endforeach
                    </select>
                    <div class="form-text mb-3">The bidding is numbered for it now, e.g. {{ now()->year }}-{{ $funds->first()?->code ?? 'COB' }}-0001, and holds only its items. A bidding is cancelled, not deleted, so its number is not used again.</div>
                    <label class="form-label small fw-semibold text-muted mb-1">Title <span class="fw-normal">(optional)</span></label>
                    <input type="text" name="title" class="form-control form-control-sm" maxlength="500" placeholder="e.g. Supply and delivery of ICT equipment">
                    <div class="form-text">A name to find it by. Left blank, the bidding is named after its items. Put the Competitive Bidding items of received PRs / JRs into lots next.</div>
                    @if ($regions)
                        <label class="form-label small fw-semibold text-muted mb-1 mt-3">Region</label>
                        <select name="region" class="form-select form-select-sm">
                            @foreach ($regions as $region)
                                <option value="{{ $region->value }}" @selected($region === (auth()->user()->region ?? \App\Enums\Region::Lm))>{{ $region->label() }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
                <div class="modal-footer bg-body-tertiary border-0 py-2">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary px-3" id="btn_bidding_new">Start Bidding</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('script')
{{-- Before the table's own script, so the spinner shows for its first load too --}}
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-help').forEach((el) => bootstrap.Popover.getOrCreateInstance(el, { container: 'body' }));

    // Search, status, year and pages: the table asks the server; the URL follows. A row opens its bidding.
    serverList({ table: '#tblBiddings', card: '#biddings_card', form: '#biddings_filter', url: '{{ route("procurement.biddings.index") }}' });

    const newModal = document.getElementById('BIDDING_NEW_MODAL');
    $('#btn_add').on('click', function () {
        bootstrap.Modal.getOrCreateInstance(newModal).show();
        newModal.addEventListener('shown.bs.modal', () => setTimeout(() => $('#form_bidding_new [name=title]').trigger('focus')), { once: true });
    });
    $('#form_bidding_new').on('submit', function (e) {
        e.preventDefault();
        $('#btn_bidding_new').prop('disabled', true);
        $('#bidding_new_error').addClass('d-none');
        $.ajax({ url: '{{ route("procurement.biddings.store") }}', type: 'POST', data: $(this).serialize(), headers: { Accept: 'application/json' } })
            .done((res) => { toastr.success(res.message, 'Success'); window.location.href = res.url; })
            .fail(function (xhr) {
                $('#btn_bidding_new').prop('disabled', false);
                const res = xhr.responseJSON || {};
                $('#bidding_new_error').removeClass('d-none').text(res.errors ? Object.values(res.errors)[0][0] : (res.message || 'Something went wrong (error ' + xhr.status + ').'));
            });
    });
});
</script>
{{ $dataTable->scripts() }}
@endpush
