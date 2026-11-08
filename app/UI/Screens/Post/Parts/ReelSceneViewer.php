<?php

declare(strict_types=1);

namespace App\UI\Screens\Post\Parts;

use App\Contracts\PostServiceInterface;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Label;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Facades\File;

/**
 * Reusable USIM Screen Component for navigating and visualizing AI Reel scenes.
 *
 * Can receive a JSON string, parsed array, or post_id, and renders a realistic
 * vertical 9:16 smartphone preview with storyboard progress, layout templates,
 * and bidirectional scene navigation.
 */
class ReelSceneViewer extends Screen
{
    /**
     * Internal server-side state persisted across USIM requests.
     */
    protected array $state_scenes = [];

    protected array $state_metadata = [];

    protected int $state_current_scene_index = 0;

    protected int $state_post_id = 0;

    // UI Components
    protected Container $phone_mockup_container;

    protected Label $scene_content_display;

    protected Container $nav_controls_container;

    protected Button $btn_prev_scene;

    protected Label $scene_indicator;

    protected Button $btn_next_scene;

    public function __construct(
        protected ?PostServiceInterface $postService = null
    ) {
        parent::__construct();
    }

    public static function authorize(): bool
    {
        return true;
    }

    /**
     * Build the base UI. Accepts parameters via embed() or navigation.
     *
     * Supported parameters:
     * - 'data' | 'script_data' | 'json': JSON string, file path, or decoded array.
     * - 'post_id': int Post ID to fetch ai_script if no data is provided.
     * - 'initial_scene': int Initial scene index (0-based).
     */
    protected function buildBaseUI(Container $container, ...$params): void
    {
        $this->hydrateScriptData($params);

        $container
            ->plain()
            ->width(Size::full())
            ->layout(LayoutType::VERTICAL)
            ->justifyContent(JustifyContent::CENTER)
            ->alignItems(AlignItems::CENTER)
            ->padding(Spacing::zero());

        // Outer phone mockup frame (vertical 9:16 aspect ratio styling)
        $this->phone_mockup_container = UI::container('phone_mockup_container')
            ->width(Size::px(310))
            ->maxWidth(Size::px(320))
            ->height(Size::px(540))
            ->backgroundColor('#f3f5f9')
            ->borderRadius('26px')
            ->border('1px solid #dce2ea')
            ->shadow(true)
            ->layout(LayoutType::VERTICAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->alignItems(AlignItems::CENTER)
            ->padding(Spacing::px(12))
            ->gap(Spacing::px(10));

        // Center scene presentation canvas
        $this->scene_content_display = UI::label('scene_content_display')
            ->width(Size::full())
            ->html($this->renderCurrentSceneHtml());

        // Bottom navigation bar matching the design mockup: [<] [Escena X de Y] [>]
        $this->nav_controls_container = $this->buildNavigationRow();

        $this->phone_mockup_container->add($this->scene_content_display);
        $this->phone_mockup_container->add($this->nav_controls_container);

        $container->add($this->phone_mockup_container);

        $this->updateNavigationState();
    }

    /**
     * Builds the bottom navigation bar container.
     */
    protected function buildNavigationRow(): Container
    {
        $row = UI::container('nav_controls_container')
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->alignItems(AlignItems::CENTER)
            ->width(Size::full())
            ->padding(Spacing::px(4))
            ->gap(Spacing::px(8));

        $total = count($this->state_scenes);
        $currentIndex = $this->state_current_scene_index;

        $this->btn_prev_scene = UI::button('btn_prev_scene')
            ->label('‹')
            ->style('secondary')
            ->variant('outline')
            ->action('prev_scene')
            ->enabled($currentIndex > 0);

        $sceneNumber = $total > 0 ? $currentIndex + 1 : 0;
        $counterText = $total > 0 ? "Escena {$sceneNumber} de {$total}" : 'Escena 0 de 0';

        $this->scene_indicator = UI::label('scene_indicator')
            ->text($counterText)
            ->bold()
            ->center();

        $this->btn_next_scene = UI::button('btn_next_scene')
            ->label('›')
            ->style('secondary')
            ->variant('outline')
            ->action('next_scene')
            ->enabled($total > 0 && $currentIndex < $total - 1);

        $row->add($this->btn_prev_scene);
        $row->add($this->scene_indicator);
        $row->add($this->btn_next_scene);

        return $row;
    }

    // ========================================================================
    // ACTION HANDLERS
    // ========================================================================

    public function onPrevScene(): void
    {
        if ($this->state_current_scene_index > 0) {
            $this->state_current_scene_index--;
            $this->refreshView();
        }
    }

    public function onNextScene(): void
    {
        if ($this->state_current_scene_index < count($this->state_scenes) - 1) {
            $this->state_current_scene_index++;
            $this->refreshView();
        }
    }

    public function onSelectScene(int $index): void
    {
        if ($index >= 0 && $index < count($this->state_scenes)) {
            $this->state_current_scene_index = $index;
            $this->refreshView();
        }
    }

    /**
     * Refreshes the display and navigation states after a user action.
     */
    protected function refreshView(): void
    {
        $this->updateNavigationState();

        if (isset($this->scene_content_display)) {
            $this->scene_content_display->html($this->renderCurrentSceneHtml());
        }
    }

    protected function updateNavigationState(): void
    {
        $total = count($this->state_scenes);
        $current = $this->state_current_scene_index;

        if ($current >= $total && $total > 0) {
            $this->state_current_scene_index = $total - 1;
            $current = $this->state_current_scene_index;
        }

        $sceneNumber = $total > 0 ? $current + 1 : 0;
        $counterText = $total > 0 ? "Escena {$sceneNumber} de {$total}" : 'Escena 0 de 0';

        if (isset($this->scene_indicator)) {
            $this->scene_indicator->text($counterText);
        }

        if (isset($this->btn_prev_scene)) {
            $this->btn_prev_scene->enabled($current > 0);
        }

        if (isset($this->btn_next_scene)) {
            $this->btn_next_scene->enabled($total > 0 && $current < $total - 1);
        }
    }

    // ========================================================================
    // DATA HYDRATION
    // ========================================================================

    protected function hydrateScriptData(array $params): void
    {
        $raw = $params['data']
            ?? $params['script_data']
            ?? $params['json']
            ?? $params['ai_script']
            ?? null;

        // If no explicit data given, check if a file path or sample flag was requested
        if ($raw === null && isset($params['json_path']) && is_string($params['json_path'])) {
            $raw = $params['json_path'];
        }

        $decoded = null;

        if (is_array($raw)) {
            $decoded = $raw;
        } elseif (is_string($raw) && trim($raw) !== '') {
            $trimmed = trim($raw);
            if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
                $decoded = json_decode($trimmed, true);
            } elseif (File::exists($trimmed)) {
                $fileContent = File::get($trimmed);
                $decoded = json_decode($fileContent, true);
            }
        }

        // If still null, check post_id parameter
        if ($decoded === null) {
            $postId = (int) ($params['post_id'] ?? request()->query('post_id', "0"));
            if ($postId > 0 && $this->postService !== null) {
                $this->state_post_id = $postId;
                $post = $this->postService->findById($postId);
                if ($post !== null && ! empty($post->aiScript)) {
                    $decoded = json_decode((string) $post->aiScript, true);
                }
            }
        }

        // Fallback: Check if _data.json exists in root if running in standalone test mode with ?sample=1
        if ($decoded === null && request()->query('sample') === '1') {
            $fallbackPath = base_path('_data.json');
            if (File::exists($fallbackPath)) {
                $decoded = json_decode(File::get($fallbackPath), true);
            }
        }

        if (is_array($decoded)) {
            $this->state_scenes = is_array($decoded['scenes'] ?? null) ? $decoded['scenes'] : [];
            $this->state_metadata = is_array($decoded['metadata'] ?? null) ? $decoded['metadata'] : [];
        }

        if (isset($params['initial_scene']) && is_numeric($params['initial_scene'])) {
            $this->state_current_scene_index = max(0, (int) $params['initial_scene']);
        }
    }

    // ========================================================================
    // VISUAL REPRESENTATION & RENDERERS
    // ========================================================================

    /**
     * Render the active scene HTML or empty state.
     */
    public function renderCurrentSceneHtml(): string
    {
        if (empty($this->state_scenes)) {
            return self::renderEmptyStateHtml();
        }

        $index = $this->state_current_scene_index;
        $total = count($this->state_scenes);
        $scene = $this->state_scenes[$index] ?? $this->state_scenes[0];

        return self::renderSceneHtml($scene, $this->state_metadata, $index, $total);
    }

    /**
     * Render the Empty State matching the user mockup screenshot.
     */
    public static function renderEmptyStateHtml(): string
    {
        return '
        <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; height: 420px; padding: 24px 18px; gap: 20px; box-sizing: border-box;">
            <!-- Magic Wand Icon with Sparkles -->
            <div style="width: 76px; height: 76px; border-radius: 50%; background: #e0e7ff; display:flex; align-items:center; justify-content:center; color: #4f46e5; box-shadow: 0 4px 14px rgba(79, 70, 229, 0.15);">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m19 11-4-4"/>
                    <path d="m2 22 10-10"/>
                    <path d="M19 15v4"/>
                    <path d="M21 17h-4"/>
                    <path d="M7 2v4"/>
                    <path d="M9 4H5"/>
                    <path d="M19 2v2"/>
                    <path d="M20 3h-2"/>
                </svg>
            </div>

            <!-- Title -->
            <div style="font-size: 19px; font-weight: 700; color: #1e293b; line-height: 1.35; max-width: 240px; letter-spacing: -0.3px;">
                Aquí aparecerá la estructura de tu Reel
            </div>

            <!-- Subtitle -->
            <div style="font-size: 13px; color: #64748b; line-height: 1.5; max-width: 230px;">
                Haz clic en \'Generar Primera Estructura\' para que la IA procese tu información.
            </div>
        </div>';
    }

    /**
     * Render a specific scene based on layout_template and visual assets.
     */
    public static function renderSceneHtml(array $scene, array $metadata = [], int $index = 0, int $total = 1): string
    {
        $template = (string) ($scene['layout_template'] ?? 'title_hero');
        $duration = (int) ($scene['duration_seconds'] ?? 5);
        $textContent = (array) ($scene['text_content'] ?? []);
        $headline = htmlspecialchars((string) ($textContent['headline'] ?? 'Escena'), ENT_QUOTES, 'UTF-8');
        $subheadline = htmlspecialchars((string) ($textContent['subheadline'] ?? ''), ENT_QUOTES, 'UTF-8');
        $body = (array) ($textContent['body'] ?? []);
        $visualAssets = (array) ($scene['visual_assets'] ?? []);
        $imageUrl = ! empty($visualAssets['image_url']) ? htmlspecialchars((string) $visualAssets['image_url'], ENT_QUOTES, 'UTF-8') : null;
        $qrUrl = ! empty($visualAssets['qr_image_url']) ? htmlspecialchars((string) $visualAssets['qr_image_url'], ENT_QUOTES, 'UTF-8') : null;
        $animation = (array) ($scene['animation_config'] ?? []);
        $entrance = htmlspecialchars((string) ($animation['entrance_type'] ?? 'fade_in'), ENT_QUOTES, 'UTF-8');
        $easing = htmlspecialchars((string) ($animation['easing'] ?? 'ease_out'), ENT_QUOTES, 'UTF-8');

        $dept = htmlspecialchars((string) ($metadata['departamento'] ?? $metadata['autor'] ?? 'UNSJ'), ENT_QUOTES, 'UTF-8');

        // Segmented Story progress bars at top
        $storyBarsHtml = self::renderStoryBarsHtml($total, $index);

        // Layout template badge label
        $templateLabel = match ($template) {
            'title_hero' => '✨ Portada Hero',
            'bullet_points' => '📋 Puntos Clave',
            'call_to_action' => '🎯 Llamado a la Acción',
            default => '🎬 Escena',
        };

        // Render template body
        $templateBodyHtml = match ($template) {
            'bullet_points' => self::renderBulletPointsTemplate($headline, $subheadline, $body, $entrance, $duration),
            'call_to_action' => self::renderCallToActionTemplate($headline, $subheadline, $body, $qrUrl, $entrance, $duration),
            default => self::renderTitleHeroTemplate($headline, $subheadline, $dept, $imageUrl, $entrance, $easing, $duration),
        };

        return '
        <div style="width: 100%; height: 430px; display: flex; flex-direction: column; justify-content: space-between; position: relative; box-sizing: border-box;">
            <!-- Top Story Progress Bar & Metadata Header -->
            <div style="width: 100%; margin-bottom: 8px;">
                ' . $storyBarsHtml . '
                <div style="display:flex; justify-content: space-between; align-items: center; padding: 4px 6px 0; font-size: 11px; font-weight: 600; color: #64748b;">
                    <span>' . $templateLabel . '</span>
                    <span style="background: rgba(0,0,0,0.06); padding: 2px 7px; border-radius: 10px;">⏱ ' . $duration . 's</span>
                </div>
            </div>

            <!-- Scene Canvas -->
            <div style="flex: 1; display: flex; flex-direction: column; width: 100%; overflow: hidden; border-radius: 18px; box-shadow: 0 4px 12px rgba(0,0,0,0.08);">
                ' . $templateBodyHtml . '
            </div>
        </div>';
    }

    /**
     * Top segmented story progress bars (Instagram / Reels style).
     */
    protected static function renderStoryBarsHtml(int $total, int $activeIndex): string
    {
        if ($total <= 1) {
            return '';
        }

        $bars = '';
        for ($i = 0; $i < $total; $i++) {
            $isActive = $i === $activeIndex;
            $bg = $isActive ? '#2563eb' : 'rgba(148, 163, 184, 0.4)';
            $bars .= '<div style="flex: 1; height: 3px; border-radius: 2px; background: ' . $bg . '; transition: all 0.3s ease;"></div>';
        }

        return '<div style="display:flex; gap: 4px; width: 100%; padding: 2px 4px 6px;">' . $bars . '</div>';
    }

    /**
     * Template: title_hero
     */
    protected static function renderTitleHeroTemplate(
        string $headline,
        string $subheadline,
        string $dept,
        ?string $imageUrl,
        string $entrance,
        string $easing,
        int $duration
    ): string {
        $bgStyle = $imageUrl !== null
            ? 'background: linear-gradient(180deg, rgba(15,23,42,0.4) 0%, rgba(15,23,42,0.9) 100%), url(\'' . $imageUrl . '\') center/cover no-repeat, #0f172a;'
            : 'background: linear-gradient(135deg, #0b2545 0%, #134074 100%);';

        return '
        <div style="width: 100%; height: 100%; ' . $bgStyle . ' display: flex; flex-direction: column; justify-content: space-between; padding: 20px 16px; color: #ffffff; box-sizing: border-box;">
            <div style="display:inline-flex; align-items:center; align-self: flex-start; gap: 6px; padding: 4px 10px; border-radius: 20px; background: rgba(255,255,255,0.18); backdrop-filter: blur(8px); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                🏛 ' . $dept . '
            </div>

            <div style="display: flex; flex-direction: column; gap: 8px; text-align: left;">
                <div style="font-size: 22px; font-weight: 800; line-height: 1.25; text-shadow: 0 2px 8px rgba(0,0,0,0.6); color: #ffffff;">
                    ' . $headline . '
                </div>
                ' . ($subheadline !== '' ? '<div style="font-size: 14px; font-weight: 600; color: #38bdf8; text-shadow: 0 1px 4px rgba(0,0,0,0.5);">' . $subheadline . '</div>' : '') . '
            </div>

            <div style="display: inline-flex; align-items: center; gap: 5px; font-size: 10px; color: rgba(255,255,255,0.8); background: rgba(0,0,0,0.4); padding: 4px 8px; border-radius: 10px; align-self: flex-start;">
                🎬 ' . ucfirst(str_replace('_', ' ', $entrance)) . ' (' . $easing . ')
            </div>
        </div>';
    }

    /**
     * Template: bullet_points
     */
    protected static function renderBulletPointsTemplate(
        string $headline,
        string $subheadline,
        array $body,
        string $entrance,
        int $duration
    ): string {
        $itemsHtml = '';
        foreach ($body as $item) {
            $itemText = htmlspecialchars((string) $item, ENT_QUOTES, 'UTF-8');
            $itemsHtml .= '
            <div style="display:flex; align-items:center; gap: 10px; padding: 8px 12px; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.12); border-radius: 10px; font-size: 12px; font-weight: 500; color: #f8fafc;">
                <span style="display:inline-flex; align-items:center; justify-content:center; width: 18px; height: 18px; border-radius: 50%; background: #10b981; color: white; font-size: 11px; font-weight: 700; flex-shrink: 0;">✓</span>
                <span>' . $itemText . '</span>
            </div>';
        }

        return '
        <div style="width: 100%; height: 100%; background: linear-gradient(145deg, #0f172a 0%, #1e293b 100%); display: flex; flex-direction: column; justify-content: space-between; padding: 20px 16px; color: #ffffff; box-sizing: border-box;">
            <div>
                <div style="font-size: 19px; font-weight: 800; line-height: 1.25; margin-bottom: 6px; color: #ffffff;">
                    ' . $headline . '
                </div>
                ' . ($subheadline !== '' ? '<div style="font-size: 12px; color: #94a3b8; margin-bottom: 12px;">' . $subheadline . '</div>' : '<div style="height: 6px;"></div>') . '
                <div style="display:flex; flex-direction:column; gap: 8px;">
                    ' . $itemsHtml . '
                </div>
            </div>

            <div style="display: inline-flex; align-items: center; gap: 5px; font-size: 10px; color: #94a3b8; background: rgba(255,255,255,0.06); padding: 4px 8px; border-radius: 10px; align-self: flex-start;">
                🎬 ' . ucfirst(str_replace('_', ' ', $entrance)) . '
            </div>
        </div>';
    }

    /**
     * Template: call_to_action
     */
    protected static function renderCallToActionTemplate(
        string $headline,
        string $subheadline,
        array $body,
        ?string $qrUrl,
        string $entrance,
        int $duration
    ): string {
        $bodyText = ! empty($body) ? htmlspecialchars((string) $body[0], ENT_QUOTES, 'UTF-8') : '';

        $qrHtml = $qrUrl !== null
            ? '<img src="' . $qrUrl . '" alt="QR Code" style="width: 105px; height: 105px; object-fit: contain; display: block;" onerror="this.onerror=null; this.src=\'data:image/svg+xml;utf8,<svg xmlns=\\\'http://www.w3.org/2000/svg\\\' width=\\\'105\\\' height=\\\'105\\\' viewBox=\\\'0 0 24 24\\\' fill=\\\'none\\\' stroke=\\\'%23334155\\\' stroke-width=\\\'2\\\'><rect width=\\\'18\\\' height=\\\'18\\\' x=\\\'3\\\' y=\\\'3\\\' rx=\\\'2\\\'/><path d=\\\'M7 7h3v3H7zM14 7h3v3h-3zM7 14h3v3H7zM14 14h.01M17 14h.01M14 17h3v-1\\\'/></svg>\';" />'
            : '<div style="width: 105px; height: 105px; display:flex; align-items:center; justify-content:center; background: #f1f5f9; border-radius: 8px; color: #64748b; font-size: 11px; font-weight: 600;">📱 QR Code</div>';

        return '
        <div style="width: 100%; height: 100%; background: linear-gradient(145deg, #1e1b4b 0%, #312e81 100%); display: flex; flex-direction: column; justify-content: space-between; align-items: center; padding: 18px 14px; text-align: center; color: #ffffff; box-sizing: border-box;">
            <div style="width: 100%; display: flex; flex-direction: column; align-items: center;">
                <div style="font-size: 20px; font-weight: 800; line-height: 1.2; margin-bottom: 6px; color: #ffffff;">
                    ' . $headline . '
                </div>

                ' . ($subheadline !== '' ? '<div style="display:inline-block; padding: 3px 10px; border-radius: 16px; background: rgba(245,158,11,0.25); border: 1px solid rgba(245,158,11,0.4); color: #fcd34d; font-size: 11px; font-weight: 600; margin-bottom: 10px;">' . $subheadline . '</div>' : '') . '

                ' . ($bodyText !== '' ? '<div style="font-size: 11px; color: #cbd5e1; margin-bottom: 12px; max-width: 210px; line-height: 1.4;">' . $bodyText . '</div>' : '') . '

                <div style="background: #ffffff; padding: 8px; border-radius: 14px; box-shadow: 0 8px 18px rgba(0,0,0,0.35);">
                    ' . $qrHtml . '
                </div>
            </div>

            <div style="display: inline-flex; align-items: center; gap: 5px; font-size: 10px; color: #a5b4fc; background: rgba(255,255,255,0.08); padding: 4px 8px; border-radius: 10px;">
                🎬 ' . ucfirst(str_replace('_', ' ', $entrance)) . '
            </div>
        </div>';
    }

    // ========================================================================
    // GETTERS FOR TESTING AND EMBEDDING
    // ========================================================================

    public function getCurrentSceneIndex(): int
    {
        return $this->state_current_scene_index;
    }

    public function getScenes(): array
    {
        return $this->state_scenes;
    }

    public function getMetadata(): array
    {
        return $this->state_metadata;
    }
}
