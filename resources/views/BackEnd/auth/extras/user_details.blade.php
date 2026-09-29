<div class="d-flex align-items-center justify-content-center text-start mx-auto p-2" style="max-width: 380px;">
    <div class="flex-shrink-0 me-3">
        <a href="{{ $user->avatarUrl() }}" target="_blank" title="View Avatar">
            <img src="{{ $user->avatarUrl() }}"
                alt="{{ $user->fullname }}"
                class="rounded-circle border border-2 border-light shadow-sm hover-border"
                style="width: 50px; height: 50px; object-fit: cover; cursor: pointer;">
        </a>
    </div>

    <div class="flex-grow-1">
        <h6 class="mb-0 fw-bold text-dark text-nowrap">{{ $user->fullname }}</h6>

        <div class="text-muted small my-0" style="font-size: 0.85rem;">
            <i class="fas fa-at text-secondary me-1" style="width: 14px;"></i>{{ $user->username }}
        </div>

        <div class="text-muted small mb-2" style="font-size: 0.85rem;">
            <i class="fas fa-envelope text-secondary me-1" style="width: 14px;"></i>{{ $user->email }}
        </div>

        <div class="d-flex gap-1">
            <span class="badge bg-secondary bg-opacity-10 text-secondary px-2 py-1 rounded-pill border border-secondary border-opacity-25 text-uppercase" style="font-size: 0.7rem;">
                <i class="fas fa-tags me-1"></i>Cat: {{ $user->categories }}
            </span>
            <span class="badge bg-primary bg-opacity-10 text-primary px-2 py-1 rounded-pill border border-primary border-opacity-25 text-uppercase" style="font-size: 0.7rem;">
                <i class="fas fa-user-shield me-1"></i>Role: {{ $user->roles->pluck('name')->join(', ') ?: 'None' }}
            </span>
        </div>
    </div>
</div>
