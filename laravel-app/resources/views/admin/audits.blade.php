@extends('layouts.app')
@section('title','Auditoria — MixHome')
@section('content')
<main><section class="workspace manager-workspace"><div class="manager-head"><div><p class="eyebrow">SEGURANÇA</p><h1>Trilha de <em>auditoria.</em></h1><p>Registro imutável das ações relevantes do sistema.</p></div><a class="button-link primary" href="{{ route('manager.dashboard') }}">Voltar ao painel</a></div>
<article class="card report"><div class="table-wrap"><table><thead><tr><th>DATA</th><th>RESPONSÁVEL</th><th>EVENTO</th><th>REGISTRO</th><th>IP</th></tr></thead><tbody>
@forelse($logs as $log)<tr><td>{{ $log->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td><td>{{ $log->actor?->name ?? 'Sistema' }}</td><td><b>{{ $log->event }}</b></td><td>{{ class_basename($log->auditable_type ?? '') }} #{{ $log->auditable_id }}</td><td>{{ $log->ip_address ?: '—' }}</td></tr>@empty<tr><td colspan="5" class="empty">Nenhum evento registrado.</td></tr>@endforelse
</tbody></table></div><div class="directory-pagination">{{ $logs->links() }}</div></article></section></main>
@endsection
