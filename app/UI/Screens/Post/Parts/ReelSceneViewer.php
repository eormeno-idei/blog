<?php

declare(strict_types=1);

namespace App\UI\Screens\Post\Parts;

use App\Contracts\PostServiceInterface;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\NewLabel;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Facades\File;

/**
 * Reusable USIM Screen Component for navigating and inspecting AI Reel textual content.
 *
 * Receives a JSON string, file path, parsed array, or post_id, and renders the
 * informational text content of each scene directly inside a flat CSS Grid card.
 */
class ReelSceneViewer extends Screen
{
    /**
     * Internal server-side state persisted across USIM requests.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $state_scenes = [];

    /** @var array<string, mixed> */
    protected array $state_metadata = [];

    protected int $state_current_scene_index = 0;

    protected int $state_post_id = 0;

    // Header labels
    protected NewLabel $lbl_scene_meta;

    protected NewLabel $lbl_scene_duration;

    // Scene body components
    protected NewLabel $lbl_empty_title;

    protected NewLabel $lbl_empty_subtitle;

    protected NewLabel $lbl_scene_headline;

    protected NewLabel $lbl_scene_subheadline;

    protected Container $scene_body_list;

    // Bottom navigation components
    protected Button $btn_prev_scene;

    protected NewLabel $scene_indicator;

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
     * Build the starting UI. Accepts parameters via embed() or navigation.
     *
     * Supported parameters:
     * - 'data' | 'script_data' | 'json' | 'ai_script': JSON string, file path, or decoded array.
     * - 'json_path': file path to JSON script.
     * - 'post_id': int Post ID to fetch ai_script if no data is provided.
     * - 'initial_scene': int Initial scene index (0-based).
     *
     * @param  array<string, mixed>  $params
     */
    protected function startingUI(array $params = []): Container
    {
        $this->hydrateScriptData($params);

        $this->scene_body_list
            ->plain()
            ->layout(LayoutType::VERTICAL)
            ->width(Size::full())
            ->padding(Spacing::none())
            ->gap(Spacing::xs());

        $this->refreshView();

        return $this->layoutContainer()->shadow(true);
    }

    protected function postLoadUI(): void
    {
        $this->getContainer()
            ?->padding(Spacing::zero())
            ->width(Size::full())
            ->height(Size::full())
            ->justifyContent(JustifyContent::START);
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
     * Refreshes the display and navigation states after initialization or user action.
     */
    protected function refreshView(): void
    {
        $this->updateNavigationState();
        $this->updateSceneContent();
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
        $counterText = $total > 0
            ? $this->t('.nav.counter', ['current' => $sceneNumber, 'total' => $total])
            : $this->t('.nav.empty');

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

    protected function updateSceneContent(): void
    {
        $hasScenes = ! empty($this->state_scenes);

        if (! $hasScenes) {
            $this->lbl_scene_meta->visible(false);
            $this->lbl_scene_duration->visible(false);

            $this->lbl_empty_title->visible(true);
            $this->lbl_empty_subtitle->visible(true);

            $this->lbl_scene_headline->visible(false);
            $this->lbl_scene_subheadline->visible(false);
            $this->scene_body_list->clear()->visible(false);

            return;
        }

        $scene = $this->state_scenes[$this->state_current_scene_index] ?? $this->state_scenes[0];
        $textContent = is_array($scene['text_content'] ?? null) ? $scene['text_content'] : [];
        $headline = is_string($textContent['headline'] ?? null) ? trim($textContent['headline']) : '';
        $subheadline = is_string($textContent['subheadline'] ?? null) ? trim($textContent['subheadline']) : '';
        $rawBody = is_array($textContent['body'] ?? null) ? $textContent['body'] : [];
        $body = array_values(array_filter(
            array_map(static fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '', $rawBody),
            static fn (string $item): bool => $item !== ''
        ));

        $rawMeta = $this->state_metadata['departamento'] ?? $this->state_metadata['autor'] ?? '';
        $metaText = is_string($rawMeta) ? trim($rawMeta) : '';
        $rawDuration = $scene['duration_seconds'] ?? 5;
        $duration = is_numeric($rawDuration) ? (int) $rawDuration : 5;

        $this->lbl_scene_meta
            ->text($metaText)
            ->visible($metaText !== '');
        $this->lbl_scene_duration
            ->text($this->t('.scene.duration', ['seconds' => $duration]))
            ->visible(true);

        $this->lbl_empty_title->visible(false);
        $this->lbl_empty_subtitle->visible(false);

        $this->lbl_scene_headline
            ->text($headline !== '' ? $headline : $this->t('.scene.default_headline'))
            ->visible(true);

        $this->lbl_scene_subheadline
            ->text($subheadline)
            ->visible($subheadline !== '');

        $this->scene_body_list->clear();
        foreach ($body as $idx => $item) {
            $this->scene_body_list->add(
                UI::newLabel("scene_body_item_{$idx}")
                    ->text("• {$item}")
                    ->small()
                    ->width(Size::full())
            );
        }
        $this->scene_body_list->visible($body !== []);
    }

    // ========================================================================
    // DATA HYDRATION
    // ========================================================================

    /**
     * @param  array<string, mixed>  $params
     */
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
            $rawPostId = $params['post_id'] ?? request()->query('post_id', '0');
            $postId = is_numeric($rawPostId) ? (int) $rawPostId : 0;
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
            /** @var array<int, array<string, mixed>> $scenes */
            $scenes = is_array($decoded['scenes'] ?? null) ? array_values($decoded['scenes']) : [];
            /** @var array<string, mixed> $metadata */
            $metadata = is_array($decoded['metadata'] ?? null) ? $decoded['metadata'] : [];

            $this->state_scenes = $scenes;
            $this->state_metadata = $metadata;
        }

        if (isset($params['initial_scene']) && is_numeric($params['initial_scene'])) {
            $this->state_current_scene_index = max(0, (int) $params['initial_scene']);
        }
    }

    // ========================================================================
    // GETTERS FOR TESTING AND EMBEDDING
    // ========================================================================

    public function getCurrentSceneIndex(): int
    {
        return $this->state_current_scene_index;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getScenes(): array
    {
        return $this->state_scenes;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->state_metadata;
    }
}
