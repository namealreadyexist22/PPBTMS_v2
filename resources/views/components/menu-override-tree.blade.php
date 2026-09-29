{{-- Usage: <x-menu-override-tree :items="$menus" :overrides="$overrides"
        :permissionsByMenu="$permissionsByMenu" :permissionOverrides="$permissionOverrides" /> --}}
{{-- $overrides: [menu_id => 'allow'|'deny'], $permissionOverrides: [permission_id => 'allow'|'deny'] --}}
{{-- $permissionsByMenu: [menu_id => Collection of Permission] --}}
@props(['items', 'overrides', 'permissionsByMenu' => null, 'permissionOverrides' => null])

@php
    $permissionsByMenu ??= collect();
    $permissionOverrides ??= collect();

    // These are the "base access" permissions — allowing the menu implies
    // allowing entry to the page, so checking the menu's Allow auto-checks
    // this one too. Anything else nested under the menu (like menus.destroy)
    // stays fully independent.
    $baseAccessNames = ['manage menus', 'manage roles', 'manage users', 'manage permissions', 'manage access'];
@endphp

<ul class="list-unstyled ms-3">
    @foreach ($items as $item)
        @php
            $current = $overrides->get($item->id, 'inherit');
            $relatedPermissions = $permissionsByMenu->get($item->id, collect());
            $basePermission = $relatedPermissions->first(fn ($p) => in_array($p->name, $baseAccessNames));
        @endphp
        <li class="mb-2">
            <div class="d-flex align-items-center gap-3">
                <span style="min-width: 220px;">
                    @if ($item->icon) <i class="{{ $item->icon }} me-1"></i> @endif
                    {{ $item->name }}
                </span>

                @foreach (['inherit' => 'Inherit from role', 'allow' => 'Allow', 'deny' => 'Deny'] as $value => $label)
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio"
                            name="overrides[{{ $item->id }}]" id="ov-{{ $item->id }}-{{ $value }}"
                            value="{{ $value }}" {{ $current === $value ? 'checked' : '' }}
                            @if ($value === 'allow' && $basePermission) data-cascade-to="pov-{{ $basePermission->id }}-allow" @endif>
                        <label class="form-check-label small" for="ov-{{ $item->id }}-{{ $value }}">{{ $label }}</label>
                    </div>
                @endforeach
            </div>

            @if ($relatedPermissions->isNotEmpty())
                <ul class="list-unstyled ms-4">
                    @foreach ($relatedPermissions as $permission)
                        @php $pCurrent = $permissionOverrides->get($permission->id, 'inherit'); @endphp
                        <li class="mb-1">
                            <div class="d-flex align-items-center gap-3">
                                <span class="text-muted" style="min-width: 200px;">{{ $permission->name }}</span>
                                @foreach (['inherit' => 'Inherit from role', 'allow' => 'Allow', 'deny' => 'Deny'] as $value => $label)
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio"
                                            name="permission_overrides[{{ $permission->id }}]" id="pov-{{ $permission->id }}-{{ $value }}"
                                            value="{{ $value }}" {{ $pCurrent === $value ? 'checked' : '' }}>
                                        <label class="form-check-label small" for="pov-{{ $permission->id }}-{{ $value }}">{{ $label }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($item->children->isNotEmpty())
                <x-menu-override-tree :items="$item->children" :overrides="$overrides"
                    :permissionsByMenu="$permissionsByMenu" :permissionOverrides="$permissionOverrides" />
            @endif
        </li>
    @endforeach
</ul>