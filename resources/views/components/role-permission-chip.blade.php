{{-- Usage: <x-role-permission-chip name="menu_ids" :id="$menu->id" :value="$menu->id" label="View / Access" :checked="$checked" /> --}}
@props(['name', 'id', 'value', 'label', 'checked' => false])

<div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
    <label class="small mb-0" for="{{ $name }}-{{ $id }}">{{ $label }}</label>
    <div class="form-check form-switch mb-0">
        <input class="form-check-input" type="checkbox" role="switch" name="{{ $name }}[]"
            id="{{ $name }}-{{ $id }}" value="{{ $value }}" {{ $checked ? 'checked' : '' }}>
    </div>
</div>