<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') | Intranet de TI</title>
    <style>
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: #f3f4f6; color: #1f2937; }
        .box { max-width: 480px; margin: 12vh auto; padding: 40px 32px; background: #ffffff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0, 0, 0, .1); text-align: center; }
        .box img { height: 48px; }
        .code { margin-top: 24px; font-size: 13px; color: #6b7280; }
        h1 { margin: 8px 0 12px; font-size: 22px; }
        p { margin: 0 0 24px; line-height: 1.5; color: #4b5563; }
        .btn { display: inline-block; padding: 10px 18px; background: #1f2937; color: #ffffff; border-radius: 6px; text-decoration: none; font-size: 14px; }
    </style>
</head>
<body>
    <div class="box">
        <img src="{{ asset('images/logo.png') }}" alt="Construtora Exemplo">
        <div class="code">Erro @yield('code')</div>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <a class="btn" href="{{ url('/') }}">Voltar para o início</a>
    </div>
</body>
</html>