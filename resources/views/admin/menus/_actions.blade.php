@if ($menu->canHaveSubmenus())
    <a href="{{ route('core.menus.submenus', $menu) }}" class="btn btn-sm btn-outline-primary">
        Submenus
    </a>
@endif
<button type="button" class="btn btn-sm btn-outline-primary btn-edit-menu" data-id="{{ $menu->id }}">
    Edit
</button>
<button type="button" class="btn btn-sm btn-outline-danger btn-delete-menu" data-id="{{ $menu->id }}">
    Delete
</button>