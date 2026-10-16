<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar Email</title>
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
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
        .verify-button {
            display: inline-block;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            text-decoration: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            transition: transform 0.2s;
        }
        .verify-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
        }
        .info-box {
            background-color: #f0f9ff;
            border: 1px solid #0ea5e9;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
            color: #0c4a6e;
        }
        .footer {
            background-color: #f9fafb;
            padding: 25px 30px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #6b7280;
            font-size: 14px;
        }
        .benefits {
            background-color: #f0fdf4;
            border-left: 4px solid #22c55e;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 6px 6px 0;
        }
        .benefits h3 {
            margin-top: 0;
            color: #166534;
            font-size: 16px;
        }
        .benefits ul {
            margin: 10px 0;
            padding-left: 20px;
            color: #166534;
        }
        .benefits li {
            margin: 5px 0;
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
            <h1>✉️ {{ config('app.name') }}</h1>
        </div>

        <div class="content">
            <div class="greeting">
                ¡Hola {{ $user->name }}! 👋
            </div>

            <div class="message">
                ¡Gracias por registrarte en <strong>{{ config('app.name') }}</strong>! Estamos emocionados de tenerte con nosotros.
            </div>

            <div class="message">
                Para completar tu registro y activar tu cuenta, necesitamos verificar tu dirección de email.
                Haz clic en el botón de abajo para confirmar tu email:
            </div>

            <div class="button-container">
                <a href="{{ $verificationUrl }}" class="verify-button">
                    ✅ Verificar mi Email
                </a>
            </div>

            <div class="benefits">
                <h3>🎉 ¿Por qué verificar tu email?</h3>
                <ul>
                    <li><strong>Seguridad:</strong> Protege tu cuenta de accesos no autorizados</li>
                    <li><strong>Recuperación:</strong> Podrás restablecer tu contraseña si la olvidas</li>
                    <li><strong>Notificaciones:</strong> Recibe actualizaciones importantes</li>
                    <li><strong>Acceso completo:</strong> Desbloquea todas las funcionalidades</li>
                </ul>
            </div>

            <div class="info-box">
                <strong>🕒 Nota Importante:</strong><br>
                Este enlace de verificación <strong>expirará en 24 horas</strong>. Si no verificas tu email dentro de este tiempo, podrás solicitar un nuevo enlace de verificación.
            </div>

            <div class="message">
                Si tienes problemas para hacer clic en el botón, copia y pega esta URL en tu navegador:
            </div>

            <div style="word-break: break-all; background-color: #f3f4f6; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px; margin: 10px 0;">
                {{ $verificationUrl }}
            </div>

            <div class="message" style="margin-top: 30px; color: #6b7280; font-size: 14px;">
                Si no te registraste en {{ config('app.name') }}, puedes ignorar este email de forma segura.
            </div>
        </div>

        <div class="footer">
            <p>
                ¡Bienvenido/a a <strong>{{ config('app.name') }}</strong>!<br>
                Este es un email automático, por favor no respondas a este mensaje.
            </p>
            <p style="margin-top: 15px; font-size: 12px;">
                © {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.
            </p>
        </div>
    </div>
</body>
</html>
