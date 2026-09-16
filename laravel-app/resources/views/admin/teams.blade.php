@extends('layouts.app')
@section('title','Equipes — MixHome')
@section('bodyClass','manager-page')

@section('content')
<main><section class="workspace manager-workspace">
    <div class="manager-head"><div><p class="eyebrow">ADMINISTRAÇÃO</p><h1>Equipes e <em>coberturas.</em></h1><p>Cadastros, calendário corporativo e substituições temporárias em uma área dedicada.</p></div></div>
    @include('manager._navigation')

    <div class="admin-grid admin-grid-directory management-sections-grid">
        <article class="card admin-card">
            <div class="report-head"><div><p class="eyebrow">CADASTRO CENTRAL</p><h2>Equipes</h2><p>Organize os grupos usados nas permissões e nos filtros gerenciais.</p></div></div>
            <form method="post" action="{{ route('admin.teams.store') }}" class="inline-create">@csrf<input name="name" required placeholder="Nome da nova equipe"><button class="primary">Adicionar</button></form>
            <div class="compact-list">@foreach($allTeams as $team)<div><span><b>{{ $team->name }}</b><small>{{ $team->active ? 'Ativa' : 'Inativa' }}</small></span><form method="post" action="{{ route('admin.teams.toggle',$team) }}">@csrf @method('PATCH')<button class="text-action">{{ $team->active ? 'Desativar' : 'Ativar' }}</button></form></div>@endforeach</div>
        </article>

        <article class="card admin-card">
            <div class="report-head"><div><p class="eyebrow">CALENDÁRIO CORPORATIVO</p><h2>Feriados e bloqueios</h2>@if($holidayLastSync)<small>Última atualização automática: {{ $holidayLastSync->timezone(config('app.timezone'))->format('d/m/Y H:i') }}@if($holidayLastSync->lt(now()->subDays(40))) · <b class="sync-warning">desatualizada</b>@endif</small>@else<small class="sync-warning">Sem sincronização automática registrada.</small>@endif</div></div>
            <form method="post" action="{{ route('admin.holidays.store') }}" class="holiday-form">@csrf<input type="date" name="date" required><input name="name" required maxlength="120" placeholder="Nome da data"><label><input type="checkbox" name="blocks_requests" value="1" checked> Bloquear solicitações</label><button class="primary">Adicionar</button></form>
            <div class="compact-list holiday-list">@forelse($holidays as $holiday)<div class="holiday-row"><time class="holiday-date-tile" datetime="{{ $holiday->date->format('Y-m-d') }}"><strong>{{ $holiday->date->format('d') }}</strong><span>{{ mb_strtoupper($holiday->date->translatedFormat('M')) }}</span><small>{{ $holiday->date->format('Y') }}</small></time><span class="holiday-details"><b>{{ $holiday->name }}</b><small><span class="holiday-scope holiday-scope-{{ $holiday->source==='manual'?'manual':($holiday->scope?:'national') }}">{{ $holiday->source==='manual'?'Corporativa':match($holiday->scope){'estadual'=>'Estadual','nacional'=>'Nacional','facultativo'=>'Facultativa',default=>'Importada'} }}</span>{{ $holiday->blocks_requests?'Solicitações bloqueadas':'Apenas informativa' }}</small></span><form method="post" action="{{ route('admin.holidays.destroy',$holiday) }}">@csrf @method('DELETE')<button class="text-action" onclick="return confirm('Remover esta data?')">Remover</button></form></div>@empty<p class="empty compact-empty">Nenhuma data futura cadastrada.</p>@endforelse</div>
        </article>

        <article class="card admin-card management-wide-card">
            <div class="report-head"><div><p class="eyebrow">COBERTURA TEMPORÁRIA</p><h2>Delegação de gestores</h2><p>O substituto recebe temporariamente as mesmas equipes do gestor de origem.</p></div></div>
            <form method="post" action="{{ route('admin.delegations.store') }}" class="delegation-form">@csrf
                <label>Gestor de origem<select name="manager_id" required><option value="">Selecione o gestor</option>@foreach($managers as $manager)<option value="{{ $manager->id }}">{{ $manager->name }}</option>@endforeach</select></label>
                <label>Gestor substituto<select name="delegate_id" required><option value="">Selecione o substituto</option>@foreach($managers as $manager)<option value="{{ $manager->id }}">{{ $manager->name }}</option>@endforeach</select></label>
                <fieldset><legend>Período da cobertura</legend><label>Início<input type="date" name="starts_on" required></label><span aria-hidden="true">→</span><label>Término<input type="date" name="ends_on" required></label></fieldset>
                <button class="primary">Programar delegação →</button>
            </form>
            <div class="compact-list">@forelse($delegations as $delegation)<div><span><b>{{ $delegation->manager->name }} → {{ $delegation->delegate->name }}</b><small>{{ $delegation->starts_on->format('d/m/Y') }} a {{ $delegation->ends_on->format('d/m/Y') }}</small></span><form method="post" action="{{ route('admin.delegations.destroy',$delegation) }}">@csrf @method('DELETE')<button class="text-action">Encerrar</button></form></div>@empty<p class="empty compact-empty">Nenhuma delegação vigente ou futura.</p>@endforelse</div>
        </article>
    </div>
</section></main>
@endsection
