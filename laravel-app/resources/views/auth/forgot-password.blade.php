@extends('layouts.app')
@section('title','Recuperar senha — MixHome')
@section('bodyClass','unified-login')
@section('content')
<main class="login-shell password-shell">
    <section class="login-copy"><img class="institutional-logo" src="{{ asset('assets/mix-fiscal-logo.png') }}" alt="Mix Fiscal"><p class="eyebrow">RECUPERAÇÃO DE ACESSO</p><h1>Volte ao trabalho<br><em>com segurança.</em></h1><p>O link de redefinição expira em 60 minutos e pode ser utilizado apenas uma vez.</p></section>
    <section class="card unified-card"><div class="login-heading"><span class="role-icon">↺</span><div><p class="eyebrow">ESQUECI MINHA SENHA</p><h2>Recuperar acesso</h2></div></div><p class="form-intro">Informe seu e-mail corporativo. Se houver uma conta ativa, enviaremos as instruções.</p><form method="post" action="{{ route('password.email') }}" class="unified-form">@csrf<label>E-mail corporativo<input name="email" type="email" value="{{ old('email') }}" autocomplete="email" autofocus required></label><button class="primary">Enviar link de recuperação <span>→</span></button><a class="auth-help" href="{{ route('login') }}">← Voltar ao login</a></form></section>
</main>
@endsection
