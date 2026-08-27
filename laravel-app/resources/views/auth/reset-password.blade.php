@extends('layouts.app')
@section('title','Nova senha — MixHome')
@section('bodyClass','unified-login')
@section('content')
<main class="login-shell password-shell">
    <section class="login-copy"><img class="institutional-logo" src="{{ asset('assets/mix-fiscal-logo.png') }}" alt="Mix Fiscal"><p class="eyebrow">PROTEÇÃO DA CONTA</p><h1>Crie uma nova<br><em>senha segura.</em></h1><p>Utilize uma senha exclusiva, com pelo menos oito caracteres.</p></section>
    <section class="card unified-card"><div class="login-heading"><span class="role-icon">✓</span><div><p class="eyebrow">REDEFINIÇÃO</p><h2>Definir nova senha</h2></div></div><form method="post" action="{{ route('password.update') }}" class="unified-form">@csrf<input type="hidden" name="token" value="{{ $token }}"><label>E-mail corporativo<input name="email" type="email" value="{{ old('email',$email) }}" required></label><label>Nova senha<input name="password" type="password" minlength="8" autocomplete="new-password" required></label><label>Confirmar nova senha<input name="password_confirmation" type="password" minlength="8" autocomplete="new-password" required></label><button class="primary">Salvar nova senha <span>→</span></button></form></section>
</main>
@endsection
