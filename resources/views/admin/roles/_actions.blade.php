<a href="{{ route('core.roles.permissions', $role) }}" class="btn btn-sm btn-outline-primary">
    Menus
</a>
@if ($role->name !== 'Super Admin')
    <button type="button" class="btn btn-sm btn-outline-danger btn-delete-role" data-id="{{ $role->id }}">
        Delete
    </button>
@endif
