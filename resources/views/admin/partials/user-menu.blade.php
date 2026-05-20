@php
    $user = auth()->user();
@endphp
<div class="header-user-menu">
    <details class="user-menu-details">
        <summary class="user-menu-trigger" aria-label="Account menu">
            <span class="user-avatar" aria-hidden="true">{{ $user->initials() }}</span>
            <span class="user-menu-label">
                <span class="user-menu-name">{{ $user->name }}</span>
                <span class="user-menu-email">{{ $user->email }}</span>
            </span>
            <svg class="user-menu-chevron" width="12" height="12" viewBox="0 0 12 12" fill="currentColor" aria-hidden="true">
                <path d="M2.5 4.5L6 8l3.5-3.5" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </summary>
        <div class="user-menu-dropdown" role="menu">
            <a href="{{ route('admin.profile.show') }}" class="user-menu-item {{ request()->routeIs('admin.profile.*') ? 'is-active' : '' }}" role="menuitem">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                Profile
            </a>
            <form method="POST" action="{{ route('logout') }}" class="user-menu-logout" role="none">
                @csrf
                <button type="submit" class="user-menu-item user-menu-item-logout" role="menuitem">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                    Logout
                </button>
            </form>
        </div>
    </details>
</div>
