<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8fafc;
            margin: 0;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 300;
        }
        .content {
            padding: 40px 30px;
            color: #374151;
        }
        .greeting {
            font-size: 20px;
            color: #1f2937;
            margin-bottom: 20px;
        }
        .message {
            font-size: 16px;
            margin-bottom: 20px;
        }
        .button-container {
            text-align: center;
            margin: 30px 0;
        }
        .reset-button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            transition: transform 0.2s;
        }
        .reset-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        .warning {
            background-color: #fef3cd;
            border: 1px solid #faebcc;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
            color: #8a6d3b;
        }
        .footer {
            background-color: #f9fafb;
            padding: 25px 30px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #6b7280;
            font-size: 14px;
        }
        .security-note {
            background-color: #e0f2fe;
            border-left: 4px solid #0288d1;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 6px 6px 0;
        }
        @media (max-width: 600px) {
            .container {
                margin: 10px;
                border-radius: 8px;
            }
            .header, .content, .footer {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 {{ config('app.name') }}</h1>
        </div>

        <div class="content">
            <div class="greeting">
                ¡Hola {{ $user->name }}! 👋
            </div>

            <div class="message">
                Recibimos una solicitud para restablecer la contraseña de tu cuenta en <strong>{{ config('app.name') }}</strong>.
            </div>

            <div class="message">
                Si fuiste tú quien solicitó este cambio, haz clic en el botón de abajo para crear una nueva contraseña:
            </div>

            <div class="button-container">
                <a href="{{ $resetUrl }}" class="reset-button">
                    🔄 Restablecer mi Contraseña
                </a>
            </div>

            <div class="security-note">
                <strong>⏰ Importante:</strong> Por tu seguridad, este enlace expirará en <strong>60 minutos</strong>.
            </div>

            <div class="warning">
                <strong>🛡️ Nota de Seguridad:</strong><br>
                Si no solicitaste este restablecimiento de contraseña, puedes ignorar este email de forma segura. Tu contraseña actual permanecerá sin cambios.
            </div>

            <div class="message">
                Si tienes problemas para hacer clic en el botón, copia y pega esta URL en tu navegador:
            </div>

            <div style="word-break: break-all; background-color: #f3f4f6; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px; margin: 10px 0;">
                {{ $resetUrl }}
            </div>
        </div>

        <div class="footer">
            <p>
                Este es un email automático de <strong>{{ config('app.name') }}</strong><br>
                Por favor no respondas a este mensaje.
            </p>
            <p style="margin-top: 15px; font-size: 12px;">
                © {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.
            </p>
        </div>
    </div>
</body>
</html>
