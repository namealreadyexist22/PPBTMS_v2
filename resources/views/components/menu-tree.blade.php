{{-- Usage: <x-menu-tree :items="$menuTree" /> --}}
@props(['items'])

@foreach ($items as $item)
    @php
        $hasChildren = $item->children->isNotEmpty();
        $isActive = $item->route && request()->routeIs($item->route);
        $childIsActive = $hasChildren && $item->children->contains(
            fn ($child) => $child->route && request()->routeIs($child->route)
        );
        $displayName = $item->nav_name ?: $item->name;
    @endphp

    @if ($hasChildren)
        <li class="nav-item {{ $childIsActive ? 'menu-open' : '' }}">
            <a href="#" class="nav-link" title="{{ $displayName }}">
                <i class="nav-icon {{ $item->icon ?: 'far fa-circle' }}"></i>
                <p>
                    {{ $displayName }}
                    <i class="nav-arrow fas fa-angle-right"></i>
                </p>
            </a>
            <ul class="nav nav-treeview">
                <x-menu-tree :items="$item->children" />
            </ul>
        </li>
    @else
        <li class="nav-item">
            <a href="{{ $item->resolvedUrl() }}"
               class="nav-link {{ $isActive ? 'active' : '' }}"
               title="{{ $displayName }}">
                <i class="nav-icon {{ $item->icon ?: 'far fa-circle' }}"></i>
                <p>{{ $displayName }}</p>
            </a>
        </li>
    @endif
@endforeach