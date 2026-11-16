@php
    $usersActive = request()->routeIs('roles.*', 'users.*');
    $mastersActive = request()->routeIs('schools.*', 'departments.*', 'request-categories.*', 'reminders.*');
@endphp

<li class="nav-item">
    <a href="{{ route('home') }}" class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}">
        <i class="nav-icon bi bi-speedometer2"></i>
        <p>Dashboard</p>
    </a>
</li>

@canany(['View User Management', 'View Users', 'Manage Users', 'View Roles', 'Manage Roles'])
    <li class="nav-item {{ $usersActive ? 'menu-open' : '' }}">
        <a href="#" class="nav-link {{ $usersActive ? 'active' : '' }}">
            <i class="nav-icon bi bi-person-gear"></i>
            <p>Users <i class="nav-arrow bi bi-chevron-right"></i></p>
        </a>
        <ul class="nav nav-treeview">
            @can('View Roles')
                <li class="nav-item">
                    <a href="{{ route('roles.index') }}" class="nav-link {{ request()->routeIs('roles.*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-circle"></i>
                        <p>Roles</p>
                    </a>
                </li>
            @endcan
            @can('View Users')
                <li class="nav-item">
                    <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-circle"></i>
                        <p>Users</p>
                    </a>
                </li>
            @endcan
        </ul>
    </li>
@endcanany

@canany(['View Masters', 'View Schools', 'View Departments', 'View Request Categories', 'View Reminders'])
    <li class="nav-item {{ $mastersActive ? 'menu-open' : '' }}">
        <a href="#" class="nav-link {{ $mastersActive ? 'active' : '' }}">
            <i class="nav-icon bi bi-list-ul"></i>
            <p>Masters <i class="nav-arrow bi bi-chevron-right"></i></p>
        </a>
        <ul class="nav nav-treeview">
            @can('View Schools')
                <li class="nav-item">
                    <a href="{{ route('schools.index') }}" class="nav-link {{ request()->routeIs('schools.*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-circle"></i>
                        <p>Schools</p>
                    </a>
                </li>
            @endcan
            @can('View Departments')
                <li class="nav-item">
                    <a href="{{ route('departments.index') }}" class="nav-link {{ request()->routeIs('departments.*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-circle"></i>
                        <p>Departments</p>
                    </a>
                </li>
            @endcan
            @can('View Request Categories')
                <li class="nav-item">
                    <a href="{{ route('request-categories.index') }}" class="nav-link {{ request()->routeIs('request-categories.*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-circle"></i>
                        <p>Request Categories</p>
                    </a>
                </li>
            @endcan
            @can('View Reminders')
                <li class="nav-item">
                    <a href="{{ route('reminders.index') }}" class="nav-link {{ request()->routeIs('reminders.*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-circle"></i>
                        <p>Reminders</p>
                    </a>
                </li>
            @endcan
        </ul>
    </li>
@endcanany

@can('View Settings')
    <li class="nav-item">
        <a href="{{ route('configurations.index') }}" class="nav-link {{ request()->routeIs('configurations.*') ? 'active' : '' }}">
            <i class="nav-icon bi bi-gear"></i>
            <p>Settings</p>
        </a>
    </li>
@endcan
