<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Invitation — Stage Tracker</title>
</head>
<body style="font-family: system-ui, sans-serif; color: #1f2933; line-height: 1.6;">
    <p>Bonjour,</p>

    <p>
        <strong>{{ $inviterName }}</strong> vous invite à rejoindre l'espace
        de travail « <strong>{{ $workspaceName }}</strong> » avec le rôle
        <strong>{{ $roleLabel }}</strong>.
    </p>

    <p>
        <a href="{{ $url }}">Accepter l'invitation</a>
    </p>

    <p>
        Ce lien est personnel et expire le {{ $expiresAt }}.
        Si vous n'êtes pas à l'origine de cette demande, ignorez cet e-mail.
    </p>

    <p>— Stage Tracker</p>
</body>
</html>
