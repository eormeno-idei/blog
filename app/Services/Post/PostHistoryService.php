<?php

namespace App\Services\Post;

use App\Contracts\PostHistoryServiceInterface;
use App\DTOs\Posts\PostHistoryEventDTO;
use App\Enums\PostHistoryEventType;
use App\Repositories\Contracts\PostHistoryRepositoryInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

class PostHistoryService implements PostHistoryServiceInterface
{
    public function __construct(
        private readonly PostHistoryRepositoryInterface $postHistoryRepository
    ) {
    }

    public function hasFirstDraftEvent(?Authenticatable $user, int $post_id): bool
    {
        $events = $this->postHistoryRepository->get([
            'post_id' => $post_id,
            'event_type' => PostHistoryEventType::VERSION_GENERATED,
            'version' => 1,
        ]);

        return $events->isNotEmpty();
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

        return $this->postHistoryRepository->create([
            'post_id' => $previousEvent->post_id,
            'event_id' => $previousEvent->id,
            'event_type' => PostHistoryEventType::VERSION_GENERATED,
            'reel_script' => $reelScript,
        ]);
    }
}
