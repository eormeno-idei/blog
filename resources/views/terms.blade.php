<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Términos y Condiciones</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8fafc;
            margin: 0;
            padding: 20px;
            line-height: 1.6;
        }

        .container {
            max-width: 1024px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #0c835b 0%, #036143 100%);
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

        @media (max-width: 600px) {
            .container {
                margin: 10px;
                border-radius: 8px;
            }

            .terms-container {
                max-width: 56rem;
                /* equivalente a max-w-4xl */
                margin-left: auto;
                /* equivalente a mx-auto */
                margin-right: auto;
                /* equivalente a mx-auto */
                padding-top: 2.5rem;
                /* equivalente a py-10 */
                padding-bottom: 2.5rem;
                /* equivalente a py-10 */
            }

            .header,
            .content {
                padding: 20px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>⚖️ {{ config('app.name') }}</h1>
        </div>

        <div class="content">

            <div class="terms-container">
                {!! $html !!}
            </div>

        </div>

    </div>
</body>

</html>
