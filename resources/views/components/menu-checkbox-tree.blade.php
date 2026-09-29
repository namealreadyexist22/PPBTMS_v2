{{-- Usage: <x-menu-checkbox-tree :items="$menus" :checked="$rolePermissionNames"
        :permissionsByMenu="$permissionsByMenu" /> --}}
{{-- $checked: Collection of permission_name strings this role currently has --}}
{{-- $permissionsByMenu: [menu_id => Collection of Permission] --}}
@props(['items', 'checked', 'permissionsByMenu' => null])

@php $permissionsByMenu ??= collect(); @endphp

<ul class="list-unstyled ms-3">
    @foreach ($items as $item)
        @php $relatedPermissions = $permissionsByMenu->get($item->id, collect()); @endphp
        <li class="mb-1">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="menu_ids[]"
                    id="menu-{{ $item->id }}" value="{{ $item->id }}"
                    {{ $checked->contains($item->permission_name) ? 'checked' : '' }}>
                <label class="form-check-label" for="menu-{{ $item->id }}">
                    @if ($item->icon) <i class="{{ $item->icon }} me-1"></i> @endif
                    {{ $item->name }}
                </label>
            </div>

            @if ($relatedPermissions->isNotEmpty())
                <ul class="list-unstyled ms-4">
                    @foreach ($relatedPermissions as $permission)
                        <li>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permission_names[]"
                                    id="perm-{{ $permission->id }}" value="{{ $permission->name }}"
                                    {{ $checked->contains($permission->name) ? 'checked' : '' }}>
                                <label class="form-check-label small text-muted" for="perm-{{ $permission->id }}">
                                    {{ $permission->name }}
                                </label>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($item->children->isNotEmpty())
                <x-menu-checkbox-tree :items="$item->children" :checked="$checked" :permissionsByMenu="$permissionsByMenu" />
            @endif
        </li>
    @endforeach
</ul>