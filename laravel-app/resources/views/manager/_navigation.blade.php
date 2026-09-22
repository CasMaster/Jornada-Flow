<nav class="manager-sections" aria-label="Áreas de gestão">
    <a class="{{ request()->routeIs('manager.dashboard') ? 'active' : '' }}" @if(request()->routeIs('manager.dashboard')) aria-current="page" @endif href="{{ route('manager.dashboard') }}">Solicitações</a>
    <a class="{{ request()->routeIs('manager.vacations.*') ? 'active' : '' }}" @if(request()->routeIs('manager.vacations.*')) aria-current="page" @endif href="{{ route('manager.vacations.index') }}">Férias</a>
    @if(auth()->user()->role === 'super_admin')
        <a class="{{ request()->routeIs('admin.teams.*') ? 'active' : '' }}" @if(request()->routeIs('admin.teams.*')) aria-current="page" @endif href="{{ route('admin.teams.index') }}">Equipes</a>
        <a class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}" @if(request()->routeIs('admin.users.*')) aria-current="page" @endif href="{{ route('admin.users.index') }}">Usuários</a>
        <a class="{{ request()->routeIs('admin.audits') ? 'active' : '' }}" @if(request()->routeIs('admin.audits')) aria-current="page" @endif href="{{ route('admin.audits') }}">Auditoria</a>
        <a class="{{ request()->routeIs('admin.operations') ? 'active' : '' }}" @if(request()->routeIs('admin.operations')) aria-current="page" @endif href="{{ route('admin.operations') }}">Operação</a>
        <a class="{{ request()->routeIs('admin.deploy.*') ? 'active' : '' }}" @if(request()->routeIs('admin.deploy.*')) aria-current="page" @endif href="{{ route('admin.deploy.index') }}">Deploy</a>
    @endif
</nav>
