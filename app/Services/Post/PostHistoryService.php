<?php

namespace App\Services\Post;

use App\Contracts\PostHistoryServiceInterface;
use App\DTOs\Posts\PostHistoryEventDTO;
use App\Enums\PostHistoryEventType;
use App\Repositories\Contracts\PostHistoryRepositoryInterface;
use Idei\Usim\Contracts\ItemList\Listable;
use Idei\Usim\DTOs\ListItemDTO;
use Idei\Usim\Enums\Style;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

class PostHistoryService implements Listable, PostHistoryServiceInterface
{
    public function __construct(
        private readonly PostHistoryRepositoryInterface $postHistoryRepository
    ) {
    }

    public function findPostEventHistoryById(?Authenticatable $user, int $event_id): ?PostHistoryEventDTO
    {
        return $this->postHistoryRepository->findById($event_id);
    }

    public function hasFirstDraftEvent(?Authenticatable $user, int $post_id): bool
    {
        $events = $this->postHistoryRepository->get([
            'post_id' => $post_id,
            'event_type' => PostHistoryEventType::VERSION_GENERATED,
        ]);

        return $events->isNotEmpty();
    }

    public function getLastGeneratedVersionEvent(?Authenticatable $user, int $post_id): ?PostHistoryEventDTO
    {
        $events = $this->postHistoryRepository->get([
            'post_id' => $post_id,
            'event_type' => PostHistoryEventType::VERSION_GENERATED,
        ]);

        return $events->first();
    }

    public function getLatestReelEvent(int $post_id): ?PostHistoryEventDTO
    {
        $events = $this->postHistoryRepository->get([
            'post_id' => $post_id,
            'event_type' => [
                PostHistoryEventType::GENERATE_REQUESTED,
                PostHistoryEventType::GENERATING_SCRIPT,
                PostHistoryEventType::VERSION_GENERATED,
            ],
        ]);

        return $events->first();
    }

    /**
     * Retrieve list items for an ItemList component according to Listable contract.
     *
     * @param  array<string, mixed>  $context
     * @return Collection<int, ListItemDTO>
     */
    public function list(string $search = '', array $context = []): Collection
    {
        $post_id = (int) ($context['post_id'] ?? 0);

        $events = $this->postHistoryRepository->get([
            'post_id' => $post_id,
            'event_type' => [
                PostHistoryEventType::VERSION_GENERATED,
                PostHistoryEventType::GENERATION_FAILED,
            ],
        ]);

        $items = $events->map(fn(PostHistoryEventDTO $event) => $this->formatEventToListItem($event));

        if ($search !== '') {
            $searchTerm = mb_strtolower($search);
            $items = $items->filter(
                fn(ListItemDTO $dto) => str_contains(mb_strtolower($dto->label), $searchTerm) ||
                    ($dto->subtitle !== null && str_contains(mb_strtolower($dto->subtitle), $searchTerm))
            );
        }

        return $items->values();
    }

    /**
     * Map a domain PostHistoryEventDTO into a ListItemDTO representation.
     */
    public function formatEventToListItem(PostHistoryEventDTO $event): ListItemDTO
    {
        $badge = null;
        $badgeStyle = Style::DEFAULT;
        $icon = null;
        $label = '';
        $subtitle = '';
        $disabled = false;

        $type = $event->event_type;

        switch ($type) {
            case PostHistoryEventType::VERSION_GENERATED:
                $label = 'Versión ' . ($event->version ?? 1);
                $badge = 'Versión generada';
                $badgeStyle = Style::INFO;
                $meta = [];
                if ($event->based_on_history_id) {
                    $meta[] = "Basada en v{$event->based_on_history_id}";
                }
                if (!empty($event->reel_script)) {
                    $meta[] = $event->updated_at->diffForHumans();
                }
                $metaStr = implode(' · ', $meta);
                $parts = array_filter([$event->summary, $metaStr !== '' ? $metaStr : null]);
                $subtitle = implode(' · ', $parts);
                break;

            case PostHistoryEventType::REVIEW_CHANGES_REQUESTED:
                $label = 'Solicitud de revisión';
                $icon = 'mail';
                $badge = 'Cambios solicitados';
                $badgeStyle = Style::WARNING;
                $actor = $event->actor?->displayName ?? $event->actor?->name ?? 'Revisor';
                $comment = $event->review_comment ? "«{$event->review_comment}»" : '';
                $subtitle = trim("{$actor}: {$comment}");
                break;

            case PostHistoryEventType::REVIEW_APPROVED:
                $label = 'Revisión aprobada';
                $icon = 'check';
                $badge = 'Aprobado';
                $badgeStyle = Style::SUCCESS;
                $subtitle = $event->summary ?? '';
                break;

            case PostHistoryEventType::REVIEW_REQUESTED:
                $label = 'Enviado a revisión';
                $icon = 'send';
                $badge = 'En revisión';
                $badgeStyle = Style::INFO;
                $subtitle = $event->summary ?? '';
                break;

            case PostHistoryEventType::GENERATE_REQUESTED:
                $label = 'Generación solicitada';
                $icon = 'sparkles';
                $badge = 'Solicitado';
                $badgeStyle = Style::INFO;
                $subtitle = $event->extra_instructions ?? $event->summary ?? '';
                $disabled = true;
                break;

            case PostHistoryEventType::GENERATING_SCRIPT:
                $label = 'Generando guion';
                $icon = 'tag';
                $badge = 'En proceso';
                $badgeStyle = Style::INFO;
                $subtitle = 'Generando estructura con IA';
                $disabled = true;
                break;

            case PostHistoryEventType::GENERATION_FAILED:
                $label = 'Error de generación';
                $icon = 'alert-circle';
                $badge = 'Falló';
                $badgeStyle = Style::DANGER;
                $subtitle = $event->summary ?? '';
                break;

            case PostHistoryEventType::PUBLISHED:
                $label = 'Publicado';
                $icon = 'check-circle';
                $badge = 'Publicado';
                $badgeStyle = Style::SUCCESS;
                $subtitle = $event->summary ?? '';
                break;

            default:
                $label = $event->summary ?? 'Evento';
                $subtitle = $event->extra_instructions ?? '';
                break;
        }

        return ListItemDTO::make(
            id: (string) $event->id,
            label: $label,
            subtitle: $subtitle !== '' ? $subtitle : null,
            icon: $icon,
            badge: $badge,
            badgeStyle: $badgeStyle,
            disabled: $disabled
        );
    }

    public function getPostEventHistory(?Authenticatable $user, int $post_id): Collection
    {
        return $this->postHistoryRepository->get([
            'post_id' => $post_id,
        ]);
    }

    public function getPostEventHistoryById(?Authenticatable $user, int $post_id, int $event_id): ?PostHistoryEventDTO
    {
        return $this->postHistoryRepository->findById($event_id);
    }

    public function createPostEventHistory(?Authenticatable $user, int $postId, PostHistoryEventType $eventType, ?Authenticatable $actor): PostHistoryEventDTO
    {
        return $this->postHistoryRepository->create([
            'post_id' => $postId,
            'event_type' => $eventType,
            'actor_id' => optional($actor)->getAuthIdentifier(),
        ]);
    }

    public function requestGenerateReelScript(?Authenticatable $user, int $postId, ?string $extra_instructions): PostHistoryEventDTO
    {
        return $this->postHistoryRepository->create([
            'post_id' => $postId,
            'event_type' => PostHistoryEventType::GENERATE_REQUESTED,
            'actor_id' => optional($user)->getAuthIdentifier(),
            'extra_instructions' => $extra_instructions,
        ]);
    }

    public function createGeneratingReelScriptEvent(PostHistoryEventDTO $previousEvent): PostHistoryEventDTO
    {
        if ($previousEvent->event_type !== PostHistoryEventType::GENERATE_REQUESTED) {
            throw new \InvalidArgumentException('El evento anterior debe ser de tipo GENERATE_REQUESTED.');
        }

        return $this->postHistoryRepository->create([
            'post_id' => $previousEvent->post_id,
            'event_id' => $previousEvent->id,
            'event_type' => PostHistoryEventType::GENERATING_SCRIPT,
        ]);
    }

    public function createCompletedReelScriptEvent(PostHistoryEventDTO $previousEvent, array $reelScript): PostHistoryEventDTO
    {
        if ($previousEvent->event_type !== PostHistoryEventType::GENERATING_SCRIPT) {
            throw new \InvalidArgumentException('El evento anterior debe ser de tipo GENERATING_SCRIPT.');
        }

        $lastVersionEvent = $this->getLastGeneratedVersionEvent(null, $previousEvent->post_id);
        $nextVersion = $lastVersionEvent ? ($lastVersionEvent->version ?? 1) + 1 : 1;

        return $this->postHistoryRepository->create([
            'post_id' => $previousEvent->post_id,
            'event_id' => $previousEvent->id,
            'event_type' => PostHistoryEventType::VERSION_GENERATED,
            'version' => $nextVersion,
            'reel_script' => $reelScript,
        ]);
    }

    public function createFailedReelScriptEvent(PostHistoryEventDTO $previousEvent, string $errorMessage): PostHistoryEventDTO
    {
        if ($previousEvent->event_type !== PostHistoryEventType::GENERATING_SCRIPT) {
            throw new \InvalidArgumentException('El evento anterior debe ser de tipo GENERATING_SCRIPT.');
        }

        return $this->postHistoryRepository->create([
            'post_id' => $previousEvent->post_id,
            'event_id' => $previousEvent->id,
            'event_type' => PostHistoryEventType::GENERATION_FAILED,
            'summary' => $errorMessage,
        ]);
    }
}
