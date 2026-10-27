{{-- Fixed positioning: the menu floats over the table instead of being cut off by its scroll area (last rows) --}}
<div class="dropdown">
    <button class="btn btn-link text-secondary p-0 border-0 lh-1" type="button" data-bs-toggle="dropdown" aria-expanded="false"
            data-bs-popper-config='{"strategy":"fixed"}' aria-label="Actions for {{ $user->fullname }}">
        <i class="fas fa-ellipsis-h"></i>
    </button>
    <ul class="dropdown-menu dropdown-menu-end shadow border border-light custom-menu-item" style="min-width: 160px; font-size: 0.85rem;">
        <!-- Edit Details -->
        <li>
            <a class="dropdown-item py-1.5 btn-edit-user" href="javascript:void(0)" data-id="{{ $user->id }}">
                <i class="fas fa-edit text-primary me-2 fw-semibold" style="width: 16px;"></i> Edit Details
            </a>
        </li>

        <!-- Change Password -->
        <li>
            <a class="dropdown-item py-1.5 btn-change-password" href="javascript:void(0)" data-id="{{ $user->id }}">
                <i class="fas fa-key text-warning me-2 fw-semibold" style="width: 16px;"></i> Change Password
            </a>
        </li>

        <!-- Toggle Status (Activate / Deactivate) -->
        <li>
            @if($user->is_activated == 1)
                <button class="dropdown-item py-1.5 text-secondary btn-toggle-status" type="button" data-id="{{ $user->id }}" data-status="0">
                    <i class="fas fa-ban text-muted me-2 fw-semibold" style="width: 16px;"></i> Deactivate User
                </button>
            @else
                <button class="dropdown-item py-1.5 text-success btn-toggle-status" type="button" data-id="{{ $user->id }}" data-status="1">
                    <i class="fas fa-check-circle text-success me-2 fw-semibold" style="width: 16px;"></i> Activate User
                </button>
            @endif
        </li>

        <!-- Divider -->
        <li><hr class="dropdown-divider my-1 opacity-50"></li>

        <!-- Delete User -->
        <li>
            <button class="dropdown-item py-1.5 text-danger hover-danger-bg btn-delete-user" type="button" data-id="{{ $user->id }}">
                <i class="fas fa-trash me-2 fw-semibold" style="width: 16px;"></i> Delete User
            </button>
        </li>
    </ul>
</div>
