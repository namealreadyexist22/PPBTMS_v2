<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>
        {{ setting('app_name', config('app.name')) }}
    </title>
    <link rel="shortcut icon" href="{{asset('assets/img/SRALOGO.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

</head>
<body class="layout-fixed sidebar-expand-lg sidebar-mini bg-light" style="font-family: 'Inter', sans-serif;">
    <div class="app-wrapper">

        @include('BackEnd.layouts.navbar')

        @include('BackEnd.layouts.sidebar')

        <main class="app-main py-4">
            <div class="app-content">
                <div class="container-fluid px-4">
                    @yield('content')
                </div>
            </div>
        </main>

        <footer class="app-footer bg-white border-top text-secondary small py-3 px-4">
            <div class="container-fluid d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2">
                <div>
                    <span>Copyright &copy; 2026</span>
                    <a href="https://www.sra.gov.ph" target="_blank" class="fw-semibold text-dark text-decoration-none ms-1 hover-underline">SRA</a>.
                    <span class="text-muted ms-1">All rights reserved.</span>
                </div>
                <div class="text-muted">
                    <span class="badge bg-light text-secondary border px-2 py-1">
                        {{ setting('app_version', '1.0') }}
                    </span>
                </div>
            </div>
        </footer>
    </div>

    <script type="text/javascript">
        // Using window.onload to ensure your compiled Vite bundle has fully evaluated
        window.addEventListener('load', function() {
            toastr.options = {
                "closeButton": true,
                "progressBar": true,
                "positionClass": "toast-top-right"
            };

            @if(session('success'))
                toastr.success("{{ session('success') }}");
            @endif

            @if(session('error'))
                toastr.error("{{ session('error') }}");
            @endif

            @if(session('info'))
                toastr.info("{{ session('info') }}");
            @endif

            @if(session('warning'))
                toastr.warning("{{ session('warning') }}");
            @endif

            @if($errors->any())
                @foreach($errors->all() as $error)
                    toastr.error("{{ $error }}");
                @endforeach
            @endif
        });
    </script>

    @stack('script')
</body>
</html>
