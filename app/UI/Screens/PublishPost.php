<?php

namespace App\UI\Screens;

use App\Ai\Agents\PostAgent;
use App\Contracts\PostServiceInterface;
use App\Http\Requests\PostRequest;
use App\Models\User;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\Textarea;
use Idei\Usim\Components\Uploader;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\AspectRatio;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class PublishPost extends Screen
{
    protected Input $post_title;
    protected Textarea $post_content;
    protected Input $ai_response;
    protected Uploader $post_image;

    public function __construct(
        protected PostServiceInterface $postService
    ) {
    }

    public static function authorize(): bool
    {
        return self::requireRole(['publisher', 'admin']);
    }

    public static function getMenuLabel(): string
    {
        return 'Publicar Post';
    }

    public static function getMenuIcon(): ?string
    {
        return '✍️';
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container
            ->plain()
            ->width(Size::full())
            ->maxWidth(Size::px(720))
            ->centerHorizontal()
            ->padding(Spacing::px(24))
            ->gap(Spacing::px(16));

        $header = UI::container('publish_header')
            ->width(Size::full())
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->alignItems(AlignItems::CENTER)
            ->plain()
            ->shadow(false)
            ->padding(Spacing::zero())
            ->gap(Spacing::px(16));

        $header->add(
            UI::label('lbl_publish_title')
                ->text('✍️ Publicar Nuevo Post')
                ->style('h2')
                ->color('primary')
        );

        $header->add(
            UI::button('btn_back_home')
                ->label('← Volver al Inicio')
                ->action('cancel_post')
                ->style('secondary')
        );

        $container->add($header);

        $formCard = UI::container('publish_form_card')
            ->card()
            ->width(Size::full())
            ->layout(LayoutType::VERTICAL)
            ->padding(Spacing::px(24))
            ->gap(Spacing::px(16));

        $this->post_title = UI::input('post_title')
            ->label('Título del Post')
            ->placeholder('Escribe un título atractivo...')
            ->required(true)
            ->width(Size::full());

        $this->post_content = UI::textarea('post_content')
            ->label('Notas rápidas')
            ->placeholder('Escribe algo aquí…')
            ->value(
                <<<EOT
Proyecto de Desarrollo Tecnológico y Social (PDTS)
Universidad Nacional de San Juan
Secretaría de Ciencia, Tecnología e Innovación – Gobierno de San Juan
Convocatoria 2025

1. DENOMINACIÓN DEL PROYECTO
Innovación en Desarrollo de Software: Validación de Arquitectura Backend-Driven UI en la aplicación "+65 Agenda Salud"

2. RESUMEN
El presente proyecto tiene por objeto la validación científica y transferencia al sector productivo de una tecnología de desarrollo de software basada en arquitectura Backend-Driven. Esta herramienta, resultado de un Proyecto de Investigación (PIC) previo del Instituto de Informática, propone centralizar la lógica de interfaz en el servidor para acelerar los tiempos de desarrollo de software. La propuesta intentará validar el framework  en dos fases de ejecución: primero, se utilizará el framework para construir y desplegar "+65 Agenda Salud", una aplicación móvil de autogestión sanitaria para adultos mayores. Segundo, se ejecutará un experimento controlado en dos empresas de Cuyo: Aconcagua Software Factory y Nodos Hub, en las cuales se comparará la productividad de este enfoque frente al desarrollo tradicional, consolidando la transferencia de la tecnología a la industria de software regional.
EOT
            )
            ->plainText()
            ->width(Size::full())
            ->height(Size::px(300))
            ->maxLength(2000)
            ->helpText('Máximo 2000 caracteres. Se guarda al salir del campo (onChange).')
            ->onChange('on_plain_saved');

        $this->ai_response = UI::input('ai_response')
            ->type('hidden');

        $this->post_image = UI::uploader('post_image')
            ->images()
            ->maxFiles(1)
            ->maxSize(5)
            ->aspect(AspectRatio::_16_9)
            ->size(2)
            ->label('Imagen de Portada (opcional)');

        $actionsRow = UI::container('publish_actions_row')
            ->width(Size::full())
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::END)
            ->gap(Spacing::px(12))
            ->plain()
            ->shadow(false)
            ->padding(Spacing::zero());

        $actionsRow->add(
            UI::button('btn_cancel_post')
                ->label('Cancelar')
                ->action('cancel_post')
                ->style('secondary')
        );

        $actionsRow->add(
            UI::button('btn_save_post')
                ->label('Publicar Post')
                ->action('save_post')
                ->style('primary')
        );

        $actionsRow->add(
            UI::button('btn_ask_ai')
                ->label('Preguntar a la IA')
                ->action('ask_ai')
                ->style('secondary')
        );

        $formCard->add($this->post_title);
        $formCard->add($this->post_content);
        $formCard->add($this->ai_response);
        $formCard->add($this->post_image);
        $formCard->add($actionsRow);

        $container->add($formCard);
    }

    public function onAskAI(PostRequest $request): void
    {
        set_time_limit(0); // Aumentar el tiempo de ejecución para procesos largos

        $question = $request->content();
        $answer = new PostAgent()->prompt($question);
        $jsonString = $answer->text;
        $this->ai_response->value($jsonString);

        // 2. Preparar el entorno para Remotion
        $uuid = Str::uuid()->toString();
        $tempDir = storage_path('app/temp');
        File::ensureDirectoryExists($tempDir);

        $jsonPath = $tempDir . "/reel_{$uuid}.json";
        File::put($jsonPath, $jsonString);

        $videoFilename = "preview_{$uuid}.mp4";
        $videoPath = storage_path("app/public/reels/{$videoFilename}");
        File::ensureDirectoryExists(storage_path('app/public/reels'));

        Log::info("Generando video con Remotion: JSON Path: {$jsonPath}, Video Path: {$videoPath}");

        try {
            // 3. Ejecutar renderizado
            $result = Process::path(base_path('video-engine'))
                ->timeout(1200) // Dar tiempo suficiente para la generación asíncrona
                ->run("npx remotion render src/index.ts ReelInstitucional {$videoPath} --props={$jsonPath}");

            Log::info("Resultado del renderizado: " . $result->output());

            File::delete($jsonPath); // Limpieza temporal

            if ($result->successful()) {
                $this->toast('Previsualización generada con éxito.', 'success');
            } else {
                $this->toast('Error al generar el video con IA.', 'error');
            }
        } catch (\Throwable $e) {
            Log::error("Error al ejecutar Remotion: " . $e->getMessage());
            $this->toast('Error al generar el video con IA.', 'error');
        }
    }

    public function onSavePost(PostRequest $request): void
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            $this->toast('Debes iniciar sesión para publicar.', 'error');
            return;
        }

        // Confirmar y persistir la imagen subida usando Uploader::confirm()
        $confirmedImage = $this->post_image->confirm($request->all(), 'images');
        $imageFilename = null;
        if (is_string($confirmedImage) && $confirmedImage !== '') {
            $imageFilename = $confirmedImage;
        } elseif (is_array($confirmedImage) && isset($confirmedImage[0]) && is_string($confirmedImage[0])) {
            $imageFilename = $confirmedImage[0];
        }

        $this->postService->createPost([
            'user_id' => (int) $user->id,
            'title' => $request->title(),
            'content' => $request->content(),
            'image' => $imageFilename,
            'background_color' => "#32637b",
            'text_color' => "#000000",
            'background_image' => null,
            'aspect_ratio' => '9:16',
            'from' => null,
            'to' => null,
            'status' => 'draft',
        ]);

        $this->toast('¡Post publicado correctamente!', 'success');

        $this->showInto(Home::class, force: true, updateBrowserUrl: true);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onCancelPost(array $params): void
    {
        $this->showInto(Home::class, force: true, updateBrowserUrl: true);
    }

    public function onPlainSaved(array $params): void
    {
        $rawValue = $params['value'] ?? '';
        $value = trim(is_scalar($rawValue) || $rawValue instanceof \Stringable ? (string) $rawValue : '');
        $this->post_content->value($value);
    }
}
