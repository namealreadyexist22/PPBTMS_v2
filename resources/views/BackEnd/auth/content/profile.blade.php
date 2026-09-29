@extends('BackEnd.layouts.master')

@section('content')
@php
    // Swapped guard profile detection back to the standard global web session context
    $user = auth()->user();
@endphp

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-xl-11">

            <div class="d-flex flex-column flex-md-row align-items-center justify-content-between pb-4 mb-5 border-bottom border-light-subtle">
                <div class="d-flex flex-column flex-md-row align-items-center gap-4 text-center text-md-start">
                    <div class="position-relative">
                        <img src="{{ asset('assets/img/userlogo.png') }}"
                             alt="Profile Identity"
                             class="rounded-circle border bg-white p-2 shadow-sm"
                             width="84"
                             height="84"
                             style="object-fit: cover;">
                    </div>
                    <div>
                        <h4 class="fw-bold text-dark mb-1" style="font-family: 'Inter', sans-serif; font-size: 20px; letter-spacing: -0.02em;">
                            {{ ucwords(strtolower($user->fname ?? 'System')) }} {{ ucwords(strtolower($user->lname ?? 'User')) }}
                        </h4>
                        <p class="text-secondary small mb-0 opacity-75" style="font-size: 12px; letter-spacing: 0.01em;">
                            {{ $user->roles->pluck('name')->join(', ') ?: 'None' }}
                            &bull; Active Gateway Session
                        </p>
                    </div>
                </div>
            </div>

            <div class="row g-5">

                <div class="col-12 col-md-4">
                    <h6 class="text-uppercase text-muted fw-bold mb-4" style="font-size: 10.5px; letter-spacing: 1px; opacity: 0.6;">
                        <i class="fas fa-id-card me-2 text-xs"></i> Personal Details
                    </h6>
                    <div class="d-flex flex-column gap-3">
                        <div class="pb-2 border-bottom border-light-subtle">
                            <span class="d-block text-muted mb-1" style="font-size: 11px;">First Name</span>
                            <span class="text-dark fw-medium" style="font-size: 13.5px;">{{ ucwords(strtolower($user->fname ?? '—')) }}</span>
                        </div>
                        <div class="pb-2 border-bottom border-light-subtle">
                            <span class="d-block text-muted mb-1" style="font-size: 11px;">Last Name</span>
                            <span class="text-dark fw-medium" style="font-size: 13.5px;">{{ ucwords(strtolower($user->lname ?? '—')) }}</span>
                        </div>
                        <div class="pb-2 border-bottom border-light-subtle">
                            <span class="d-block text-muted mb-1" style="font-size: 11px;">Username Account</span>
                            <span class="font-monospace fw-bold text-primary" style="font-size: 13px;">{{ $user->username ?? '—' }}</span>
                        </div>
                        <div class="pb-2">
                            <span class="d-block text-muted mb-1" style="font-size: 11px;">Primary Email Address</span>
                            <span class="text-secondary text-break" style="font-size: 13px; font-weight: 500;">{{ $user->email ?? '—' }}</span>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-4 border-start border-light-subtle">
                    <h6 class="text-uppercase text-muted fw-bold mb-4 ps-md-2" style="font-size: 10.5px; letter-spacing: 1px; opacity: 0.6;">
                        <i class="fas fa-lock me-2 text-xs"></i> Security Configuration
                    </h6>

                    <form id="frm_profile_cpass" autocomplete="off" class="d-flex flex-column gap-3 ps-md-2">
                        <div>
                            <label class="text-muted mb-1" style="font-size: 11px; font-weight: 500;">Current Password</label>
                            <input type="password" name="current_password" class="form-control form-control-sm bg-light-subtle border-light-subtle rounded-3 py-2" placeholder="••••••••" required style="font-size: 13px;">
                        </div>
                        <div>
                            <label class="text-muted mb-1" style="font-size: 11px; font-weight: 500;">New Password</label>
                            <input type="password" id="new_password" name="password" class="form-control form-control-sm bg-light-subtle border-light-subtle rounded-3 py-2" placeholder="••••••••" required style="font-size: 13px;">
                        </div>
                        <div>
                            <label class="text-muted mb-1" style="font-size: 11px; font-weight: 500;">Confirm Password</label>
                            <input type="password" name="password_confirmation" class="form-control form-control-sm bg-light-subtle border-light-subtle rounded-3 py-2" placeholder="••••••••" required style="font-size: 13px;">
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="btn btn-dark w-100 text-white fw-semibold py-2 shadow-sm rounded-3" id="btn_password_save" style="font-size: 12px; background-color: #111827; border: none;">
                                <i class="fas fa-key me-2 text-xs opacity-70"></i> Update Credentials
                            </button>
                        </div>
                    </form>

                    <div class="mt-4 pt-4 border-top border-light-subtle ps-md-2">
                        <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded-3 border border-light-subtle">
                            <div class="d-flex align-items-center gap-3 min-width-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" class="flex-shrink-0">
                                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" fill="#FBBC05"/>
                                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                                </svg>
                                <div class="text-truncate">
                                    <span class="fw-bold d-block text-dark lh-1 mb-1" style="font-size: 12px;">Google Identity</span>
                                    @if(!empty($user->google_id))
                                        <small class="text-success fw-semibold d-block" style="font-size: 10px;">Connected</small>
                                    @else
                                        <small class="text-muted d-block" style="font-size: 10px;">Not Linked</small>
                                    @endif
                                </div>
                            </div>
                            <div>
                                <button type="button" class="btn btn-sm px-3 py-1 fw-bold {{ !empty($user->google_id) ? 'btn-outline-danger' : 'btn-outline-secondary' }}" id="btn_google_toggle" data-linked="{{ !empty($user->google_id) ? 'true' : 'false' }}" style="font-size: 10.5px; border-radius: 6px;">
                                    {{ !empty($user->google_id) ? 'Unlink' : 'Link' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-4 border-start border-light-subtle">
                    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2 ps-md-2">
                        <h6 class="text-uppercase text-muted fw-bold m-0" style="font-size: 10.5px; letter-spacing: 1px; opacity: 0.6;">
                            <i class="fas fa-network-wired me-2 text-xs"></i> Session Trackers
                        </h6>
                        @if(count($activeSessions) > 1)
                            <button type="button" class="btn btn-link text-danger p-0 border-0 fw-bold text-uppercase text-decoration-none small" style="font-size: 10px;" onclick="terminateSession()">
                                Flush Devices
                            </button>
                        @endif
                    </div>

                    <div class="d-flex flex-column gap-3 ps-md-2">
                        @if(count($activeSessions) > 0)
                            @foreach($activeSessions as $session)
                                @php
                                    $ua = strtolower($session->user_agent ?? '');

                                    if (strpos($ua, 'opera gx') !== false || strpos($ua, 'opr/') !== false) {
                                        $browserName = 'Opera GX';
                                        $browserIcon = 'fab fa-opera text-danger';
                                    } elseif (strpos($ua, 'chrome') !== false) {
                                        $browserName = 'Chrome';
                                        $browserIcon = 'fab fa-chrome text-primary';
                                    } elseif (strpos($ua, 'safari') !== false) {
                                        $browserName = 'Safari';
                                        $browserIcon = 'fab fa-safari text-info';
                                    } elseif (strpos($ua, 'firefox') !== false) {
                                        $browserName = 'Firefox';
                                        $browserIcon = 'fab fa-firefox text-warning';
                                    } elseif (strpos($ua, 'edge') !== false) {
                                        $browserName = 'Edge';
                                        $browserIcon = 'fab fa-edge text-info';
                                    } else {
                                        $browserName = 'Browser';
                                        $browserIcon = 'fas fa-window-maximize text-secondary';
                                    }

                                    if (strpos($ua, 'iphone') !== false || strpos($ua, 'ipad') !== false) {
                                        $platform = 'iOS';
                                    } elseif (strpos($ua, 'android') !== false) {
                                        $platform = 'Android';
                                    } elseif (strpos($ua, 'windows') !== false) {
                                        $platform = 'Windows';
                                    } elseif (strpos($ua, 'macintosh') !== false) {
                                        $platform = 'macOS';
                                    } else {
                                        $platform = 'OS';
                                    }

                                    $isCurrentDevice = ($session->id === session()->getId());
                                @endphp

                                <div class="d-flex align-items-center justify-content-between p-3 rounded-3 border border-light-subtle {{ $isCurrentDevice ? 'bg-white shadow-sm' : 'bg-light-subtle opacity-75' }}">
                                    <div class="d-flex align-items-center gap-3 min-width-0">
                                        <i class="{{ $browserIcon }} fs-5 text-center flex-shrink-0" style="width: 24px; opacity: 0.8;"></i>
                                        <div class="text-truncate">
                                            <span class="text-sm fw-bold text-dark text-truncate d-block mb-0.5" style="font-size: 12.5px; line-height: 1.3;">
                                                {{ $browserName }} &bull; {{ $platform }}
                                                @if($isCurrentDevice)
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle ms-1 px-2 py-0.5" style="font-size: 8px; font-weight: 800; border-radius: 4px;">CURRENT</span>
                                                @endif
                                            </span>
                                            <small class="text-muted d-block font-monospace" style="font-size: 10.5px; opacity: 0.8;">
                                                {{ $session->ip_address }} &bull; {{ \Carbon\Carbon::createFromTimestamp($session->last_activity)->diffForHumans() }}
                                            </small>
                                        </div>
                                    </div>
                                    <div>
                                        <button type="button" class="btn btn-link text-danger p-0 border-0 fw-bold text-uppercase text-decoration-none opacity-50" style="font-size: 10px;" onclick="terminateSession('{{ $session->id }}', {{ $isCurrentDevice ? 'true' : 'false' }})">
                                            Exit
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="p-4 text-center text-muted border border-dashed rounded-3" style="font-size: 11px;">
                                No active telemetry found.
                            </div>
                        @endif
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>
@endsection
@push('script')
<script type="text/javascript">
(function waitForJQuery() {
    if (typeof window.jQuery !== 'undefined') {
        initProfileScripts(window.jQuery);
    } else {
        setTimeout(waitForJQuery, 50);
    }
})();

function initProfileScripts($) {
    $(document).ready(function() {

        // Helper function to dispatch profile actions uniformly
        function dispatchProfileAction(payload, successCallback, errorCallback) {
            $.ajax({
                url: '{{ route("app.main.profile") }}',
                type: 'POST',
                data: $.extend({}, payload, { _token: '{{ csrf_token() }}' }),
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        if (response.message) toastr.success(response.message);
                        if (successCallback) successCallback(response);
                        if (response.redirect) {
                            setTimeout(function() { window.location.href = response.redirect; }, 1200);
                        }
                    } else {
                        toastr.error(response.message || 'An error occurred.');
                        if (errorCallback) errorCallback();
                    }
                },
                error: function(xhr) {
                    if (errorCallback) errorCallback(xhr);
                    if (xhr.status === 422 && xhr.responseJSON?.errors) {
                        $.each(xhr.responseJSON.errors, function(key, value) {
                            toastr.warning(value);
                        });
                    } else {
                        toastr.error(xhr.responseJSON?.message || 'Failed to complete transaction.');
                    }
                }
            });
        }

        $(document).off('submit', '#frm_profile_cpass').on('submit', '#frm_profile_cpass', function(e) {
            e.preventDefault();
            let form = $(this);
            let btn = $('#btn_password_save');
            let origHtml = btn.html();

            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Updating...');

            let formData = {};
            $.each(form.serializeArray(), function(_, kv) { formData[kv.name] = kv.value; });
            formData.action_type = 'update_password';

            dispatchProfileAction(
                formData,
                function(response) { form[0].reset(); },
                function(xhr) { btn.prop('disabled', false).html(origHtml); }
            );
        });

        // B. Handle Google SSO Toggle Action (Link / Unlink) via modern unified pipeline
        $(document).off('click', '#btn_google_toggle').on('click', '#btn_google_toggle', function(e) {
            e.preventDefault();
            let btn = $(this);
            let isLinked = btn.data('linked') === true; // Looks at data-linked attribute
            let origHtml = btn.html();

            if (!isLinked) {
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Connecting...');

                dispatchProfileAction(
                    { action_type: 'link_google' },
                    null, // Handled automatically by your response.redirect condition!
                    function() { btn.prop('disabled', false).html(origHtml); }
                );
                return;
            }

            if (confirm('Are you sure you want to disconnect your Google Account?')) {
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Unlinking...');

                dispatchProfileAction(
                    { action_type: 'toggle_google' },
                    function() {
                        setTimeout(function() { window.location.reload(); }, 1000);
                    },
                    function() {
                        btn.prop('disabled', false).html(origHtml);
                    }
                );
            }
        });

        // Remote Active Browser Session Terminations (Single, Local, or Global)
        window.terminateSession = function(sessionId = null, isCurrentDevice = false) {
            let confirmationMessage = '';

            if (sessionId) {
                confirmationMessage = isCurrentDevice
                    ? 'Warning: Logging out of this device will end your active workspace session immediately. Proceed?'
                    : 'Log out this specific remote browser session?';
            } else {
                confirmationMessage = 'Log out of all other active browser instances?';
            }

            if (confirm(confirmationMessage)) {
                dispatchProfileAction({
                    action_type: 'terminate_session',
                    session_id: sessionId
                }, function(response) {
                    if (!isCurrentDevice) {
                        setTimeout(function() { window.location.reload(); }, 1000);
                    }
                });
            }
        };
    });
}
</script>
@endpush
