@extends('layouts.app')
@section('title','Gestor — MixHome')
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
                <a href="{{ route('admin.audits') }}">Auditoria</a>
            @endif
        </nav>

        <article class="card filters-card" id="registros">
            <div class="report-head filter-priority-head">
                <div><p class="eyebrow">PESQUISA E FILTROS</p><h2>Localizar solicitações</h2><p class="filter-description">Combine os campos abaixo para encontrar rapidamente os registros que precisam de análise.</p></div>
            </div>
            <form method="get" class="filters request-filters" data-manager-filters>
                <label class="request-search">Nome ou e-mail<input name="q" value="{{ request('q') }}" placeholder="Pesquisar colaborador"></label>
                <label>Ciclo 20–19<select name="cycle">@foreach($cycles as $cycle)<option value="{{ $cycle['value'] }}" @selected(request('cycle',$cycles[0]['value'])===$cycle['value'])>{{ $cycle['label'] }}</option>@endforeach</select></label>
                <label>Equipes<select name="teams[]" multiple class="click-multi" data-placeholder="Todas as equipes permitidas" data-singular="equipe selecionada" data-plural="equipes selecionadas" data-search-placeholder="Buscar equipe..." data-empty="Nenhuma equipe encontrada">@foreach($teams as $team)<option value="{{ $team->name }}" @selected(in_array($team->name,(array)request('teams',request('team') ? [request('team')] : [])))>{{ $team->name }}</option>@endforeach</select></label>
                <label>Status<select name="status"><option value="">Todos</option>@foreach(['pending'=>'Pendentes','approved'=>'Aprovadas','rejected'=>'Recusadas'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></label>
                <label>Colaboradores<select name="employees[]" multiple class="click-multi" data-placeholder="Selecionar colaboradores" data-singular="colaborador selecionado" data-plural="colaboradores selecionados" data-search-placeholder="Buscar colaborador..." data-empty="Nenhum colaborador encontrado">@foreach($employees as $employee)<option value="{{ $employee->email }}" @selected(in_array($employee->email,(array)request('employees',[])))>{{ $employee->name }} · {{ $employee->team }}</option>@endforeach</select></label>
                <label>Ordenar por<select name="sort"><option value="date_asc" @selected(request('sort')==='date_asc')>Data crescente</option><option value="date_desc" @selected(request('sort')==='date_desc')>Data decrescente</option><option value="created_desc" @selected(request('sort')==='created_desc')>Envio mais recente</option><option value="status" @selected(request('sort')==='status')>Status</option></select></label>
                <label>Por página<select name="per_page">@foreach([25,50,100] as $size)<option value="{{ $size }}" @selected((int)request('per_page',25)===$size)>{{ $size }}</option>@endforeach</select></label>
                <div class="filter-actions">
                    <button class="primary" type="submit">Aplicar filtros</button>
                    <button class="secondary" type="button" data-save-filters>Salvar preferência</button>
                    @if(request()->hasAny(['q','cycle','team','teams','status','employees']))<a class="clear-filter" href="{{ route('manager.dashboard') }}">Limpar</a>@endif
                </div>
            </form>
        </article>

        <div class="stats stats-three compact-stats">
            <div><span>SOLICITAÇÕES NO FILTRO</span><strong>{{ $metrics->total ?? 0 }}</strong></div>
            <div class="pending-highlight"><span>AGUARDANDO SUA AÇÃO</span><strong>{{ $metrics->pending ?? 0 }}</strong></div>
            <div><span>COLABORADORES</span><strong>{{ $metrics->collaborators ?? 0 }}</strong></div>
        </div>

        <details class="card report requests-accordion" @if(request()->hasAny(['cycle','team','teams','status','employees'])) open @endif>
            <summary class="requests-accordion-summary">
                <span><span class="eyebrow">SOLICITAÇÕES</span><strong>{{ $records->total() }} resultados</strong></span>
                <span class="requests-accordion-action"><span class="when-closed">Exibir lista</span><span class="when-open">Ocultar lista</span></span>
            </summary>
            <form id="batch-form" method="post" action="{{ route('manager.review.batch') }}" class="batch-toolbar">@csrf<span><b>Ações em lote</b><small>Selecione até 100 solicitações nesta página.</small></span><input name="review_note" maxlength="1000" placeholder="Justificativa opcional para todas"><button name="decision" value="approved" class="approve-action">✓ Aprovar</button><button name="decision" value="rejected" class="reject-action" onclick="return confirm('Recusar e arquivar as solicitações selecionadas?')">× Recusar</button></form>
            <div class="table-wrap compact-requests-table"><table>
                <thead><tr><th><input type="checkbox" data-select-all aria-label="Selecionar todos"></th><th>COLABORADOR</th><th>EQUIPE</th><th>DATA</th><th>STATUS</th><th>ANÁLISE</th></tr></thead>
                <tbody>@forelse($records as $record)
                    <tr class="request-row request-{{ $record->status }}">
                        <td><input type="checkbox" name="requests[]" value="{{ $record->id }}" form="batch-form" class="batch-checkbox" aria-label="Selecionar solicitação de {{ $record->user->name }}"></td>
                        <td><b>{{ $record->user->name }}</b><small>{{ $record->user->email }}</small></td>
                        <td>{{ $record->user->team }}</td><td><b>{{ $record->work_date->format('d/m/Y') }}</b></td>
                        <td><span class="request-badge">{{ ['pending'=>'Pendente','approved'=>'Aprovada','rejected'=>'Recusada'][$record->status] }}</span></td>
                        <td class="review-actions"><details class="request-menu"><summary>Analisar</summary><form method="post" action="{{ route('manager.review',$record) }}" class="request-menu-panel">@csrf<textarea name="review_note" maxlength="1000" rows="3" placeholder="Justificativa opcional">{{ $record->review_note }}</textarea><button class="approve-action" name="decision" value="approved">✓ Aprovar</button><button class="reject-action" name="decision" value="rejected" onclick="return confirm('Recusar e arquivar esta solicitação?')">× Recusar</button></form></details>@if($record->review_note)<small class="review-note">{{ $record->review_note }}</small>@endif</td>
                    </tr>
                @empty<tr><td colspan="6" class="empty">Nenhuma solicitação neste ciclo.</td></tr>@endforelse</tbody>
            </table></div><div class="directory-pagination">{{ $records->links() }}</div>
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
                <article class="card admin-card" id="calendario">
                    <div class="report-head"><div><p class="eyebrow">CALENDÁRIO CORPORATIVO</p><h2>Feriados e bloqueios</h2>@if($holidayLastSync)<small>Última atualização automática: {{ $holidayLastSync->timezone(config('app.timezone'))->format('d/m/Y H:i') }}@if($holidayLastSync->lt(now()->subDays(40))) · <b class="sync-warning">desatualizada</b>@endif</small>@else<small class="sync-warning">Sem sincronização automática neste ciclo.</small>@endif</div></div>
                    <form method="post" action="{{ route('admin.holidays.store') }}" class="holiday-form">@csrf<input type="date" name="date" required><input name="name" required maxlength="120" placeholder="Nome da data"><label><input type="checkbox" name="blocks_requests" value="1" checked> Bloquear solicitações</label><button class="primary">Adicionar</button></form>
                    <div class="compact-list holiday-list">@forelse($holidays as $holiday)<div class="holiday-row"><time class="holiday-date-tile" datetime="{{ $holiday->date->format('Y-m-d') }}"><strong>{{ $holiday->date->format('d') }}</strong><span>{{ mb_strtoupper($holiday->date->translatedFormat('M')) }}</span><small>{{ $holiday->date->format('Y') }}</small></time><span class="holiday-details"><b>{{ $holiday->name }}</b><small><span class="holiday-scope holiday-scope-{{ $holiday->source==='manual'?'manual':($holiday->scope?:'national') }}">{{ $holiday->source==='manual'?'Corporativa':match($holiday->scope){'estadual'=>'Estadual','nacional'=>'Nacional','facultativo'=>'Facultativa',default=>'Importada'} }}</span>{{ $holiday->blocks_requests?'Solicitações bloqueadas':'Apenas informativa' }}</small></span><form method="post" action="{{ route('admin.holidays.destroy',$holiday) }}">@csrf @method('DELETE')<button class="text-action" onclick="return confirm('Remover esta data?')">Remover</button></form></div>@empty<p class="empty compact-empty">Nenhuma data neste ciclo.</p>@endforelse</div>
                </article>
                <article class="card admin-card" id="delegacoes">
                    <div class="report-head"><div><p class="eyebrow">COBERTURA TEMPORÁRIA</p><h2>Delegação de gestores</h2><p>O substituto recebe temporariamente as mesmas equipes do gestor de origem.</p></div></div>
                    <form method="post" action="{{ route('admin.delegations.store') }}" class="holiday-form">@csrf
                        <select name="manager_id" required><option value="">Gestor de origem</option>@foreach($managers as $manager)<option value="{{ $manager->id }}">{{ $manager->name }}</option>@endforeach</select>
                        <select name="delegate_id" required><option value="">Gestor substituto</option>@foreach($managers as $manager)<option value="{{ $manager->id }}">{{ $manager->name }}</option>@endforeach</select>
                        <input type="date" name="starts_on" required><input type="date" name="ends_on" required><button class="primary">Programar</button>
                    </form>
                    <div class="compact-list">@forelse($delegations as $delegation)<div><span><b>{{ $delegation->manager->name }} → {{ $delegation->delegate->name }}</b><small>{{ $delegation->starts_on->format('d/m/Y') }} a {{ $delegation->ends_on->format('d/m/Y') }}</small></span><form method="post" action="{{ route('admin.delegations.destroy',$delegation) }}">@csrf @method('DELETE')<button class="text-action">Encerrar</button></form></div>@empty<p class="empty compact-empty">Nenhuma delegação vigente ou futura.</p>@endforelse</div>
                </article>
                <article class="card admin-card directory-callout"><div><p class="eyebrow">RASTREABILIDADE</p><h2>Auditoria administrativa</h2><p>Consulte aprovações, recusas e alterações de usuários, equipes e calendário.</p></div><a class="primary button-link" href="{{ route('admin.audits') }}">Abrir auditoria →</a></article>
            </div>
        @endif
    </section>
</main>
@endsection

@push('scripts')<script src="{{ asset('assets/manager.js') }}?v={{ filemtime(public_path('assets/manager.js')) }}" defer></script>@endpush
