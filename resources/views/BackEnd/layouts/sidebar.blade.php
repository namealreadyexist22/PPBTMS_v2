{{-- AdminLTE 4 Sidebar --}}
<aside
    class="app-sidebar shadow"
    data-bs-theme="dark"
    style="
        background-color: #111827;
        border-right: 1px solid rgba(255,255,255,0.03);
    "
>

    {{-- Sidebar Brand --}}
    <div class="sidebar-brand">
        {{-- <a href="{{ route('app.main.home') }}" class="brand-link"> --}}
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
                data-accordion="false"
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
```
