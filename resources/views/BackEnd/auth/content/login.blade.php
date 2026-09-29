<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ setting('app_name', config('app.name')) }} | Login</title>
  <link rel="shortcut icon" href="{{asset('assets/img/SRALOGO.png') }}">
  <script src="https://cdn.tailwindcss.com"></script>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

  <style>
    body {
      font-family: 'Segoe UI', Roboto, sans-serif;
      background-color: #27533a;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    #floating-back-btn {
      position: fixed;
      bottom: 20px;
      right: 20px;
      z-index: 9999;
      width: 48px;
      height: 48px;
      background-color: #ffffff;
      color: #6b7c70;
      border: 1px solid #e1e8e3;
      border-radius: 12px;
      display: flex;
      justify-content: center;
      align-items: center;
      transition: all 0.2s ease;
      box-shadow: 0 4px 12px rgba(0,0,0,0.04);
    }
    #floating-back-btn:hover {
      color: #15803d;
      border-color: #bbf7d0;
      background-color: #f0fdf4;
    }

    /* Minimalist overrides for toast containers */
    #toast-container > div {
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1) !important;
        border-radius: 12px !important;
        opacity: 0.98 !important;
        font-size: 13px !important;
    }
  </style>
</head>
<body class="antialiased text-stone-800">

  <div class="min-h-screen w-full flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-6 bg-white p-8 sm:p-10 rounded-2xl border border-stone-100 shadow-xl shadow-stone-900/5">

      <div class="text-center">
        <h2 class="text-2xl font-black tracking-wider text-transparent bg-clip-text bg-gradient-to-r from-[#166534] to-[#3f6212] uppercase">
            {{ setting('app_name', config('app.name')) }}
        </h2>
      </div>

      <div class="relative flex items-center justify-center my-4">
        <div class="border-t border-stone-200 w-full"></div>
        <span class="absolute bg-white px-3 text-[10px] font-bold uppercase tracking-widest text-stone-400">
            SIGN IN
        </span>
      </div>

      <form class="space-y-4" action="{{ route('auth.attempt') }}" method="POST">
        @csrf

        <div>
          <label for="username" class="block text-[11px] font-bold text-stone-500 uppercase tracking-wider mb-1">
            USERNAME
        </label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-stone-400">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
              </svg>
            </div>
            <input id="username"
                name="username"
                type="text"
                value="{{ old('username', request()->cookie('saved_username')) }}"
                autocomplete="username"
                required
                class="block w-full pl-10 pr-3.5 py-2.5 bg-stone-50/50 border border-stone-200 rounded-xl text-sm text-stone-800 placeholder-stone-400 focus:outline-none focus:border-green-600 focus:ring-1 focus:ring-green-600 transition duration-150"
                placeholder="Username or Email" autocomplete="off">
          </div>
        </div>

        <div>
          <label for="password" class="block text-[11px] font-bold text-stone-500 uppercase tracking-wider mb-1">Password</label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-stone-400">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
              </svg>
            </div>
            <input id="password"
                name="password"
                type="password"
                required
                class="block w-full pl-10 pr-3.5 py-2.5 bg-stone-50/50 border border-stone-200 rounded-xl text-sm text-stone-800 placeholder-stone-400 focus:outline-none focus:border-green-600 focus:ring-1 focus:ring-green-600 transition duration-150"
                placeholder="••••••••" autocomplete="off">
          </div>
        </div>

        <div class="flex items-center py-1">
          <input id="remember"
              name="remember"
              type="checkbox"
              {{ request()->cookie('saved_username') ? 'checked' : '' }}
              class="h-4 w-4 rounded border-stone-300 text-green-600 focus:ring-0 focus:ring-offset-0">
          <label for="remember" class="ml-2 block text-xs text-stone-500 select-none">
              Remember me
          </label>
        </div>

        <div class="pt-2">
          <button type="submit"
              class="w-full flex justify-center items-center gap-2 py-3 px-4 text-xs font-bold uppercase tracking-wider rounded-xl text-white bg-gradient-to-r from-green-600 to-emerald-700 hover:opacity-95 transition duration-150 shadow-md shadow-green-900/20">
              <span>Log in</span>
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
              </svg>
          </button>
        </div>
      </form>

      {{-- <div class="mt-4">
        <form action="{{ route('auth.redirect') }}" method="POST">
          @csrf
          <button type="submit"
             class="w-full flex items-center justify-center gap-3 bg-stone-50 hover:bg-stone-100 text-stone-700 font-bold text-xs uppercase tracking-wider py-2.5 px-4 border border-stone-200 rounded-xl transition duration-200 shadow-sm">
            <svg class="w-4 h-4 bg-white rounded-full p-0.5 shadow-sm" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
              <path d="M21.35,11.1H12v2.7h5.38c-0.24,1.28 -0.96,2.37 -2.04,3.1v2.6h3.3c1.93,-1.78 3.04,-4.4 3.04,-7.4C21.68,11.75 21.56,11.4 21.35,11.1z" fill="#4285F4" />
              <path d="M12,20.74c2.43,0 4.47,-0.8 5.96,-2.2l-3.3,-2.6c-0.9,0.6 -2.07,0.98 -3.32,0.98 -2.34,0 -4.33,-1.58 -5.03,-3.7H2.92v2.7C4.4,18.84 8.01,20.74 12,20.74z" fill="#34A853" />
              <path d="M6.97,13.22a5.2,5.2 0 0 1 0,-3.34V7.18H2.92a8.85,8.85 0 0 0 0,7.74l4.05,-2.7z" fill="#FBBC05" />
              <path d="M12,7.26c1.3,0 2.48,0.44 3.4,1.32l2.55,-2.55C16.42,4.64 14.39,4 12,4 8.01,4 4.4,5.9 2.92,8.82l4.05,3.14C7.67,8.84 9.66,7.26 12,7.26z" fill="#EA4335" />
            </svg>
            <span>Continue with Google</span>
          </button>
        </form>
      </div> --}}

      {{-- <div class="p-3 bg-amber-50 border border-amber-100 rounded-xl flex items-start gap-2.5">
        <svg class="h-4 w-4 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
        </svg>
        <p class="text-[10px] text-amber-800/80 leading-normal font-medium">This is a restricted network system. Accounts must be provisioned exclusively by SRA System Administrators.</p>
      </div> --}}

      <div class="pt-2 text-center text-[10px] text-stone-400 border-t border-stone-100 flex justify-between items-center tracking-wide">
        <p>&copy; 2026 <a href="https://www.sra.gov.ph" class="hover:text-green-600 underline">SRA</a> All rights reserved.</p>
        <p>Version 1.0</p>
      </div>
    </div>
  </div>

  {{-- <a href="{{ url('/') }}" id="floating-back-btn" title="Exit">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M13 5v6h6m-6 0h-6v6h6v-6z" />
    </svg>
  </a> --}}

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

  <script>
    $(document).ready(function() {
        // Global Toastr Options
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "timeOut": "4000"
        };

        // Catch basic session redirects (e.g., Logged out or Middleware Access Blocks)
        @if(session('success'))
            toastr.success("{{ session('success') }}");
        @endif

        @if(session('error'))
            toastr.error("{{ session('error') }}");
        @endif

        // Loop validation error arrays (e.g., Wrong Username/Password credentials)
        @if($errors->any())
            @foreach($errors->all() as $error)
                toastr.error("{{ $error }}");
            @endforeach
        @endif
    });
  </script>
</body>
</html>
