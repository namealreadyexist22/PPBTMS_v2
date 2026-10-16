{{-- Usage: <x-permission-chip name="perm_access" :id="$permission->id" label="View All"
        :value="$override" :source="$defaultSources->get($permission->name)" />
     A page row also passes :page="true" and :linkSource (its sidebar link's source). --}}
{{-- One row on the User Access screen: Default follows the user's roles (and
     permissions given to them directly), Allow / Deny is a personal override.
     $value: null (no override) | 'allow' | 'deny'. $source: where the default
     access comes from ('role', 'given directly', 'Super Admin'), null = none;
     for a page it is the access to its pages, $linkSource the sidebar link's. --}}
@props(['name', 'id', 'label', 'value' => null, 'source' => null, 'page' => false, 'linkSource' => null])

@php
    $base = "access-{$name}-{$id}";
    $default = $source !== null;
    $effective = $value ? $value === 'allow' : $default;
    $defaultText = match (true) {
        ! $page => $default ? "allowed ({$source})" : 'no access',
        $default && $linkSource !== null => "allowed ({$source})",
        $default => "pages allowed ({$source}), no sidebar link",
        $linkSource !== null => "sidebar link only ({$linkSource}), pages blocked",
        default => 'no access',
    };
@endphp

<div class="access-row d-flex align-items-center gap-2 px-3 py-2 border-bottom {{ $value ? 'is-overridden' : '' }}"
     data-default="{{ $default ? 'allow' : 'deny' }}">
    <span class="access-dot {{ $effective ? 'is-allowed' : '' }}" title="{{ $effective ? 'Has access' : 'No access' }}"></span>
    <div class="flex-grow-1 min-w-0">
        <div class="small fw-semibold text-truncate" title="{{ $label }}">{{ $label }}</div>
        <div class="text-muted access-default">
            Default: {{ $defaultText }}
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
