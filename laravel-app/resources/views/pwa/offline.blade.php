<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#071424">
    <title>Sem conexão — MixHome</title>
    <style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#071424;color:#f4f8fb;font-family:Inter,Arial,sans-serif}.offline-card{width:min(420px,calc(100% - 40px));padding:36px;border:1px solid #40566d;border-radius:16px;background:#13263d;text-align:center;box-shadow:0 24px 70px rgba(0,0,0,.28)}img{width:120px;height:auto;margin-bottom:24px}h1{margin:0 0 12px;font-size:28px}p{margin:0 0 26px;color:#c5d0da;line-height:1.55}button{min-height:46px;padding:0 22px;border:0;border-radius:8px;background:#51ca70;color:#071424;font-weight:800;cursor:pointer}</style>
</head>
<body>
<main class="offline-card">
    <img src="{{ asset('assets/mix-fiscal-logo.svg') }}" alt="Mix Fiscal">
    <h1>Você está sem conexão</h1>
    <p>Por segurança, o MixHome não guarda solicitações ou dados da sua conta para uso offline.</p>
    <button type="button" onclick="location.reload()">Tentar novamente</button>
</main>
</body>
</html>
