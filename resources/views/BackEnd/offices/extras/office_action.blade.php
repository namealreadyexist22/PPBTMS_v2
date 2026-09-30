<div class="dropdown">
    <button class="btn btn-link text-secondary p-0 border-0 lh-1" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fas fa-ellipsis-h"></i>
    </button>
    <ul class="dropdown-menu dropdown-menu-end shadow border border-light custom-menu-item" style="min-width: 160px; font-size: 0.85rem;">
        <li>
            <a class="dropdown-item py-1.5 btn-edit-office" href="javascript:void(0)" data-id="{{ $office->id }}">
                <i class="fas fa-edit text-primary me-2 fw-semibold" style="width: 16px;"></i> Edit Details
            </a>
        </li>
        @if (auth()->user()->canAccessPermission('menu.offices-destroy'))
            <li><hr class="dropdown-divider"></li>
            <li>
                <button class="dropdown-item py-1.5 text-danger btn-delete-office" type="button" data-id="{{ $office->id }}">
                    <i class="fas fa-trash-alt me-2 fw-semibold" style="width: 16px;"></i> Delete
                </button>
            </li>
        @endif
    </ul>
</div>
