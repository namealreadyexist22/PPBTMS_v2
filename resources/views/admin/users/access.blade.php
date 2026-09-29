@extends('BackEnd.layouts.master')

@section('content')
<div class="row">
    <div class="col-12">
        <h1 class="h4 mb-3">User Access</h1>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
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
            <form method="POST" action="{{ route('core.access.update', $selectedUser) }}">
                @csrf
                @method('PUT')

                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h5 class="mb-0">{{ $selectedUser->fullname }}</h5>
                        <span class="text-muted small">
                            Roles: {{ $selectedUser->roles->pluck('name')->join(', ') ?: '— none —' }}
                        </span>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Save Access</button>
                </div>

                <p class="text-muted small">
                    The right switch turns on a personal override for that item; the left switch then sets
                    whether it's allowed or denied. Leave the right switch off to simply inherit from the
                    user's role.
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
                                $menuOverrideValue = $menuOverrides->get($menu->id);
                            @endphp
                            <div class="col-md-4">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-header bg-light fw-semibold small d-flex justify-content-between align-items-center" style="border-radius: 0px 0px 0 0;">
                                        {{ $menu->name }}
                                        <span class="text-muted text-uppercase ms-auto" style="font-size: 0.62rem; letter-spacing: 0.5px;">Override</span>
                                    </div>                             
                                    <div class="card-body p-0" style="max-height: 240px; overflow-y: auto;">
                                        <x-permission-chip stateField="menu_state" overrideField="menu_override"
                                            :id="$menu->id" :label="$menu->nav_name ?: $menu->name"
                                            :checked="$menuOverrideValue === 'allow'"
                                            :overridden="$menuOverrideValue !== null"
                                            :roleDefault="$rolePermissionNames->contains($menu->permission_name)" />

                                        @foreach ($otherLinkedPermissions as $permission)
                                            @php $permOverrideValue = $permissionOverrides->get($permission->id); @endphp
                                            <x-permission-chip stateField="perm_state" overrideField="perm_override"
                                                :id="$permission->id" :label="$permission->name"
                                                :checked="$permOverrideValue === 'allow'"
                                                :overridden="$permOverrideValue !== null"
                                                :roleDefault="$rolePermissionNames->contains($permission->name)" />
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
                            <div class="col-md-4">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-header bg-light fw-semibold small" style="border-radius: 10px 10px 0 0;">
                                        {{ $groupName }}
                                    </div>
                                    <div class="card-body p-0" style="max-height: 240px; overflow-y: auto;">
                                        @foreach ($permissionsInGroup as $permission)
                                            @php $permOverrideValue = $permissionOverrides->get($permission->id); @endphp
                                            <x-permission-chip stateField="perm_state" overrideField="perm_override"
                                                :id="$permission->id" :label="$permission->name"
                                                :checked="$permOverrideValue === 'allow'"
                                                :overridden="$permOverrideValue !== null"
                                                :roleDefault="$rolePermissionNames->contains($permission->name)" />
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <button type="submit" class="btn btn-primary mt-3">Save Access</button>
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
    document.querySelectorAll('.chip-override').forEach(function (overrideSwitch) {
        overrideSwitch.addEventListener('change', function () {
            const row = this.closest('.d-flex');
            const target = document.getElementById(this.dataset.target);
            const badge = row.querySelector('.chip-status-badge');
            const switchWrapper = row.querySelector('.chip-state-wrapper');

            if (!target || !badge || !switchWrapper) return;

            target.disabled = !this.checked;
            badge.style.display = this.checked ? 'none' : '';
            switchWrapper.style.display = this.checked ? '' : 'none';

            if (!this.checked) {
                target.checked = target.dataset.roleDefault === '1';
            }
        });
    });
});
</script>
@endpush