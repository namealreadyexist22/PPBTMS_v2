{{-- Usage: <x-permission-chip stateField="menu_state" overrideField="menu_override"
        :id="$menu->id" label="View / Access" :checked="$effective"
        :overridden="$hasOverride" :roleDefault="$fromRole" /> --}}
{{-- Role already grants it: 2-switch layout (status badge <-> allow/deny
     switch, revealed by the override switch) — same as before.
     Role does NOT grant it: only one meaningful override exists (grant it
     anyway), so a single switch covers both fields at once. --}}
@props(['stateField', 'overrideField', 'id', 'label', 'checked' => false, 'overridden' => false, 'roleDefault' => false])

@php
    $stateId = "state-{$stateField}-{$id}";
    $overrideId = "override-{$overrideField}-{$id}";
    $displayChecked = $overridden ? $checked : $roleDefault;
@endphp

@if ($roleDefault)
    <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
        <label class="small mb-0 flex-grow-1" for="{{ $stateId }}">{{ $label }}</label>

        <span class="badge bg-success-subtle text-success me-3 chip-status-badge"
              style="{{ $overridden ? 'display:none;' : '' }}">
            Allowed
        </span>

        <div class="form-check form-switch mb-0 me-3 chip-state-wrapper" style="{{ $overridden ? '' : 'display:none;' }}" title="Allow / Deny">
            <input class="form-check-input chip-state" type="checkbox" role="switch"
                name="{{ $stateField }}[{{ $id }}]" id="{{ $stateId }}" value="1" data-role-default="1"
                {{ $displayChecked ? 'checked' : '' }} {{ $overridden ? '' : 'disabled' }}>
        </div>

        <div class="form-check form-switch mb-0" title="Override the role default for this user">
            <input class="form-check-input chip-override" type="checkbox" role="switch"
                name="{{ $overrideField }}[{{ $id }}]" id="{{ $overrideId }}" value="1"
                {{ $overridden ? 'checked' : '' }} data-target="{{ $stateId }}">
        </div>
    </div>
@else
    <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
        <label class="small mb-0 flex-grow-1" for="{{ $overrideId }}">
            {{ $label }}
            <span class="badge bg-light text-muted border ms-1" style="font-size: 0.6rem;">not in role</span>
        </label>

        {{-- Always "allow" when this switch is on — there's nothing else it could mean here. --}}
        <input type="hidden" name="{{ $stateField }}[{{ $id }}]" value="1">

        <div class="form-check form-switch mb-0" title="Allow this user anyway, even though their role doesn't grant it">
            <input class="form-check-input chip-override" type="checkbox" role="switch"
                name="{{ $overrideField }}[{{ $id }}]" id="{{ $overrideId }}" value="1"
                {{ $overridden ? 'checked' : '' }}>
        </div>
    </div>
@endif