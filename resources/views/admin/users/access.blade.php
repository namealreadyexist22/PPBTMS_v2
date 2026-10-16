@extends('BackEnd.layouts.master')

@section('content')
<div class="row">
    <div class="col-12">
        <h1 class="h4 mb-3">User Access</h1>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif
    </div>

    <div class="col-md-3">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-2">
                <form method="GET" class="mb-2">
                    <input type="text" name="q" class="form-control form-control-sm"
                        placeholder="Search name or email" value="{{ request('q') }}">
                </form>

                <div class="list-group list-group-flush">
                    @forelse ($users as $user)
                        <a href="{{ route('core.access.index', ['user' => $user->id, 'q' => request('q')]) }}"
                            class="list-group-item list-group-item-action py-2 {{ $selectedUser?->id === $user->id ? 'active' : '' }}">
                            <div class="small fw-semibold">{{ $user->fullname }}</div>
                            <div class="small {{ $selectedUser?->id === $user->id ? '' : 'text-muted' }}"
                                style="font-size: 0.75rem;">{{ $user->roles->pluck('name')->join(', ') ?: '— no role —' }}</div>
                        </a>
                    @empty
                        <div class="list-group-item text-muted small">No users found.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div>{{ $users->links() }}</div>
    </div>

    <div class="col-md-9">
        @if ($selectedUser)
            <style>
                .access-row { transition: background-color .15s; }
                .access-row.is-overridden { background: var(--bs-warning-bg-subtle); }
                .access-default { font-size: .7rem; }
                .access-dot { width: .55rem; height: .55rem; border-radius: 50%; flex-shrink: 0; background: var(--bs-secondary-bg); border: 1px solid var(--bs-border-color); }
                .access-dot.is-allowed { background: var(--bs-success); border-color: var(--bs-success); }
                .access-row .btn-group .btn { font-size: .7rem; padding: .15rem .45rem; }
            </style>
            <form method="POST" action="{{ route('core.access.update', $selectedUser) }}" id="form_access">
                @csrf
                @method('PUT')

                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <div>
                        <h5 class="mb-0">{{ $selectedUser->fullname }}</h5>
                        <span class="text-muted small">
                            Roles: {{ $selectedUser->roles->pluck('name')->join(', ') ?: '— none —' }}
                            · <span id="override_count">{{ $overrideCount === 0 ? 'no overrides' : $overrideCount . ' ' . Str::plural('override', $overrideCount) }}</span>
                        </span>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light border btn-sm" id="btn_reset_access" title="Set every row back to Default (save to apply)">
                            <i class="fas fa-undo me-1"></i> All to Default
                        </button>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i> Save Access</button>
                    </div>
                </div>

                <p class="text-muted small">
                    <strong>Default</strong> follows the user's roles (and any permission given to them directly).
                    <strong>Allow</strong> or <strong>Deny</strong> overrides it for this user only. Rows with an override are highlighted;
                    the dot shows whether the user has access. A page's Allow / Deny applies to both its sidebar link and its pages.
                </p>

                @foreach ($groupedMenus as $sectionName => $menusInSection)
                    <h6 class="text-uppercase text-muted small fw-bold mt-4 mb-2" style="letter-spacing: 0.5px;">
                        {{ $sectionName }}
                    </h6>

                    <div class="row g-3 mb-2">
                        @foreach ($menusInSection as $menu)
                            @php
                                $otherLinkedPermissions = $menu->linkedPermissions->reject(
                                    fn ($p) => str_starts_with($p->name, 'manage ')
                                );
                            @endphp
                            <div class="col-lg-6 col-xxl-4">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-header bg-light fw-semibold small">{{ $menu->name }}</div>
                                    <div class="card-body p-0" style="max-height: 240px; overflow-y: auto;">
                                        <x-permission-chip name="menu_access" :id="$menu->id" label="View / Access" :page="true"
                                            :value="$pageRows[$menu->id]['value']"
                                            :source="$pageRows[$menu->id]['source']"
                                            :linkSource="$pageRows[$menu->id]['linkSource']" />

                                        {{-- Hidden action gates are checked via canAccessPermission(), so they use permission overrides --}}
                                        @foreach ($menu->actionChildren as $action)
                                            @php $actionPermission = $actionPermissions->get($action->permission_name); @endphp
                                            @if ($actionPermission)
                                                <x-permission-chip name="perm_access" :id="$actionPermission->id"
                                                    :label="$action->nav_name ?: $action->name"
                                                    :value="$permissionOverrides->get($actionPermission->id)"
                                                    :source="$defaultSources->get($action->permission_name)" />
                                            @endif
                                        @endforeach

                                        @foreach ($otherLinkedPermissions as $permission)
                                            <x-permission-chip name="perm_access" :id="$permission->id" :label="$permission->name"
                                                :value="$permissionOverrides->get($permission->id)"
                                                :source="$defaultSources->get($permission->name)" />
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach

                @if ($unassignedGrouped->isNotEmpty())
                    <h6 class="text-uppercase text-muted small fw-bold mt-4 mb-2" style="letter-spacing: 0.5px;">
                        Other Permissions
                    </h6>

                    <div class="row g-3 mb-2">
                        @foreach ($unassignedGrouped as $groupName => $permissionsInGroup)
                            <div class="col-lg-6 col-xxl-4">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-header bg-light fw-semibold small">{{ $groupName }}</div>
                                    <div class="card-body p-0" style="max-height: 240px; overflow-y: auto;">
                                        @foreach ($permissionsInGroup as $permission)
                                            <x-permission-chip name="perm_access" :id="$permission->id" :label="$permission->name"
                                                :value="$permissionOverrides->get($permission->id)"
                                                :source="$defaultSources->get($permission->name)" />
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <button type="submit" class="btn btn-primary mt-3"><i class="fas fa-save me-1"></i> Save Access</button>
            </form>
        @else
            <div class="text-muted">Select a user on the left to manage their access.</div>
        @endif
    </div>
</div>
@endsection

@push('script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('form_access');
    if (!form) return;

    // Highlight overridden rows, colour the dot by the resulting access, count the overrides
    function paint(row) {
        const value = row.querySelector('.btn-check:checked')?.value || 'inherit';
        const allowed = value === 'inherit' ? row.dataset.default === 'allow' : value === 'allow';
        row.classList.toggle('is-overridden', value !== 'inherit');
        const dot = row.querySelector('.access-dot');
        dot.classList.toggle('is-allowed', allowed);
        dot.title = allowed ? 'Has access' : 'No access';
    }

    function count() {
        const n = form.querySelectorAll('.access-row.is-overridden').length;
        document.getElementById('override_count').textContent = n === 0 ? 'no overrides' : n + (n === 1 ? ' override' : ' overrides');
    }

    form.addEventListener('change', function (e) {
        const row = e.target.closest('.access-row');
        if (row) { paint(row); count(); }
    });

    document.getElementById('btn_reset_access').addEventListener('click', function () {
        form.querySelectorAll('.btn-check[value="inherit"]').forEach((radio) => { radio.checked = true; });
        form.querySelectorAll('.access-row').forEach(paint);
        count();
    });

});
</script>
@endpush
