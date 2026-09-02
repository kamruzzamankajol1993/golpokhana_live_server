<header class="progga-navbar">
  <div class="progga-navbar-leading">
    <button class="progga-navbar-toggle" id="sidebarToggle" type="button" aria-label="Toggle navigation menu">
      <i class="bi bi-list"></i>
    </button>
    <div class="progga-navbar-heading">
      <div class="progga-navbar-title">Dashboard</div>
      <div class="progga-navbar-subtitle">{{ date('l, d F Y') }}</div>
    </div>
  </div>
  <div class="progga-navbar-actions">

    <a class="progga-btn progga-btn-secondary progga-btn-sm progga-navbar-cache-btn" href="{{ url('/clear') }}" title="Clear System Cache">
        <i class="bi bi-arrow-clockwise"></i> <span class="progga-navbar-action-label">Clear Cache</span>
    </a>

    <button class="progga-navbar-icon-btn progga-navbar-notification-btn" type="button" title="Notifications" aria-label="Notifications"><i class="bi bi-bell"></i><span class="progga-notif-dot"></span></button>
    <a class="progga-btn progga-btn-secondary progga-btn-sm progga-navbar-pos-btn" href="{{ route('pos.index') }}" title="Open POS"><i class="bi bi-display"></i> <span class="progga-navbar-action-label">POS</span></a>

    <div class="dropdown progga-navbar-user-menu">
      <img src="{{ Auth::user()->image ? asset('public/' . Auth::user()->image) : 'https://ui-avatars.com/api/?name='.urlencode(Auth::user()->name).'&background=21352a&color=d5aa65&size=68' }}" class="progga-navbar-avatar" alt="User" data-bs-toggle="dropdown" role="button" aria-expanded="false">
      <ul class="dropdown-menu dropdown-menu-end" style="border:1px solid var(--progga-border);border-radius:var(--progga-radius);">

        @can('profile-view')
        <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person-circle me-2"></i>Profile</a></li>
        @endcan

        @can('systemsetting-view')
        <li><a class="dropdown-item" href="{{ route('settings.index') }}"><i class="bi bi-gear me-2"></i>Settings</a></li>
        @endcan

        <li><hr class="dropdown-divider"></li>

        <li>
          <a class="dropdown-item text-danger" href="{{ route('logout') }}"
             onclick="event.preventDefault(); document.getElementById('logout-form').requestSubmit();">
            <i class="bi bi-box-arrow-right me-2"></i>Logout
          </a>
          <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none" data-pos-logout="1">
              @csrf
          </form>
        </li>

      </ul>
    </div>
  </div>
</header>
