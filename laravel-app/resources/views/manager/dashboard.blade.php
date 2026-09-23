@extends('layouts.app')
@section('title','Gestor — MixHome')
@section('bodyClass','manager-page')

@section('content')
<main>
    <section class="workspace manager-workspace">
        <div class="manager-head">
            <div>
                <p class="eyebrow">PAINEL DO GESTOR</p>
                <h1>Gestão da <em>jornada híbrida.</em></h1>
                <p>Ciclo {{ $start->format('d/m/Y') }} até {{ $end->format('d/m/Y') }}.</p>
            </div>
            <form class="export-menu" method="get" action="{{ route('manager.export') }}">@foreach(request()->except(['format','export_status']) as $key=>$value)@if(is_array($value))@foreach($value as $item)<input type="hidden" name="{{ $key }}[]" value="{{ $item }}">@endforeach @else<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach<label>Formato<select name="format"><option value="xlsx">Excel matricial</option><option value="csv">CSV detalhado</option></select></label><label>Status<select name="export_status"><option value="approved">Aprovadas</option><option value="pending">Pendentes</option><option value="rejected">Recusadas</option><option value="all">Todos</option></select></label><button class="primary">Exportar ↓</button></form>
        </div>

        @include('manager._navigation')

        <article class="card filters-card" id="registros">
            <div class="report-head filter-priority-head">
                <div><p class="eyebrow">PESQUISA E FILTROS</p><h2>Localizar solicitações</h2><p class="filter-description">Combine os campos abaixo para encontrar rapidamente os registros que precisam de análise.</p></div>
            </div>
            <form method="get" class="filters request-filters" data-manager-filters>
                <label class="request-search">Nome ou e-mail<input name="q" value="{{ request('q') }}" placeholder="Pesquisar colaborador"></label>
                <label>Ciclo 20–19<select name="cycle">@foreach($cycles as $cycle)<option value="{{ $cycle['value'] }}" @selected(request('cycle',$cycles[0]['value'])===$cycle['value'])>{{ $cycle['label'] }}</option>@endforeach</select></label>
                <label>Equipes<select name="teams[]" multiple class="click-multi" data-placeholder="Todas as equipes permitidas" data-singular="equipe selecionada" data-plural="equipes selecionadas" data-search-placeholder="Buscar equipe..." data-empty="Nenhuma equipe encontrada">@foreach($teams as $team)<option value="{{ $team->name }}" @selected(in_array($team->name,(array)request('teams',request('team') ? [request('team')] : [])))>{{ $team->name }}</option>@endforeach</select></label>
                <label>Status<select name="status"><option value="">Todos</option>@foreach(['pending'=>'Pendentes','approved'=>'Aprovadas','rejected'=>'Recusadas'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></label>
                <label>Modalidade<select name="work_mode"><option value="">Todas</option><option value="home_office" @selected(request('work_mode')==='home_office')>Home office</option><option value="onsite" @selected(request('work_mode')==='onsite')>Presencial</option></select></label>
                <label>Colaboradores<select name="employees[]" multiple class="click-multi" data-placeholder="Selecionar colaboradores" data-singular="colaborador selecionado" data-plural="colaboradores selecionados" data-search-placeholder="Buscar colaborador..." data-empty="Nenhum colaborador encontrado">@foreach($employees as $employee)<option value="{{ $employee->email }}" @selected(in_array($employee->email,(array)request('employees',[])))>{{ $employee->name }} · {{ $employee->team }}</option>@endforeach</select></label>
                <label>Ordenar por<select name="sort"><option value="date_asc" @selected(request('sort')==='date_asc')>Data crescente</option><option value="date_desc" @selected(request('sort')==='date_desc')>Data decrescente</option><option value="created_desc" @selected(request('sort')==='created_desc')>Envio mais recente</option><option value="status" @selected(request('sort')==='status')>Status</option></select></label>
                <label>Por página<select name="per_page">@foreach([25,50,100] as $size)<option value="{{ $size }}" @selected((int)request('per_page',25)===$size)>{{ $size }}</option>@endforeach</select></label>
                <div class="filter-actions">
                    <button class="primary" type="submit">Aplicar filtros</button>
                    <button class="secondary" type="button" data-save-filters>Salvar preferência</button>
                    @if(request()->hasAny(['q','cycle','team','teams','status','work_mode','employees']))<a class="clear-filter" href="{{ route('manager.dashboard') }}">Limpar</a>@endif
                </div>
            </form>
        </article>

        <div class="stats stats-three compact-stats">
            <div><span>REGISTROS NO FILTRO</span><strong>{{ $metrics->total ?? 0 }}</strong></div>
            <div class="pending-highlight"><span>AGUARDANDO SUA AÇÃO</span><strong>{{ $metrics->pending ?? 0 }}</strong></div>
            <div><span>COLABORADORES</span><strong>{{ $metrics->collaborators ?? 0 }}</strong></div>
        </div>

        <section class="manager-insights" aria-label="Resumo gerencial"><article class="card priority-card"><div class="report-head"><div><p class="eyebrow">PRIORIDADE</p><h2>Pendências mais antigas</h2></div><a href="{{ route('manager.dashboard',[...request()->query(),'status'=>'pending']) }}">Ver todas</a></div><div class="priority-list">@forelse($priorityRequests as $record)<div><span><b>{{ $record->user->name }}</b><small>{{ $record->user->team }} · {{ $record->work_date->format('d/m/Y') }}</small></span><strong>{{ $record->created_at->diffForHumans() }}</strong></div>@empty<p class="empty compact-empty">Nenhuma pendência no filtro atual.</p>@endforelse</div></article><article class="card executive-card"><div class="report-head"><div><p class="eyebrow">VISÃO EXECUTIVA</p><h2>Distribuição do ciclo</h2></div></div>@php($summaryTotal=max(1,(int)$statusSummary->sum()))<div class="status-bars">@foreach(['pending'=>'Pendentes','approved'=>'Aprovadas','rejected'=>'Recusadas'] as $status=>$label)@php($amount=(int)($statusSummary[$status]??0))<div><span><b>{{ $label }}</b><small>{{ $amount }}</small></span><i><em class="bar-{{ $status }}" style="width:{{ round($amount/$summaryTotal*100) }}%"></em></i></div>@endforeach</div><div class="team-ranking">@foreach($teamSummary as $team)<span><b>{{ $team->team ?: 'Sem equipe' }}</b><small>{{ $team->total }} registros · {{ $team->pending }} pendentes</small></span>@endforeach</div></article></section>

        <details class="card report requests-accordion" @if(request()->hasAny(['cycle','team','teams','status','work_mode','employees'])) open @endif>
            <summary class="requests-accordion-summary">
                <span><span class="eyebrow">SOLICITAÇÕES</span><strong>{{ $records->total() }} resultados</strong></span>
                <span class="requests-accordion-action"><span class="when-closed">Exibir lista</span><span class="when-open">Ocultar lista</span></span>
            </summary>
            <form id="batch-form" method="post" action="{{ route('manager.review.batch') }}" class="batch-toolbar">@csrf<span><b>Ações em lote</b><small>Selecione até 100 solicitações nesta página.</small></span><input name="review_note" maxlength="1000" placeholder="Justificativa opcional para todas"><button name="decision" value="approved" class="approve-action">✓ Aprovar</button><button name="decision" value="rejected" class="reject-action" onclick="return confirm('Recusar e arquivar as solicitações selecionadas?')">× Recusar</button></form>
            <div class="table-wrap compact-requests-table"><table>
                <thead><tr><th><input type="checkbox" data-select-all aria-label="Selecionar todos"></th><th>COLABORADOR</th><th>EQUIPE</th><th>DATA</th><th>STATUS</th><th>ANÁLISE</th></tr></thead>
                <tbody>@forelse($records as $record)
                    <tr class="request-row request-{{ $record->status }} mode-{{ $record->work_mode }}">
                        <td>@if($record->status==='pending')<input type="checkbox" name="requests[]" value="{{ $record->id }}" form="batch-form" class="batch-checkbox" aria-label="Selecionar solicitação de {{ $record->user->name }}">@endif</td>
                        <td><b>{{ $record->user->name }}</b><small>{{ $record->user->email }}</small></td>
                        <td>{{ $record->user->team }}</td><td><b>{{ $record->work_date->format('d/m/Y') }}</b><small>{{ $record->isOnsite() ? 'Presencial' : 'Home office' }}</small></td>
                        <td><span class="request-badge">{{ ['pending'=>'Pendente','approved'=>'Aprovada','rejected'=>'Recusada'][$record->status] }}</span></td>
                        <td class="review-actions">@if($record->status==='pending')<details class="request-menu"><summary>Analisar</summary><form method="post" action="{{ route('manager.review',$record) }}" class="request-menu-panel">@csrf<textarea name="review_note" maxlength="1000" rows="3" placeholder="Justificativa opcional">{{ $record->review_note }}</textarea><button class="approve-action" name="decision" value="approved">✓ Aprovar</button><button class="reject-action" name="decision" value="rejected" onclick="return confirm('Recusar e arquivar esta solicitação?')">× Recusar</button></form></details>@else<small>{{ $record->reviewer?->name ?? '—' }}</small>@endif @if($record->review_note)<small class="review-note">{{ $record->review_note }}</small>@endif</td>
                    </tr>
                @empty<tr><td colspan="6" class="empty">Nenhuma solicitação neste ciclo.</td></tr>@endforelse</tbody>
            </table></div>@include('components.pagination', ['paginator' => $records])
        </details>

    </section>
</main>
@endsection

@push('scripts')<script src="{{ asset('assets/manager.js') }}?v={{ filemtime(public_path('assets/manager.js')) }}" defer></script>@endpush
