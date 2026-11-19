@php
    $usersActive = request()->routeIs('roles.*', 'users.*');
    $mastersActive = request()->routeIs('schools.*', 'departments.*', 'request-categories.*', 'workflow-steps.*', 'configurations.*');
    $requestScope = request()->routeIs('requests.index') ? (in_array(request('scope'), ['inbox', 'all'], true) ? request('scope') : 'mine') : null;
    $requestsActive = request()->routeIs('requests.*');
    $inventoryActive = request()->routeIs('inventory.*');
    $awaitingMyAction = \App\Models\SchoolRequest::awaitingActionBy(auth()->user())->count();
@endphp

<li class="nav-item">
    <a href="{{ route('home') }}" class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}">
        <i class="nav-icon bi bi-speedometer2"></i>
        <p>Dashboard</p>
    </a>
</li>

<li class="nav-item {{ $requestsActive ? 'menu-open' : '' }}">
    <a href="#" class="nav-link {{ $requestsActive ? 'active' : '' }}">
        <i class="nav-icon bi bi-file-earmark-text"></i>
        <p>
            Requests
            @if ($awaitingMyAction > 0)
                <span class="badge text-bg-warning">{{ $awaitingMyAction }}</span>
            @endif
            <i class="nav-arrow bi bi-chevron-right"></i>
        </p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item">
            <a href="{{ route('requests.create') }}" class="nav-link {{ request()->routeIs('requests.create') ? 'active' : '' }}">
                <i class="nav-icon bi bi-circle"></i>
                <p>New Request</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('requests.index') }}" class="nav-link {{ $requestScope === 'mine' ? 'active' : '' }}">
                <i class="nav-icon bi bi-circle"></i>
                <p>My Requests</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('requests.index', ['scope' => 'inbox']) }}" class="nav-link {{ $requestScope === 'inbox' ? 'active' : '' }}">
                <i class="nav-icon bi bi-circle"></i>
                <p>
                    Awaiting My Action
                    @if ($awaitingMyAction > 0)
                        <span class="badge text-bg-warning">{{ $awaitingMyAction }}</span>
                    @endif
                </p>
            </a>
        </li>
        @can('View All Requests')
            <li class="nav-item">
                <a href="{{ route('requests.index', ['scope' => 'all']) }}" class="nav-link {{ $requestScope === 'all' ? 'active' : '' }}">
                    <i class="nav-icon bi bi-circle"></i>
                    <p>All Requests</p>
                </a>
            </li>
        @endcan
    </ul>
</li>

@canany(['View Stock', 'View Inventory Items', 'View Suppliers', 'View Inventory Setup'])
    <li class="nav-item {{ $inventoryActive ? 'menu-open' : '' }}">
        <a href="#" class="nav-link {{ $inventoryActive ? 'active' : '' }}">
            <i class="nav-icon bi bi-box-seam"></i>
            <p>Inventory <i class="nav-arrow bi bi-chevron-right"></i></p>
        </a>
        <ul class="nav nav-treeview">
            @can('View Stock')
                <li class="nav-item">
                    <a href="{{ route('inventory.stock.index') }}" class="nav-link {{ request()->routeIs('inventory.stock.*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-circle"></i>
                        <p>Stock Balances</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('inventory.stock-in.index') }}" class="nav-link {{ request()->routeIs('inventory.stock-in.*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-circle"></i>
                        <p>Stock In</p>
                    </a>
                </li>
            @endcan
            @can('View Inventory Items')
                <li class="nav-item">
                    <a href="{{ route('inventory.items.index') }}" class="nav-link {{ request()->routeIs('inventory.items.*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-circle"></i>
                        <p>Items</p>
                    </a>
                </li>
            @endcan
            @can('View Suppliers')
                <li class="nav-item">
                    <a href="{{ route('inventory.suppliers.index') }}" class="nav-link {{ request()->routeIs('inventory.suppliers.*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-circle"></i>
                        <p>Suppliers</p>
                    </a>
                </li>
            @endcan
            @can('View Inventory Setup')
                <li class="nav-item">
                    <a href="{{ route('inventory.categories.index') }}" class="nav-link {{ request()->routeIs('inventory.categories.*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-circle"></i>
                        <p>Item Categories</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('inventory.units.index') }}" class="nav-link {{ request()->routeIs('inventory.units.*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-circle"></i>
                        <p>Units of Measure</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('inventory.stores.index') }}" class="nav-link {{ request()->routeIs('inventory.stores.*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-circle"></i>
                        <p>Stores</p>
                    </a>
                </li>
            @endcan
        </ul>
    </li>
@endcanany

@canany(['View User Management', 'View Users', 'Manage Users', 'View Roles', 'Manage Roles'])
    <li class="nav-item {{ $usersActive ? 'menu-open' : '' }}">
        <a href="#" class="nav-link {{ $usersActive ? 'active' : '' }}">
            <i class="nav-icon bi bi-person-gear"></i>
            <p>User Management <i class="nav-arrow bi bi-chevron-right"></i></p>
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

@canany(['View Administration', 'View Schools', 'View Departments', 'View Request Categories', 'View Approval Chain', 'View Settings'])
    <li class="nav-item {{ $mastersActive ? 'menu-open' : '' }}">
        <a href="#" class="nav-link {{ $mastersActive ? 'active' : '' }}">
            <i class="nav-icon bi bi-list-ul"></i>
            <p>Administration <i class="nav-arrow bi bi-chevron-right"></i></p>
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
            @can('View Approval Chain')
                <li class="nav-item">
                    <a href="{{ route('workflow-steps.index') }}" class="nav-link {{ request()->routeIs('workflow-steps.*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-circle"></i>
                        <p>Approval Chain</p>
                    </a>
                </li>
            @endcan
            @can('View Settings')
                <li class="nav-item">
                    <a href="{{ route('configurations.index') }}" class="nav-link {{ request()->routeIs('configurations.*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-circle"></i>
                        <p>Settings</p>
                    </a>
                </li>
            @endcan
        </ul>
    </li>
@endcanany

