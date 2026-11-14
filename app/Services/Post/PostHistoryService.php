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
}
