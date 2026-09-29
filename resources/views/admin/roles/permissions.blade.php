@extends('BackEnd.layouts.master')

@section('content')
<h1 class="h4 mb-3">Permissions for role: {{ $role->name }}</h1>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<form method="POST" action="{{ route('core.roles.permissions.update', $role) }}">
    @csrf
    @method('PUT')

    @foreach ($groupedMenus as $sectionName => $menusInSection)
        <h6 class="text-uppercase text-muted small fw-bold mt-4 mb-2" style="letter-spacing: 0.5px;">
            {{ $sectionName }}
        </h6>

        <div class="row g-3 mb-2">
            @foreach ($menusInSection as $menu)
                @php
                    // Any "manage X" permission linked to this menu is folded into
                    // the View/Access toggle itself — granting one always grants both,
                    // so the sidebar and the actual page access never drift apart.
                    $otherLinkedPermissions = $menu->linkedPermissions->reject(
                        fn ($p) => str_starts_with($p->name, 'manage ')
                    );
                @endphp
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-light fw-semibold small" style="border-radius: 10px 10px 0 0;">
                            {{ $menu->name }}
                        </div>
                        <div class="card-body p-0" style="max-height: 240px; overflow-y: auto;">
                            <x-role-permission-chip name="menu_ids" :id="$menu->id" :value="$menu->id"
                                label="View / Access" :checked="$rolePermissionNames->contains($menu->permission_name)" />

                            @foreach ($otherLinkedPermissions as $permission)
                                <x-role-permission-chip name="permission_names" :id="$permission->id" :value="$permission->name"
                                    :label="$permission->name" :checked="$rolePermissionNames->contains($permission->name)" />
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
        <p class="text-muted small">Not linked to a specific menu.</p>

        <div class="row g-3 mb-2">
            @foreach ($unassignedGrouped as $groupName => $permissionsInGroup)
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-light fw-semibold small" style="border-radius: 10px 10px 0 0;">
                            {{ $groupName }}
                        </div>
                        <div class="card-body p-0" style="max-height: 240px; overflow-y: auto;">
                            @foreach ($permissionsInGroup as $permission)
                                <x-role-permission-chip name="permission_names" :id="$permission->id" :value="$permission->name"
                                    :label="$permission->name" :checked="$rolePermissionNames->contains($permission->name)" />
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <button type="submit" class="btn btn-primary mt-3">Save</button>
    <a href="{{ route('core.roles.index') }}" class="btn btn-outline-secondary mt-3">Back</a>
</form>
@endsection