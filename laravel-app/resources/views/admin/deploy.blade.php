@extends('layouts.app')
@section('title', 'Aprovação de deploy — '.config('brand.name'))
@section('bodyClass','manager-page')
@section('content')
<main><section class="workspace manager-workspace">
    <div class="manager-head"><div><p class="eyebrow">PUBLICAÇÃO CONTROLADA</p><h1>Aprovar <em>pacote.</em></h1><p>A autorização é específica para o pacote recebido e expira em poucos minutos.</p></div><a class="button-link primary" href="{{ route('admin.operations') }}">Voltar à operação</a></div>
    @include('manager._navigation')
    <article class="card ops-checklist">
        <h2>{{ $environment === 'producao' ? 'Produção' : ($environment === 'homologacao' ? 'Homologação' : 'Não configurado') }}</h2>
        @if(! $environment)
            <p>A aprovação web ainda não foi configurada neste ambiente. O procedimento por SSH continua disponível.</p>
        @elseif(empty($pending))
            <p>Nenhum pacote aguardando aprovação. Inicie o deploy manual no GitHub e atualize esta página.</p>
        @else
            <p>Confira no job do GitHub se o ambiente e o SHA-256 abaixo correspondem ao pacote esperado. Confirme somente se você autorizou essa publicação.</p>
            @foreach($pending as $item)
                <div class="deploy-approval-item">
                    <p><strong>SHA-256</strong><br><code>{{ $item['digest'] }}</code></p>
                    <p>Expira às {{ \Carbon\Carbon::createFromTimestamp($item['expires'])->timezone(config('app.timezone'))->format('H:i:s') }}</p>
                    <form method="post" action="{{ route('admin.deploy.approve', $item['digest']) }}">
                        @csrf
                        @if(session('auth_provider') === 'oidc')
                            <p class="form-hint">Sua autenticação corporativa recente confirmará esta aprovação.</p>
                        @else
                            <label for="password-{{ $loop->index }}">Confirme sua senha</label>
                            <input id="password-{{ $loop->index }}" name="password" type="password" required autocomplete="current-password">
                        @endif
                        <button type="submit" class="button-link primary">Aprovar este pacote</button>
                    </form>
                </div>
            @endforeach
            @error('password')<p role="alert">{{ $message }}</p>@enderror
        @endif
    </article>
</section></main>
@endsection
