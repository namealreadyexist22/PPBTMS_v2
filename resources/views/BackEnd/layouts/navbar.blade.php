<nav class="app-header navbar navbar-expand bg-white border-bottom shadow-sm px-3">
    <div class="container-fluid px-0">
        <ul class="navbar-nav align-items-center">
            <li class="nav-item">
                <a class="nav-link text-secondary hover-bg rounded-circle d-flex align-items-center justify-content-center"
                   data-lte-toggle="sidebar"
                   href="javascript:void(0)"
                   role="button"
                   style="width: 38px; height: 38px; transition: all 0.2s;">
                    <i class="fas fa-bars fs-5"></i>
                </a>
            </li>
        </ul>

        <ul class="navbar-nav ms-auto align-items-center gap-2">

            <li class="nav-item d-none d-sm-inline-block">
                <a class="nav-link position-relative text-secondary hover-bg rounded-circle d-flex align-items-center justify-content-center"
                data-bs-toggle="offcanvas"
                data-bs-target="#offcanvasRight"
                aria-controls="offcanvasRight"
                href="javascript:void(0)"
                role="button"
                style="width: 38px; height: 38px; transition: all 0.2s;">
                    <i class="fas fa-bell fs-5"></i>
                    @php $unreadCount = auth()->check() ? auth()->user()->unreadNotifications->count() : 0; @endphp
                    <span id="notif-badge" class="position-absolute top-0 end-0 badge rounded-pill bg-danger"
                        style="font-size: 0.6rem; transform: translate(20%, -20%); {{ $unreadCount > 0 ? '' : 'display: none;' }}">
                        {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                    </span>
                </a>
            </li>

            <li class="nav-item dropdown">
                <a class="nav-link p-1 d-flex align-items-center gap-2 border rounded-pill bg-light shadow-sm hover-border transition-all"
                   data-bs-toggle="dropdown"
                   href="javascript:void(0)"
                   aria-expanded="false"
                   style="padding-right: 12px !important;">

                    @if(auth()->check() && !empty(auth()->user()->img_slug) && auth()->user()->img_slug !== 'avatar-default.png')
                        <img src="{{ asset('storage/avatars/' . auth()->user()->img_slug) }}"
                             alt="Avatar"
                             class="rounded-circle bg-white border"
                             width="30"
                             height="30"
                             style="object-fit: cover;">
                    @else
                        <img src="{{ asset('assets/img/userlogo.png') }}"
                             alt="Avatar"
                             class="rounded-circle bg-white border"
                             width="30"
                             height="30"
                             style="object-fit: cover;">
                    @endif

                    @if(auth()->check())
                        <span class="d-none d-md-inline-block fw-semibold text-dark small">
                            {{ ucwords(strtolower(auth()->user()->fname ?? 'User')) }}
                        </span>
                    @endif
                    <i class="fas fa-chevron-down text-muted entry-arrow" style="font-size: 10px;"></i>
                </a>

                @if(auth()->check())
                    <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end border-0 shadow-lg py-0 overflow-hidden rounded-3 mt-2" style="min-width: 290px; z-index: 9999;">
                        <div class="p-4 text-center text-white" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);">
                            <div class="position-relative d-inline-block mb-2">

                                @if(!empty(auth()->user()->img_slug) && auth()->user()->img_slug !== 'avatar-default.png')
                                    <img src="{{ asset('storage/avatars/' . auth()->user()->img_slug) }}"
                                         alt="Display Avatar"
                                         class="rounded-circle border border-2 border-white shadow-sm"
                                         width="68"
                                         height="68"
                                         style="object-fit: cover;">
                                @else
                                    <img src="{{ asset('assets/img/userlogo.png') }}"
                                         alt="Display Avatar"
                                         class="rounded-circle border border-2 border-white shadow-sm"
                                         width="68"
                                         height="68"
                                         style="object-fit: cover;">
                                @endif

                            </div>
                            <h6 class="fw-bold text-truncate mb-0" style="letter-spacing: -0.1px;">
                                {{ ucwords(strtolower(auth()->user()->fname ?? '')) }} {{ ucwords(strtolower(auth()->user()->lname ?? '')) }}
                            </h6>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1 mt-2 fw-medium text-uppercase tracking-wider" style="font-size: 10px;">
                                {{ auth()->user()->roles->pluck('name')->join(', ') ?: 'None' }}
                            </span>
                        </div>

                        <div class="p-2 bg-white">
                            <a href="{{ route('app.main.profile') }}" class="dropdown-item px-3 py-2 d-flex align-items-center text-secondary rounded-2 custom-menu-item">
                                <div class="bg-light text-muted rounded d-flex align-items-center justify-content-center me-3" style="width: 32px; height: 32px;">
                                    <i class="fas fa-user-circle fs-6"></i>
                                </div>
                                <span class="fw-medium text-dark" style="font-size: 14px;">Account Settings</span>
                            </a>
                        </div>

                        <div class="p-2 bg-light border-top">
                            <a href="javascript:void(0)"
                               class="dropdown-item px-3 py-2 d-flex align-items-center text-danger rounded-2 custom-menu-item hover-danger-bg"
                               onclick="event.preventDefault(); document.getElementById('frm-logout').submit();">
                                <div class="bg-danger-subtle text-danger rounded d-flex align-items-center justify-content-center me-3" style="width: 32px; height: 32px;">
                                    <i class="fas fa-sign-out-alt fs-6"></i>
                                </div>
                                <span class="fw-semibold" style="font-size: 14px;">Sign Out</span>
                            </a>
                            <form id="frm-logout" action="{{ route('auth.logout') }}" method="POST" style="display: none;">
                                @csrf
                            </form>
                        </div>
                    </div>
                @else
                    <div class="dropdown-menu dropdown-menu-md dropdown-menu-end border-0 shadow-lg p-2 rounded-3 mt-2">
                        <a href="{{ route('auth.login') }}" class="btn btn-primary w-100 fw-bold py-2 small d-flex align-items-center justify-content-center gap-2">
                            <i class="fas fa-sign-in-alt"></i> Sign In to Portal
                        </a>
                    </div>
                @endif
            </li>
        </ul>
    </div>
</nav>

<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasRight" aria-labelledby="offcanvasRightLabel">
    <div class="offcanvas-header text-white" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);">
        <h5 class="offcanvas-title fw-bold" id="offcanvasRightLabel">Notifications</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>

    <div class="offcanvas-body p-0">
        @auth
            @php $recentNotifications = auth()->user()->notifications()->latest()->take(8)->get(); @endphp

            @if ($recentNotifications->isNotEmpty())
                <div class="p-2 border-bottom d-flex justify-content-end">
                    <button type="button" id="btn-mark-all-read" class="btn btn-sm btn-link text-decoration-none">
                        Mark all as read
                    </button>
                </div>
            @endif

            <div class="list-group list-group-flush" id="notification-list">
                @forelse ($recentNotifications as $notification)
                    <a href="javascript:void(0)"
                       class="list-group-item list-group-item-action notification-item d-flex gap-3 py-3 {{ $notification->read_at ? '' : 'bg-primary bg-opacity-10' }}"
                       data-id="{{ $notification->id }}"
                       data-url="{{ $notification->data['url'] ?? '' }}">
                        <div class="bg-light text-primary rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                             style="width: 36px; height: 36px;">
                            <i class="{{ $notification->data['icon'] ?? 'fas fa-bell' }}"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-semibold small text-dark">{{ $notification->data['title'] ?? 'Notification' }}</div>
                            <div class="small text-muted">{{ $notification->data['message'] ?? '' }}</div>
                            <div class="small text-muted mt-1" style="font-size: 0.7rem;">{{ $notification->created_at->diffForHumans() }}</div>
                        </div>
                    </a>
                @empty
                    <div class="p-4 text-center text-muted small">No notifications yet.</div>
                @endforelse
            </div>

            <div class="p-2 border-top text-center">
                <a href="{{ route('core.notifications.index') }}" class="small fw-semibold text-decoration-none">View All</a>
            </div>
        @endauth
    </div>
</div>

@push('script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.notification-item').forEach(function (item) {
        item.addEventListener('click', function () {
            const id = this.dataset.id;
            const url = this.dataset.url;

            fetch(`/core/notifications/${id}/read`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            }).finally(function () {
                if (url) window.location.href = url;
            });
        });
    });

    const markAllBtn = document.getElementById('btn-mark-all-read');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function () {
            fetch('/core/notifications/read-all', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            }).then(function () {
                window.location.reload();
            });
        });
    }
});

@auth
document.addEventListener('DOMContentLoaded', function () {
    window.Echo.private('App.Models.User.{{ auth()->id() }}')
        .notification((notification) => {
            const badge = document.getElementById('notif-badge');
            const currentCount = parseInt(badge.textContent) || 0;
            const newCount = currentCount + 1;
            badge.textContent = newCount > 9 ? '9+' : newCount;
            badge.style.display = 'inline-block';

            const list = document.getElementById('notification-list');
            const emptyState = list.querySelector('.text-muted.text-center');
            if (emptyState) emptyState.remove();

            const item = document.createElement('a');
            item.href = 'javascript:void(0)';
            item.className = 'list-group-item list-group-item-action notification-item d-flex gap-3 py-3 bg-primary bg-opacity-10';
            item.dataset.id = notification.id;
            item.dataset.url = notification.url ?? '';
            item.innerHTML = `
                <div class="bg-light text-primary rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px;">
                    <i class="${notification.icon ?? 'fas fa-bell'}"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="fw-semibold small text-dark">${notification.title}</div>
                    <div class="small text-muted">${notification.message}</div>
                    <div class="small text-muted mt-1" style="font-size: 0.7rem;">Just now</div>
                </div>
            `;

            item.addEventListener('click', function () {
                fetch(`/core/notifications/${this.dataset.id}/read`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                }).finally(() => {
                    if (this.dataset.url) window.location.href = this.dataset.url;
                });
            });

            list.prepend(item);

            if (window.toastr) {
                toastr.info(notification.message, notification.title);
            }
        });
});
@endauth
</script>
@endpush