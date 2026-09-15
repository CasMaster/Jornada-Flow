@extends('layouts.app')
@section('title','Minhas férias — MixHome')
@section('content')
<main><section class="workspace vacation-workspace">
  <div class="manager-head"><div><p class="eyebrow">PLANEJAMENTO DE FÉRIAS</p><h1>Organize seu <em>período de descanso.</em></h1><p>Informe o intervalo; depois do envio, a solicitação fica disponível apenas para acompanhamento.</p></div></div>
  <div class="vacation-layout">
    <article class="card vacation-form-card"><div class="title"><span>01</span><h2>Nova solicitação</h2></div><form method="post" action="{{ route('vacations.store') }}" class="vacation-form">@csrf<label>Início<input type="date" name="starts_on" min="{{ today()->format('Y-m-d') }}" value="{{ old('starts_on') }}" required></label><span aria-hidden="true">→</span><label>Término<input type="date" name="ends_on" min="{{ today()->format('Y-m-d') }}" value="{{ old('ends_on') }}" required></label><button class="primary">Enviar para aprovação →</button></form><p class="readonly-note">Solicitações enviadas não podem ser editadas. Correções e cancelamentos são realizados pelo Super Admin e ficam auditados.</p></article>
    <article class="card"><div class="history-head"><div><p class="eyebrow">HISTÓRICO</p><h2>Períodos solicitados</h2></div><div class="history-total"><strong>{{ $vacations->total() }}</strong><span>REGISTROS</span></div></div>
      <div class="vacation-list">@forelse($vacations as $vacation)<div class="vacation-item status-{{ $vacation->status }}"><time><strong>{{ $vacation->starts_on->format('d/m/Y') }}</strong><span>até</span><strong>{{ $vacation->ends_on->format('d/m/Y') }}</strong></time><span>{{ $vacation->starts_on->diffInDays($vacation->ends_on)+1 }} dias corridos</span><span class="request-badge">{{ ['pending'=>'Pendente','approved'=>'Aprovada','rejected'=>'Recusada','cancelled'=>'Cancelada'][$vacation->status] }}</span>@if($vacation->review_note)<small>{{ $vacation->review_note }}</small>@endif @if($vacation->cancel_note)<small>Cancelamento: {{ $vacation->cancel_note }}</small>@endif</div>@empty<p class="empty">Nenhuma solicitação de férias registrada.</p>@endforelse</div>{{ $vacations->links() }}
    </article>
  </div>
</section></main>
@endsection
