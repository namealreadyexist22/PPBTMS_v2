{{--
    Searchable signatory name (Select2): pick a person to fill in their designation, or type any name.
    Expects: $name, $value, $designationField (input name filled on pick), $people (each ['name', 'designation', 'office']),
             $placeholder (shown when blank, e.g. the default signatory).
--}}
@php $people = collect($people)->filter(fn ($p) => filled($p['name']))->unique('name')->values(); @endphp
<select name="{{ $name }}" class="form-select form-select-sm js-signatory" data-designation="{{ $designationField }}" data-placeholder="{{ $placeholder ?? 'Search or type a name' }}">
    <option value=""></option>
    @if (filled($value) && ! $people->contains('name', $value))
        <option value="{{ $value }}" selected>{{ $value }}</option>
    @endif
    @foreach ($people as $person)
        <option value="{{ $person['name'] }}" data-designation="{{ $person['designation'] }}" data-office="{{ $person['office'] ?? '' }}" @selected($person['name'] === $value)>{{ $person['name'] }}</option>
    @endforeach
</select>
@once
    @push('script')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // Signatory pickers: search people (name + designation), or type a name not in the list
        window.initSignatorySelects = function (root) {
            $(root || document).find('select.js-signatory').each(function () {
                if ($(this).hasClass('select2-hidden-accessible')) return;
                const select = $(this);
                select.select2({
                    theme: 'bootstrap-5', width: '100%', tags: true, allowClear: true,
                    placeholder: select.data('placeholder'),
                    dropdownParent: select.closest('.modal').length ? select.closest('.modal') : $(document.body),
                    selectionCssClass: 'select2--small', dropdownCssClass: 'select2--small',
                    matcher: function (params, data) {
                        const term = (params.term || '').trim().toLowerCase();
                        if (!term) return data;
                        const el = data.element;
                        const extra = el ? (el.dataset.designation || '') + ' ' + (el.dataset.office || '') : '';
                        return (data.text + ' ' + extra).toLowerCase().includes(term) ? data : null;
                    },
                    // A typed name not in the list comes last, so Enter picks the matching person first
                    createTag: function (params) {
                        const term = (params.term || '').trim();
                        return term ? { id: term, text: term, newTag: true } : null;
                    },
                    insertTag: function (data, tag) { data.push(tag); },
                    templateResult: function (data) {
                        if (data.newTag) return $('<span class="text-muted">').text('Use "' + data.text + '"');
                        const el = data.element;
                        const sub = el ? [el.dataset.designation, el.dataset.office].filter(Boolean).join(' · ') : '';
                        if (!sub) return data.text;
                        return $('<div>').append($('<div>').text(data.text), $('<div class="small text-muted">').text(sub));
                    },
                }).on('select2:select', function (e) {
                    const designation = e.params.data.element ? e.params.data.element.dataset.designation : null;
                    if (designation) $('[name="' + select.data('designation') + '"]').val(designation);
                }).on('select2:clear', function () {
                    $('[name="' + select.data('designation') + '"]').val('');
                });
            });
        };
        window.initSignatorySelects();
    });
    </script>
    @endpush
@endonce
