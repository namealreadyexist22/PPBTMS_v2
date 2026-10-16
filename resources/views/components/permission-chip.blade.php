{{-- Usage: <x-permission-chip name="menu_access" :id="$menu->id" label="PPMP"
        :value="$override" :source="$defaultSources->get($menu->permission_name)" /> --}}
{{-- One row on the User Access screen: Default follows the user's roles (and
     permissions given to them directly), Allow / Deny is a personal override.
     $value: null (no override) | 'allow' | 'deny'. $source: where the default
     access comes from ('role', 'given directly', 'Super Admin'), null = none. --}}
@props(['name', 'id', 'label', 'value' => null, 'source' => null])

@php
    $base = "access-{$name}-{$id}";
    $default = $source !== null;
    $effective = $value ? $value === 'allow' : $default;
@endphp

<div class="access-row d-flex align-items-center gap-2 px-3 py-2 border-bottom {{ $value ? 'is-overridden' : '' }}"
     data-default="{{ $default ? 'allow' : 'deny' }}">
    <span class="access-dot {{ $effective ? 'is-allowed' : '' }}" title="{{ $effective ? 'Has access' : 'No access' }}"></span>
    <div class="flex-grow-1 min-w-0">
        <div class="small fw-semibold text-truncate" title="{{ $label }}">{{ $label }}</div>
        <div class="text-muted access-default">
            Default: {{ $default ? "allowed ({$source})" : 'no access' }}
        </div>
    </div>
    <div class="btn-group btn-group-sm flex-shrink-0" role="group" aria-label="Access to {{ $label }}">
        <input type="radio" class="btn-check" name="{{ $name }}[{{ $id }}]" id="{{ $base }}-inherit" value="inherit" autocomplete="off" @checked(! $value)>
        <label class="btn btn-outline-secondary" for="{{ $base }}-inherit" title="Follow the role">Default</label>
        <input type="radio" class="btn-check" name="{{ $name }}[{{ $id }}]" id="{{ $base }}-allow" value="allow" autocomplete="off" @checked($value === 'allow')>
        <label class="btn btn-outline-success" for="{{ $base }}-allow" title="Allow for this user">Allow</label>
        <input type="radio" class="btn-check" name="{{ $name }}[{{ $id }}]" id="{{ $base }}-deny" value="deny" autocomplete="off" @checked($value === 'deny')>
        <label class="btn btn-outline-danger" for="{{ $base }}-deny" title="Deny for this user">Deny</label>
    </div>
</div>
