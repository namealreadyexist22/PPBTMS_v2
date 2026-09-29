@extends('BackEnd.layouts.master')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white pt-4 pb-3 px-4 d-flex align-items-center justify-content-between"
                style="border-bottom: 1px solid #f1f5f9;">
                <h5 class="m-0 fw-bold text-dark">
                    <i class="fas fa-bell text-muted me-2"></i>Notifications
                </h5>
            </div>

            <div class="list-group list-group-flush">
                @forelse ($notifications as $notification)
                    <div class="list-group-item d-flex gap-3 py-3 {{ $notification->read_at ? '' : 'bg-primary bg-opacity-10' }}">
                        <div class="bg-light text-primary rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                             style="width: 40px; height: 40px;">
                            <i class="{{ $notification->data['icon'] ?? 'fas fa-bell' }}"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-semibold">{{ $notification->data['title'] ?? 'Notification' }}</div>
                            <div class="text-muted small">{{ $notification->data['message'] ?? '' }}</div>
                            <div class="text-muted small mt-1">{{ $notification->created_at->diffForHumans() }}</div>
                        </div>
                        @if (! empty($notification->data['url']))
                            <a href="{{ $notification->data['url'] }}" class="btn btn-sm btn-outline-secondary align-self-center">Open</a>
                        @endif
                    </div>
                @empty
                    <div class="p-4 text-center text-muted">No notifications yet.</div>
                @endforelse
            </div>

            <div class="card-footer bg-white">
                {{ $notifications->links() }}
            </div>
        </div>
    </div>
</div>
@endsection