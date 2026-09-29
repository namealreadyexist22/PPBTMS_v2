{{-- Usage: <x-permission-override-list :grouped="$groupedActionPermissions" :overrides="$permissionOverrides" /> --}}
{{-- $overrides is a [permission_id => 'allow'|'deny'] map; anything absent = inherit --}}
@props(['grouped', 'overrides'])

@forelse ($grouped as $groupName => $permissionsInGroup)
    <div class="mb-3">
        <div class="fw-semibold text-uppercase text-muted small mb-1" style="letter-spacing: 0.5px;">
            {{ $groupName }}
        </div>

        @foreach ($permissionsInGroup as $permission)
            @php $current = $overrides->get($permission->id, 'inherit'); @endphp
            <div class="d-flex align-items-center gap-3 mb-1">
                <span style="min-width: 220px;">{{ $permission->name }}</span>

                @foreach (['inherit' => 'Inherit from role', 'allow' => 'Allow', 'deny' => 'Deny'] as $value => $label)
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio"
                            name="permission_overrides[{{ $permission->id }}]" id="pov-{{ $permission->id }}-{{ $value }}"
                            value="{{ $value }}" {{ $current === $value ? 'checked' : '' }}>
                        <label class="form-check-label small" for="pov-{{ $permission->id }}-{{ $value }}">{{ $label }}</label>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
@empty
    <div class="text-muted small">No action-level permissions created yet.</div>
@endforelse
