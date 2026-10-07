@extends('layouts.app')
@section('title', 'Entrar — '.config('brand.name')) @section('bodyClass','unified-login')
@section('content')
<main class="login-shell"><section class="login-copy"><img class="institutional-logo" src="{{ asset(config('brand.login_logo')) }}" alt="{{ config('brand.company') }}"><p class="eyebrow">CONTROLE DE PRESENÇA</p><h1>Seu trabalho.<br><em>Um só acesso.</em></h1><p>Use sua conta corporativa. {{ config('brand.name') }} direciona você automaticamente para os recursos disponíveis no seu perfil.</p><div class="login-note"><b>{{ config('brand.initials') }}</b><span>As contas de acesso são administradas pelo responsável do sistema.</span></div></section>
<section class="card unified-card">
@if($errors->any())<div class="auth-alert" role="alert" aria-live="assertive"><span aria-hidden="true">!</span><p><b>Não foi possível entrar</b><small>{{ $errors->first() }}</small></p></div>@endif
<div class="login-heading"><span class="role-icon">H</span><div><p class="eyebrow">ACESSO {{ mb_strtoupper(config('brand.name')) }}</p><h2>Entre na sua conta</h2></div></div>
<p class="form-intro">Colaboradores, gestores e administradores utilizam o mesmo acesso.</p>
<form method="post" action="{{ route('login') }}" class="unified-form">@csrf<label>E-mail corporativo<input name="email" type="email" value="{{ old('email') }}" autocomplete="email" autofocus required></label><label>Senha<input name="password" type="password" autocomplete="current-password" required></label><button class="primary">Entrar <span>→</span></button>@if(config('auth.password_recovery_enabled'))<div class="login-actions"><a class="auth-help" href="{{ route('password.request') }}">Esqueci minha senha</a></div>@endif</form>
</section></main>
@endsection
