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
      @if($requestableEntitlements->count()===1)<input id="vacation-entitlement" type="hidden" name="vacation_entitlement_id" value="{{ $requestableEntitlements->first()->id }}" data-available-from="{{ $requestableEntitlements->first()->acquisition_ends_on->copy()->addDay()->format('Y-m-d') }}" data-expires-on="{{ $requestableEntitlements->first()->expires_on?->format('Y-m-d') }}"><div class="auto-balance-note"><b>{{ $requestableEntitlements->first()->availableDays() }} dias restantes</b><span>Este saldo será usado automaticamente.</span>@if($requestableEntitlements->first()->reservedDays()>0)<small>{{ $requestableEntitlements->first()->reservedDays() }} dias já estão reservados em solicitação pendente.</small>@endif</div>
      @elseif($requestableEntitlements->count()>1)<label class="vacation-balance-choice">Período que deseja utilizar<select id="vacation-entitlement" name="vacation_entitlement_id" required><option value="">Selecionar período</option>@foreach($requestableEntitlements as $entitlement)<option value="{{ $entitlement->id }}" data-available-from="{{ $entitlement->acquisition_ends_on->copy()->addDay()->format('Y-m-d') }}" data-expires-on="{{ $entitlement->expires_on?->format('Y-m-d') }}" @selected(old('vacation_entitlement_id')==$entitlement->id)>{{ $entitlement->availableDays() }} dias — férias a partir de {{ $entitlement->acquisition_ends_on->copy()->addDay()->format('d/m/Y') }}</option>@endforeach</select></label>@endif
      <p id="vacation-availability" class="vacation-availability" aria-live="polite"></p>
      <input id="vacation-starts-on" type="hidden" name="starts_on" data-today="{{ today()->format('Y-m-d') }}" value="{{ old('starts_on') }}"><input id="vacation-ends-on" type="hidden" name="ends_on" value="{{ old('ends_on') }}">
      <div class="vacation-date-field"><span>Primeiro dia das férias</span><button id="vacation-start-trigger" class="vacation-date-trigger" type="button" aria-haspopup="dialog" aria-controls="vacation-calendar" @disabled($requestableEntitlements->isEmpty())><span>Escolher data</span><b aria-hidden="true">▣</b></button></div><span class="vacation-date-connector" aria-hidden="true">até</span><div class="vacation-date-field"><span>Último dia das férias</span><button id="vacation-end-trigger" class="vacation-date-trigger" type="button" aria-haspopup="dialog" aria-controls="vacation-calendar" @disabled($requestableEntitlements->isEmpty())><span>Escolher data</span><b aria-hidden="true">▣</b></button></div>
      <section id="vacation-calendar" class="mixhome-calendar" role="dialog" aria-modal="false" aria-label="Escolher período de férias" hidden><header><button type="button" data-calendar-prev aria-label="Mês anterior">←</button><strong data-calendar-title></strong><button type="button" data-calendar-next aria-label="Próximo mês">→</button></header><div class="mixhome-calendar-week" aria-hidden="true"><span>D</span><span>S</span><span>T</span><span>Q</span><span>Q</span><span>S</span><span>S</span></div><div class="mixhome-calendar-days" data-calendar-days></div><footer><p data-calendar-instruction>Escolha o primeiro dia.</p><button type="button" data-calendar-clear>Limpar</button><button type="button" data-calendar-close>Concluir</button></footer></section>
      <button class="primary" @disabled($requestableEntitlements->isEmpty())>Enviar para aprovação →</button></form><p class="readonly-note">A contagem inclui o primeiro e o último dia, além de fins de semana e feriados. Enquanto aguarda aprovação, os dias ficam reservados.</p></article>
    <article class="card"><div class="history-head"><div><p class="eyebrow">HISTÓRICO</p><h2>Períodos solicitados</h2></div><div class="history-total"><strong>{{ $vacations->total() }}</strong><span>REGISTROS</span></div></div>
      <div class="vacation-list">@forelse($vacations as $vacation)<div class="vacation-item status-{{ $vacation->status }}"><time><strong>{{ $vacation->starts_on->format('d/m/Y') }}</strong><span>até</span><strong>{{ $vacation->ends_on->format('d/m/Y') }}</strong></time><span>{{ $vacation->days() }} dias corridos</span><span class="request-badge">{{ ['pending'=>'Pendente','approved'=>'Aprovada','rejected'=>'Recusada','cancelled'=>'Cancelada'][$vacation->status] }}</span>@if($vacation->entitlement)<small>Aquisitivo: {{ $vacation->entitlement->acquisition_starts_on->format('d/m/Y') }} a {{ $vacation->entitlement->acquisition_ends_on->format('d/m/Y') }}</small>@endif @if($vacation->review_note)<small>{{ $vacation->review_note }}</small>@endif @if($vacation->cancel_note)<small>Cancelamento: {{ $vacation->cancel_note }}</small>@endif</div>@empty<p class="empty">Nenhuma solicitação de férias registrada.</p>@endforelse</div>{{ $vacations->links() }}
    </article>
  </div>
</section></main>
@endsection
@push('scripts')<script src="{{ asset('assets/vacations.js') }}?v={{ filemtime(public_path('assets/vacations.js')) }}" defer></script>@endpush
