@extends('layouts.app')
@section('title', 'Usuários — '.config('brand.name'))
@section('bodyClass','manager-page user-directory-page')

@section('content')
<main><section class="workspace manager-workspace">
    <div class="manager-head directory-head">
        <div><p class="eyebrow">ADMINISTRAÇÃO</p><h1>Diretório de <em>usuários.</em></h1><p>Contas, equipes e permissões em uma área dedicada e paginada.</p></div>
        <a class="clear-filter" href="{{ route('manager.dashboard') }}">← Voltar para gestão</a>
    </div>
    @include('manager._navigation')

    <div class="stats stats-three directory-stats">
        <div><span>TOTAL</span><strong>{{ $totalUsers }}</strong></div>
        <div><span>ATIVOS</span><strong>{{ $activeUsers }}</strong></div>
        <div><span>GESTORES</span><strong>{{ $managerUsers }}</strong></div>
    </div>

    <article class="card">
        <div class="report-head"><div><p class="eyebrow">IDENTIDADE CENTRAL</p><h2>Migração para o Keycloak</h2><p>{{ $keycloakLinkedUsers }} vinculados · {{ $keycloakPendingUsers }} pendentes</p></div></div>
        <p class="form-hint">O vínculo preserva equipes, férias, solicitações e o login local de contingência. A migração é executada primeiro em modo de simulação pelo procedimento operacional.</p>
    </article>

    <article class="card directory-create">
        <div class="report-head"><div><p class="eyebrow">NOVO ACESSO</p><h2>Criar usuário</h2></div><p class="form-hint">{{ config('auth.password_recovery_enabled')?'Deixe a senha vazia para enviar um link de definição de senha.':'Defina uma senha provisória; o envio por e-mail será liberado após configurar HTTPS e SMTP.' }}</p></div>
        <form method="post" action="{{ route('admin.users.store') }}" class="management-form directory-create-form">@csrf
            <label>Nome completo<input name="name" value="{{ old('name') }}" required></label>
            <label>E-mail corporativo<input name="email" type="email" value="{{ old('email') }}" required></label>
            <label>Perfil<select name="role"><option value="employee">Colaborador</option><option value="manager">Gestor</option><option value="super_admin">Super Admin</option></select></label>
            <label>Equipe própria<select name="team"><option value="">Sem equipe</option>@foreach($teams->where('active',true) as $team)<option @selected(old('team')===$team->name)>{{ $team->name }}</option>@endforeach</select></label>
            <label>Data de admissão<input name="hired_on" type="date" max="{{ today()->format('Y-m-d') }}" value="{{ old('hired_on') }}"><small>É só informar esta data; o saldo de férias será calculado automaticamente.</small></label>
            <label>Equipes administradas<select name="manager_teams[]" multiple class="click-multi" data-placeholder="Selecione para gestores">@foreach($teams->where('active',true) as $team)<option value="{{ $team->id }}">{{ $team->name }}</option>@endforeach</select></label>
            <label>Senha provisória (opcional)<input name="password" type="password" minlength="8" autocomplete="new-password"></label>
            <label>Confirmar senha provisória<input name="password_confirmation" type="password" minlength="8" autocomplete="new-password"></label>
            <button class="primary">Criar usuário</button>
        </form>
    </article>

    <article class="card filters-card directory-filters">
        <form method="get" class="filters">
            <label class="directory-search">Nome ou e-mail<input name="q" value="{{ request('q') }}" placeholder="Buscar no diretório"></label>
            <label>Perfil<select name="role"><option value="">Todos</option>@foreach(['employee'=>'Colaborador','manager'=>'Gestor','super_admin'=>'Super Admin'] as $value=>$label)<option value="{{ $value }}" @selected(request('role')===$value)>{{ $label }}</option>@endforeach</select></label>
            <label>Equipe<select name="team"><option value="">Todas</option>@foreach($teams as $team)<option @selected(request('team')===$team->name)>{{ $team->name }}</option>@endforeach</select></label>
            <label>Status<select name="status"><option value="">Todos</option><option value="active" @selected(request('status')==='active')>Ativos</option><option value="inactive" @selected(request('status')==='inactive')>Inativos</option></select></label>
            <button class="primary">Filtrar</button><a class="clear-filter" href="{{ route('admin.users.index') }}">Limpar</a>
        </form>
    </article>

    <article class="card report users-report directory-report">
        <div class="report-head"><div><p class="eyebrow">RESULTADOS</p><h2>{{ $users->total() }} usuários encontrados</h2></div><small>Página {{ $users->currentPage() }} de {{ $users->lastPage() }}</small></div>
        <div class="table-wrap"><table><thead><tr><th>USUÁRIO</th><th>PERFIL</th><th>EQUIPE PRÓPRIA / ADMINISTRADAS</th><th>IDENTIDADE</th><th>STATUS</th><th>AÇÕES</th></tr></thead><tbody>
        @forelse($users as $user)<tr>
            <td><b>{{ $user->name }}</b><small>{{ $user->email }}</small></td>
            <td>{{ ['employee'=>'Colaborador','manager'=>'Gestor','super_admin'=>'Super Admin'][$user->role] }}</td>
            <td>{{ $user->team?:'Sem equipe' }}<small>Contratação: {{ $user->hired_on?->format('d/m/Y') ?? 'não informada' }}</small>@if($user->isManager())<small>Administra: {{ $user->role==='super_admin'?'Todas':($user->managedTeams->pluck('name')->join(', ')?:'nenhuma') }}</small>@endif</td>
            <td><span class="directory-status {{ $user->keycloak_subject?'is-active':'is-inactive' }}">{{ $user->keycloak_subject?'Keycloak vinculado':'Migração pendente' }}</span></td>
            <td><span class="directory-status {{ $user->active?'is-active':'is-inactive' }}">{{ $user->active?'Ativo':'Inativo' }}</span></td>
            <td><details class="user-menu"><summary>Gerenciar</summary><div class="user-actions-panel">
                <form method="post" action="{{ route('admin.users.update',$user) }}" class="edit-user-form">@csrf @method('PUT')
                    <input name="name" value="{{ $user->name }}" required><input name="email" type="email" value="{{ $user->email }}" required>
                    <select name="role">@foreach(['employee'=>'Colaborador','manager'=>'Gestor','super_admin'=>'Super Admin'] as $role=>$label)<option value="{{ $role }}" @selected($user->role===$role)>{{ $label }}</option>@endforeach</select>
                    <select name="team"><option value="">Sem equipe própria</option>@foreach($teams as $team)<option @selected($user->team===$team->name)>{{ $team->name }}</option>@endforeach</select>
                    <label>Contratação<input name="hired_on" type="date" max="{{ today()->format('Y-m-d') }}" value="{{ $user->hired_on?->format('Y-m-d') }}"></label>
                    <select name="manager_teams[]" multiple class="click-multi" data-placeholder="Equipes administradas">@foreach($teams as $team)<option value="{{ $team->id }}" @selected($user->managedTeams->contains($team))>{{ $team->name }}</option>@endforeach</select>
                    <input name="password" type="password" placeholder="Nova senha (opcional)"><input name="password_confirmation" type="password" placeholder="Confirmar nova senha">
                    <button class="primary">Salvar alterações</button>
                </form>
                <div class="directory-row-actions">@if(config('auth.password_recovery_enabled'))<form method="post" action="{{ route('admin.users.password-link',$user) }}">@csrf<button class="text-action">Enviar acesso por e-mail</button></form>@endif @if(!$user->is(auth()->user()))<form method="post" action="{{ route('admin.users.toggle',$user) }}">@csrf @method('PATCH')<button class="text-action">{{ $user->active?'Desativar':'Ativar' }}</button></form>@endif</div>
            </div></details></td>
        </tr>@empty<tr><td colspan="6" class="empty">Nenhum usuário corresponde aos filtros.</td></tr>@endforelse
        </tbody></table></div>
        @if($users->hasPages())<nav class="directory-pagination" aria-label="Paginação">@if($users->onFirstPage())<span>← Anterior</span>@else<a href="{{ $users->previousPageUrl() }}">← Anterior</a>@endif<strong>{{ $users->currentPage() }} / {{ $users->lastPage() }}</strong>@if($users->hasMorePages())<a href="{{ $users->nextPageUrl() }}">Próxima →</a>@else<span>Próxima →</span>@endif</nav>@endif
    </article>
</section></main>
@endsection

@push('scripts')<script src="{{ asset('assets/manager.js') }}?v={{ filemtime(public_path('assets/manager.js')) }}" defer></script>@endpush
