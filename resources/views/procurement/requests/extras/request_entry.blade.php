<div class="modal fade" id="{{ $modalName }}" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold"><i class="fas fa-file-invoice text-primary me-2"></i>New Request</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="form_request_entry" autocomplete="off" novalidate>
                @csrf
                <div class="modal-body p-4">
                    <div id="request_error_summary" class="alert alert-danger d-none py-2 px-3 small mb-3">
                        <i class="fas fa-exclamation-triangle me-1"></i> <span></span>
                    </div>

                    @if ($ppmps->isEmpty())
                        <div class="alert alert-warning small mb-0">
                            <i class="fas fa-info-circle me-1"></i>
                            Your office has no approved PPMP yet. A request can only be charged to a project in an approved PPMP.
                        </div>
                    @else
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted mb-1">Kind</label>
                            <div class="btn-group w-100" role="group">
                                @foreach (\App\Enums\RequestKind::cases() as $option)
                                    <input type="radio" class="btn-check" name="kind" id="kind_{{ $option->value }}" value="{{ $option->value }}" @checked($option === $kind)>
                                    <label class="btn btn-sm btn-outline-primary" for="kind_{{ $option->value }}">{{ $option->label() }} ({{ $option->short() }})</label>
                                @endforeach
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted mb-1">Charge to (PAP of the approved PPMP)</label>
                            <select name="ppmp_pap_id" class="form-select form-select-sm" required>
                                <option value="">Choose…</option>
                                @foreach ($ppmps as $ppmp)
                                    <optgroup label="FY {{ $ppmp->fiscal_year }} · {{ $ppmp->ppmp_no }} · {{ $ppmp->office->shortName() }}">
                                        @foreach ($ppmp->paps as $pap)
                                            <option value="{{ $pap->id }}" @disabled(! $pap->items_count)>{{ $pap->label() }} ({{ $pap->items_count }} {{ \Illuminate\Support\Str::plural('project', $pap->items_count) }})</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <div class="form-text">One request charges one PAP. Its items can come from several projects under it.</div>
                        </div>

                        <div class="mb-3" id="jr_type_group" @if ($kind !== \App\Enums\RequestKind::Jr) style="display: none" @endif>
                            <label class="form-label small fw-semibold text-muted mb-1">JR Type</label>
                            <select name="jr_type" class="form-select form-select-sm">
                                <option value="">Choose…</option>
                                @foreach ($jrTypes as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-0">
                            <label class="form-label small fw-semibold text-muted mb-1">Purpose</label>
                            <textarea name="purpose" class="form-control form-control-sm" rows="2" placeholder="Can also be filled in later"></textarea>
                        </div>
                    @endif
                </div>

                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                    @if ($ppmps->isNotEmpty())
                        <button type="submit" id="btn_save_request" class="btn btn-sm btn-primary px-3"><i class="fas fa-arrow-right me-1"></i> Start</button>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    $('#form_request_entry [name="kind"]').on('change', function () {
        $('#jr_type_group').toggle(this.value === 'jr');
    });
</script>
