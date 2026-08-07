@extends('layouts.app')
@section('title','Gestor — Híbrido')
@section('bodyClass','manager-page')

@section('content')
<main>
    <section class="workspace manager-workspace">
        <div class="manager-head">
            <div>
                <p class="eyebrow">PAINEL DO GESTOR</p>
                <h1>Gestão do <em>trabalho remoto.</em></h1>
                <p>Ciclo {{ $start->format('d/m/Y') }} até {{ $end->format('d/m/Y') }}.</p>
            </div>
            <a class="primary button-link" href="{{ route('manager.export',request()->query()) }}">Exportar dados ↓</a>
        </div>

        <nav class="manager-sections">
            <a href="#registros">Solicitações</a>
            @if(auth()->user()->role==='super_admin')
                <a href="#equipes">Equipes</a>
                <a href="{{ route('admin.users.index') }}">Diretório de usuários</a>
            @endif
        </nav>

        <article class="card filters-card" id="registros">
            <div class="report-head filter-priority-head">
                <div><p class="eyebrow">PESQUISA E FILTROS</p><h2>Localizar solicitações</h2></div>
                <a class="clear-filter" href="{{ route('manager.dashboard') }}">Limpar filtros</a>
            </div>
            <form method="get" class="filters request-filters">
                <label>Ciclo 20–19<select name="cycle">@foreach($cycles as $cycle)<option value="{{ $cycle['value'] }}" @selected(request('cycle',$cycles[0]['value'])===$cycle['value'])>{{ $cycle['label'] }}</option>@endforeach</select></label>
                <label>Equipe<select name="team"><option value="">Todas permitidas</option>@foreach($teams as $team)<option @selected(request('team')===$team->name)>{{ $team->name }}</option>@endforeach</select></label>
                <label>Status<select name="status"><option value="">Todos</option>@foreach(['pending'=>'Pendentes','approved'=>'Aprovadas','rejected'=>'Recusadas'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></label>
                <label>Colaboradores<select name="employees[]" multiple class="click-multi" data-placeholder="Selecionar colaboradores">@foreach($employees as $employee)<option value="{{ $employee->email }}" @selected(in_array($employee->email,(array)request('employees',[])))>{{ $employee->name }} · {{ $employee->team }}</option>@endforeach</select></label>
                <button class="primary">Pesquisar</button>
            </form>
        </article>

        <div class="stats stats-three compact-stats">
            <div><span>SOLICITAÇÕES</span><strong>{{ $records->count() }}</strong></div>
            <div><span>PENDENTES</span><strong>{{ $records->where('status','pending')->count() }}</strong></div>
            <div><span>COLABORADORES</span><strong>{{ $records->pluck('user_id')->unique()->count() }}</strong></div>
        </div>

        <details class="card report requests-accordion" @if(request()->hasAny(['cycle','team','status','employees'])) open @endif>
            <summary class="requests-accordion-summary">
                <span><span class="eyebrow">SOLICITAÇÕES</span><strong>{{ $records->count() }} resultados</strong></span>
                <span class="requests-accordion-action"><span class="when-closed">Exibir lista</span><span class="when-open">Ocultar lista</span></span>
            </summary>
            <div class="table-wrap compact-requests-table"><table>
                <thead><tr><th>COLABORADOR</th><th>EQUIPE</th><th>DATA</th><th>STATUS</th><th>ANÁLISE</th></tr></thead>
                <tbody>@forelse($records as $record)
                    <tr class="request-row request-{{ $record->status }}">
                        <td><b>{{ $record->user->name }}</b><small>{{ $record->user->email }}</small></td>
                        <td>{{ $record->user->team }}</td><td><b>{{ $record->work_date->format('d/m/Y') }}</b></td>
                        <td><span class="request-badge">{{ ['pending'=>'Pendente','approved'=>'Aprovada','rejected'=>'Recusada'][$record->status] }}</span></td>
                        <td class="review-actions"><details class="request-menu"><summary>Analisar</summary><form method="post" action="{{ route('manager.review',$record) }}" class="request-menu-panel">@csrf<button class="approve-action" name="decision" value="approved">✓ Aprovar</button><button class="reject-action" name="decision" value="rejected" onclick="return confirm('Recusar e arquivar esta solicitação?')">× Recusar</button></form></details></td>
                    </tr>
                @empty<tr><td colspan="5" class="empty">Nenhuma solicitação neste ciclo.</td></tr>@endforelse</tbody>
            </table></div>
        </details>

        @if(auth()->user()->role==='super_admin')
            <div class="admin-grid admin-grid-directory">
                <article class="card admin-card" id="equipes">
                    <div class="report-head"><div><p class="eyebrow">CADASTRO CENTRAL</p><h2>Equipes</h2></div></div>
                    <form method="post" action="{{ route('admin.teams.store') }}" class="inline-create">@csrf<input name="name" required placeholder="Nome da nova equipe"><button class="primary">Adicionar</button></form>
                    <div class="compact-list">@foreach($allTeams as $team)<div><span><b>{{ $team->name }}</b><small>{{ $team->active?'Ativa':'Inativa' }}</small></span><form method="post" action="{{ route('admin.teams.toggle',$team) }}">@csrf @method('PATCH')<button class="text-action">{{ $team->active?'Desativar':'Ativar' }}</button></form></div>@endforeach</div>
                </article>
                <article class="card admin-card directory-callout">
                    <div><p class="eyebrow">ACESSOS E PERMISSÕES</p><h2>Diretório de usuários</h2><p>Cadastre pessoas, filtre equipes, atribua gestores e envie links seguros para definição ou recuperação de senha.</p></div>
                    <a class="primary button-link" href="{{ route('admin.users.index') }}">Gerenciar usuários →</a>
                </article>
            </div>
        @endif
    </section>
</main>
@endsection

@push('scripts')<script src="{{ asset('assets/manager.js') }}?v={{ filemtime(public_path('assets/manager.js')) }}" defer></script>@endpush
