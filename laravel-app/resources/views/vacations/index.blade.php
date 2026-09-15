@extends('layouts.app')
@section('title','Minhas férias — MixHome')
@section('content')
<main><section class="workspace vacation-workspace">
  <div class="manager-head"><div><p class="eyebrow">PLANEJAMENTO DE FÉRIAS</p><h1>Organize seu <em>período de descanso.</em></h1><p>Informe o intervalo; depois do envio, a solicitação fica disponível apenas para acompanhamento.</p></div></div>
  <div class="vacation-balance-grid">
    @forelse($usableEntitlements as $entitlement)
      <article class="card vacation-balance-card">
        <span>SEU SALDO DE FÉRIAS</span>
        <strong>{{ $entitlement->availableDays() }} <small>dias disponíveis</small></strong>
        <p>Gerado pelo período trabalhado de {{ $entitlement->acquisition_starts_on->format('d/m/Y') }} a {{ $entitlement->acquisition_ends_on->format('d/m/Y') }}.</p>
        <div><span>{{ $entitlement->approvedDays() }} utilizados</span><span>{{ $entitlement->reservedDays() }} reservados</span><span>{{ $entitlement->totalDays() }} concedidos</span></div>
      </article>
    @empty
      @unless($accrualPeriod)
      <article class="card vacation-balance-card is-empty"><strong>Sem saldo disponível</strong><p>Confirme sua data de admissão com o responsável pelo cadastro de usuários.</p></article>
      @endunless
    @endforelse
    @if($accrualPeriod)
      <article class="card vacation-balance-card is-accruing">
        <span>PRÓXIMO SALDO</span>
        <strong>Em formação</strong>
        <p>Período trabalhado de {{ $accrualPeriod['starts_on']->format('d/m/Y') }} a {{ $accrualPeriod['ends_on']->format('d/m/Y') }}.</p>
        <div><span>30 dias previstos</span><span>Disponível a partir de {{ $accrualPeriod['available_on']->format('d/m/Y') }}</span></div>
      </article>
    @endif
  </div>
  <div class="vacation-layout">
    <article class="card vacation-form-card"><div class="title"><span>01</span><h2>Nova solicitação</h2></div><form method="post" action="{{ route('vacations.store') }}" class="vacation-form">@csrf
      @if($usableEntitlements->count()===1)<input type="hidden" name="vacation_entitlement_id" value="{{ $usableEntitlements->first()->id }}"><p class="auto-balance-note">O saldo de <b>{{ $usableEntitlements->first()->availableDays() }} dias</b> será usado automaticamente.</p>
      @elseif($usableEntitlements->count()>1)<label class="vacation-balance-choice">Saldo que deseja utilizar<select name="vacation_entitlement_id" required><option value="">Selecionar saldo</option>@foreach($usableEntitlements as $entitlement)<option value="{{ $entitlement->id }}" @selected(old('vacation_entitlement_id')==$entitlement->id)>{{ $entitlement->availableDays() }} dias disponíveis — adquirido até {{ $entitlement->acquisition_ends_on->format('d/m/Y') }}</option>@endforeach</select></label>@endif
      <label>Primeiro dia das férias<input type="date" name="starts_on" min="{{ today()->format('Y-m-d') }}" value="{{ old('starts_on') }}" required></label><span aria-hidden="true">→</span><label>Último dia das férias<input type="date" name="ends_on" min="{{ today()->format('Y-m-d') }}" value="{{ old('ends_on') }}" required></label><button class="primary" @disabled($usableEntitlements->isEmpty())>Enviar para aprovação →</button></form><p class="readonly-note">A contagem inclui o primeiro e o último dia, além de fins de semana e feriados. Enquanto aguarda aprovação, os dias ficam reservados.</p></article>
    <article class="card"><div class="history-head"><div><p class="eyebrow">HISTÓRICO</p><h2>Períodos solicitados</h2></div><div class="history-total"><strong>{{ $vacations->total() }}</strong><span>REGISTROS</span></div></div>
      <div class="vacation-list">@forelse($vacations as $vacation)<div class="vacation-item status-{{ $vacation->status }}"><time><strong>{{ $vacation->starts_on->format('d/m/Y') }}</strong><span>até</span><strong>{{ $vacation->ends_on->format('d/m/Y') }}</strong></time><span>{{ $vacation->days() }} dias corridos</span><span class="request-badge">{{ ['pending'=>'Pendente','approved'=>'Aprovada','rejected'=>'Recusada','cancelled'=>'Cancelada'][$vacation->status] }}</span>@if($vacation->entitlement)<small>Aquisitivo: {{ $vacation->entitlement->acquisition_starts_on->format('d/m/Y') }} a {{ $vacation->entitlement->acquisition_ends_on->format('d/m/Y') }}</small>@endif @if($vacation->review_note)<small>{{ $vacation->review_note }}</small>@endif @if($vacation->cancel_note)<small>Cancelamento: {{ $vacation->cancel_note }}</small>@endif</div>@empty<p class="empty">Nenhuma solicitação de férias registrada.</p>@endforelse</div>{{ $vacations->links() }}
    </article>
  </div>
</section></main>
@endsection
