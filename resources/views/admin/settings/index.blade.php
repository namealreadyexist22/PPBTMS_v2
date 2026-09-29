@extends('BackEnd.layouts.master')

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-white pt-4 pb-3 px-4" style="border-bottom: 1px solid #f1f5f9;">
                <h5 class="m-0 fw-bold text-dark d-flex align-items-center" style="font-size: 1.05rem;">
                    <i class="fas fa-sliders-h text-muted me-2"></i>App Settings
                </h5>
            </div>

            <div class="card-body p-4">
                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                <form method="POST" action="{{ route('core.settings.update') }}" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div class="mb-3 d-flex align-items-center gap-3">
                        @if ($appLogo)
                            <img src="{{ asset('storage/' . $appLogo) }}" alt="Current logo"
                                class="rounded border" style="width: 64px; height: 64px; object-fit: contain;">
                        @endif
                        <div class="flex-grow-1">
                            <label class="form-label small fw-semibold text-muted mb-1">Logo</label>
                            <input type="file" name="app_logo" class="form-control" accept="image/*">
                            <div class="form-text">PNG/JPG, max 2MB. Leave blank to keep the current logo.</div>
                            @error('app_logo')
                                <div class="text-danger small">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted mb-1">App Name</label>
                        <input type="text" name="app_name" class="form-control" value="{{ old('app_name', $appName) }}" required>
                        @error('app_name')
                            <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold text-muted mb-1">Version</label>
                        <input type="text" name="app_version" class="form-control" value="{{ old('app_version', $appVersion) }}" required placeholder="e.g. 1.0.0">
                        @error('app_version')
                            <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection