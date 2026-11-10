{{-- AdminLTE 4 Sidebar --}}
<aside
    class="app-sidebar shadow"
    data-bs-theme="dark"
    style="
        background-color: #111827;
        border-right: 1px solid rgba(255,255,255,0.03);
    "
>

    {{-- Collapsed (icons only): the mark alone, centered over the menu icons; the name comes back on hover --}}
    <style>
        .sidebar-mini.sidebar-collapse .app-sidebar:not(:hover) .sidebar-brand { justify-content: center; padding-left: 0; padding-right: 0; }
        .sidebar-mini.sidebar-collapse .app-sidebar:not(:hover) .sidebar-brand .brand-mark { margin-right: 0 !important; }
        .sidebar-mini.sidebar-collapse .app-sidebar:not(:hover) .sidebar-brand .brand-text { display: none; }
    </style>

    {{-- Sidebar Brand --}}
    <div class="sidebar-brand">
        {{-- <a href="{{ route('app.main.home') }}" class="brand-link"> --}}
            {{-- Procurement mark: a checked-off clipboard (plan, request, award) --}}
            <span class="brand-mark d-inline-flex align-items-center justify-content-center me-2 flex-shrink-0" aria-hidden="true"
                  style="width: 28px; height: 28px; border-radius: 8px; background: linear-gradient(135deg, #10b981 0%, #2563eb 100%); box-shadow: 0 2px 6px rgba(37, 99, 235, .35);">
                <i class="fas fa-clipboard-check text-white" style="font-size: 14px;"></i>
            </span>
            <span
                class="brand-text fw-bold text-white"
                style="
                    font-size: 13px;
                    letter-spacing: 1.5px;
                    opacity: .9;
                "
            >
                {{ setting('app_name', 'PORTAL') }}
            </span>
        {{-- </a> --}}
    </div>


    {{-- Sidebar Wrapper --}}
    <div class="sidebar-wrapper" data-overlayscrollbars-viewport="scrollbarHidden overflowXScroll overflowYScroll">

        <nav class="mt-2" aria-label="Main navigation">

            <ul
                class="nav sidebar-menu flex-column"
                data-lte-toggle="treeview"
                data-accordion="true" {{-- opening a dropdown closes the one already open --}}
                id="navigation"
                tabindex="-1"
            >

                @php
                    $menuTree = app(\App\Core\Services\MenuService::class)
                        ->getMenuTreeForUser(auth()->user());
                @endphp

                <x-menu-tree :items="$menuTree" />

            </ul>

        </nav>

    </div>

</aside>
