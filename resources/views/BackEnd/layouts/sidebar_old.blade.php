<!-- AdminLTE 4 Sidebar container -->
<aside class="app-sidebar shadow-sm" data-bs-theme="dark" style="background-color: #111827; border-right: 1px solid rgba(255,255,255,0.03); width: 240px;">

    <!-- Brand Logo Space -->
    <div class="sidebar-brand border-bottom border-secondary-subtle" style="height: 56px; border-color: rgba(255,255,255,0.05) !important;">
            <span class="brand-text fw-bold text-white tracking-wider" style="font-size: 13px; letter-spacing: 1.5px; opacity: 0.9;">
                {{ setting('app_name', 'PORTAL') }}
            </span>
    </div>

    <!-- Navigation Sidebar Wrapper -->
    <div class="sidebar-wrapper py-2">
        <nav class="px-2">
            <ul class="nav sidebar-menu flex-column gap-1" data-lte-toggle="treeview" role="menu" data-accordion="false">

                {{-- <li class="nav-header text-uppercase tracking-widest text-muted fw-bold ps-3 mb-1 mt-2" style="font-size: 10px; letter-spacing: 0.8px; opacity: 0.4;">
                    Main Menu
                </li> --}}

                @php
                    $menuTree = app(\App\Core\Services\MenuService::class)->getMenuTreeForUser(auth()->user());
                @endphp

                <x-menu-tree :items="$menuTree" />

            </ul>
        </nav>
    </div>
</aside>
