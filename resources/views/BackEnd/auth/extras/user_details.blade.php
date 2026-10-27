{{-- A user in the Manage Users list: avatar, name (with title / suffix), designation and office, login, region and role. --}}
<div class="d-flex align-items-center text-start py-1">
    <div class="flex-shrink-0 me-3">
        <a href="{{ $user->avatarUrl() }}" target="_blank" title="View Avatar">
            <img src="{{ $user->avatarUrl() }}"
                alt="{{ $user->fullname }}"
                class="rounded-circle border border-2 border-light shadow-sm hover-border"
                style="width: 46px; height: 46px; object-fit: cover; cursor: pointer;">
        </a>
    </div>

    <div class="flex-grow-1" style="min-width: 0;">
        <div class="fw-bold text-dark">{{ $user->fullname }}</div>
        @if ($user->designation || $user->office)
            <div class="text-muted small">{{ collect([$user->designation, $user->office?->shortName()])->filter()->implode(' · ') }}</div>
        @endif

        <div class="d-flex flex-wrap align-items-center column-gap-3 text-muted small mt-1" style="font-size: 0.8rem;">
            <span class="text-nowrap"><i class="fas fa-at text-secondary me-1"></i>{{ $user->username }}</span>
            <span class="text-truncate"><i class="fas fa-envelope text-secondary me-1"></i>{{ $user->email }}</span>
        </div>

        <div class="d-flex flex-wrap gap-1 mt-1">
            <span class="badge bg-secondary bg-opacity-10 text-secondary px-2 py-1 rounded-pill border border-secondary border-opacity-25 text-uppercase" style="font-size: 0.68rem;">
                <i class="fas fa-map-marker-alt me-1"></i>{{ $user->region->short() }}
            </span>
            <span class="badge bg-primary bg-opacity-10 text-primary px-2 py-1 rounded-pill border border-primary border-opacity-25 text-uppercase" style="font-size: 0.68rem;">
                <i class="fas fa-user-shield me-1"></i>Role: {{ $user->roles->pluck('name')->join(', ') ?: 'None' }}
            </span>
        </div>
    </div>
</div>
