<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Convite</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f7f7f8; color:#111; margin:0; padding:24px;">
    <div style="max-width:560px; margin:0 auto; background:#fff; padding:32px; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,.08);">
        <h1 style="font-size:20px; margin:0 0 16px;">Você foi convidado para {{ $tenantName }}</h1>

        <p>Olá,</p>

        @if ($inviterName)
            <p><strong>{{ $inviterName }}</strong> convidou você para participar da equipe <strong>{{ $tenantName }}</strong> no {{ config('app.name') }} com o perfil <strong>{{ $roleLabel }}</strong>.</p>
        @else
            <p>Você foi convidado para participar da equipe <strong>{{ $tenantName }}</strong> no {{ config('app.name') }} com o perfil <strong>{{ $roleLabel }}</strong>.</p>
        @endif

        <p style="margin:24px 0;">
            <a href="{{ $acceptUrl }}"
               style="display:inline-block; background:#111827; color:#fff; padding:12px 20px; border-radius:8px; text-decoration:none; font-weight:600;">
                Aceitar convite
            </a>
        </p>

        <p style="font-size:13px; color:#555;">
            Ou copie e cole este link no seu navegador:<br>
            <a href="{{ $acceptUrl }}">{{ $acceptUrl }}</a>
        </p>

        <p style="font-size:13px; color:#555;">
            Este convite expira em {{ $expiresAt?->format('d/m/Y H:i') }}.
        </p>

        <hr style="border:none; border-top:1px solid #eee; margin:24px 0;">
        <p style="font-size:12px; color:#888;">Se você não esperava este convite, pode ignorar este e-mail com segurança.</p>
    </div>
</body>
</html>
