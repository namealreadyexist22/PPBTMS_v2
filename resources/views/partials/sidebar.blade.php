{{--
    Include this in your dashboard layout, e.g.:
    @include('partials.sidebar')

    Menu is cached per-request via the service; no need to worry about
    calling this more than once on a page.
--}}
@php
    $menuTree = app(\App\Services\MenuService::class)->getMenuTreeForUser(auth()->user());
@endphp

<aside class="sidebar p-3">
    <x-menu-tree :items="$menuTree" />
</aside>
