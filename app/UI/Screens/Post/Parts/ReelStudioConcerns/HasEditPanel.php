<?php

namespace App\UI\Screens\Post\Parts\ReelStudioConcerns;

use App\DTOs\Posts\PostHistoryEventDTO;
use App\Enums\PostHistoryEventType;
use App\Repositories\Contracts\PostHistoryRepositoryInterface;
use App\Repositories\Eloquent\EloquentPostHistoryRepository;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\ItemList;
use Idei\Usim\Components\NewLabel;
use Idei\Usim\Enums\Align;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\ContainerAppearance;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\SelectionMode;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

trait HasEditPanel
{
    // Post Title & Status row
    protected NewLabel $lbl_post_title;
    protected Container $cnt_status_row;
    protected NewLabel $lbl_status_prefix;
    protected NewLabel $lbl_status_badge;

    // Version History List
    protected ItemList $lst_history;

    // Waiting on Creator Card
    protected Container $cnt_waiting_creator;
    protected NewLabel $lbl_waiting_icon;
    protected NewLabel $lbl_waiting_title;
    protected NewLabel $lbl_waiting_desc;

    /**
     * Configure the edit panel and attach its declarative layout.
     */
    protected function buildEditReelPanel(): Container
    {
        $this->configureStatusRow();
        $this->configureWaitingCreatorCard();
        $this->loadHistoryItems();

        if (($this->state_post_id ?? 0) > 0 && isset($this->postService)) {
            $post = $this->postService->findById($this->state_post_id);
            if ($post !== null && !empty($post->title)) {
                $this->lbl_post_title->text($post->title);
            }
            if ($post !== null) {
                $this->lbl_status_badge->text(strtoupper($post->statusValue()));
            }
        }

        return $this->edit_panel
            ->layoutFile('reel-studio-edit.layout.yaml');
    }

    /**
     * Configure horizontal status row container.
     */
    protected function configureStatusRow(): Container
    {
        return $this->cnt_status_row
            ->plain()
            ->layout(LayoutType::HORIZONTAL)
            ->alignItems(AlignItems::CENTER)
            ->gap(Spacing::xs())
            ->add(
                $this->lbl_status_prefix
                    ->text($this->t('.status_prefix', [], 'ESTADO:'))
                    ->muted()
                    ->small()
            )
            ->add(
                $this->lbl_status_badge
                    ->text($this->t('.status_badge', [], 'BORRADOR'))
                    ->pill()
                    ->secondary()
                    ->small()
            );
    }

    /**
     * Configure the waiting on creator status card using semantic container appearance.
     */
    protected function configureWaitingCreatorCard(): Container
    {
        return $this->cnt_waiting_creator
            ->appearance(ContainerAppearance::CARD)
            ->layout(LayoutType::VERTICAL)
            ->width(Size::full())
            ->padding(Spacing::m())
            ->gap(Spacing::xs())
            ->alignItems(AlignItems::CENTER)
            ->justifyContent(JustifyContent::CENTER)
            ->add(
                $this->lbl_waiting_icon
                    ->icon('clock')
                    ->pill()
                    ->secondary()
                    ->center()
            )
            ->add(
                $this->lbl_waiting_title
                    ->text($this->t('.waiting_title', [], 'Esperando al creador'))
                    ->bold()
                    ->center()
            )
            ->add(
                $this->lbl_waiting_desc
                    ->text($this->t('.waiting_desc', [], 'El creador está editando el post. Recibirás una notificación cuando lo envíe a revisión.'))
                    ->muted()
                    ->small()
                    ->center()
            );
    }

    /**
     * Load history events for the current post, falling back to default preview events.
     */
    protected function loadHistoryItems(): void
    {
        $items = [];
        $postId = $this->state_post_id ?? 0;

        if ($postId > 0) {
            /** @var PostHistoryRepositoryInterface $historyRepo */
            $historyRepo = app()->bound(PostHistoryRepositoryInterface::class)
                ? app(PostHistoryRepositoryInterface::class)
                : app(EloquentPostHistoryRepository::class);

            $events = $historyRepo->get(['post_id' => $postId]);
            if ($events->isNotEmpty()) {
                foreach ($events as $event) {
                    $items[] = $this->formatHistoryEventItem($event);
                }
            }
        }

        if (empty($items)) {
            $items = $this->getDefaultHistoryItems();
        }

        $this->lst_history
            ->selectionMode(SelectionMode::SINGLE)
            ->items($items);

        if (!empty($items)) {
            $this->lst_history->select([(string) $items[0]['id']]);
        }
    }

    /**
     * Default history items matching the design mock when no stored events exist.
     *
     * @return list<array<string, mixed>>
     */
    protected function getDefaultHistoryItems(): array
    {
        return [
            [
                'id' => 'v1.1',
                'label' => $this->t('.history.items.v1_1.label', [], 'Versión 1.1'),
                'badge' => $this->t('.history.items.v1_1.badge', [], 'En revisión'),
                'badge_style' => 'info',
                'subtitle' => $this->t('.history.items.v1_1.subtitle', [], 'Se agregó el logotipo de la facultad. · Basada en v1.0 · Generada por IA'),
            ],
            [
                'id' => 'rev1',
                'icon' => 'mail',
                'label' => $this->t('.history.items.rev1.label', [], 'Solicitud de revisión'),
                'badge' => $this->t('.history.items.rev1.badge', [], 'Cambios solicitados'),
                'badge_style' => 'warning',
                'subtitle' => $this->t('.history.items.rev1.subtitle', [], 'Juan Pérez: «Agregá el logotipo de la facultad.»'),
            ],
            [
                'id' => 'v1.0',
                'label' => $this->t('.history.items.v1_0.label', [], 'Versión 1.0'),
                'subtitle' => $this->t('.history.items.v1_0.subtitle', [], 'Borrador inicial generado'),
            ],
        ];
    }

    /**
     * Map a domain PostHistoryEventDTO into an ItemList item representation.
     *
     * @return array<string, mixed>
     */
    protected function formatHistoryEventItem(PostHistoryEventDTO $event): array
    {
        $badge = null;
        $badgeStyle = 'default';
        $icon = null;
        $label = '';
        $subtitle = '';

        $type = $event->eventType instanceof PostHistoryEventType
            ? $event->eventType
            : PostHistoryEventType::tryFrom((string) $event->eventType);

        switch ($type) {
            case PostHistoryEventType::VERSION_GENERATED:
                $label = 'Versión ' . ($event->versionLabel() ?? '1.0');
                $badge = 'En revisión';
                $badgeStyle = 'info';
                $meta = $this->formatEventMetadata($event);
                $parts = array_filter([$event->summary, $meta]);
                $subtitle = implode(' · ', $parts);
                break;

            case PostHistoryEventType::REVIEW_CHANGES_REQUESTED:
                $label = 'Solicitud de revisión';
                $icon = 'mail';
                $badge = 'Cambios solicitados';
                $badgeStyle = 'warning';
                $actor = $event->actor?->displayName ?? $event->actor?->name ?? 'Revisor';
                $comment = $event->reviewComment ? "«{$event->reviewComment}»" : '';
                $subtitle = trim("{$actor}: {$comment}");
                break;

            case PostHistoryEventType::REVIEW_APPROVED:
                $label = 'Revisión aprobada';
                $icon = 'check';
                $badge = 'Aprobado';
                $badgeStyle = 'success';
                $subtitle = $event->summary ?? '';
                break;

            default:
                $label = $event->summary ?? 'Evento';
                $subtitle = $event->instruction ?? '';
                break;
        }

        return [
            'id' => (string) $event->id,
            'label' => $label,
            'subtitle' => $subtitle,
            'badge' => $badge,
            'badge_style' => $badgeStyle,
            'icon' => $icon,
        ];
    }

    /**
     * Format metadata line for a generated version event.
     */
    protected function formatEventMetadata(PostHistoryEventDTO $event): string
    {
        $meta = [];
        if ($event->basedOnHistoryId) {
            $meta[] = "Basada en v{$event->basedOnHistoryId}";
        }
        if (!empty($event->metadata)) {
            $meta[] = 'Generada por IA';
        }

        return implode(' · ', $meta);
    }

    /**
     * Handle version history item selection.
     *
     * @param array<int, string|int> $selected
     */
    public function onSelectHistoryEvent(string|int|null $id = null, array $selected = []): void
    {
        $selectedId = $id ?? ($selected[0] ?? null);
        if ($selectedId !== null) {
            $this->lst_history->select([(string) $selectedId]);
        }
    }
}
