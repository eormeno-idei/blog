<?php

namespace App\Repositories\Eloquent;

use App\DTOs\Posts\PostHistoryEventDTO;
use App\Enums\PostHistoryEventType;
use App\Models\PostHistory;
use App\Repositories\Contracts\PostHistoryRepositoryInterface;
use App\Repositories\Eloquent\Concerns\HasUserDTO;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PostHistoryRepository implements PostHistoryRepositoryInterface
{
    use HasUserDTO;

    /** @var array<int, string> */
    private static array $defaultWith = ['actor'];

    public function findById(int $id): ?PostHistoryEventDTO
    {
        $history = PostHistory::with(self::$defaultWith)->find($id);

        if (!$history) {
            return null;
        }

        return $this->toDTO($history);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): PostHistoryEventDTO
    {
        $history = PostHistory::create($data);
        $history->load(self::$defaultWith);

        return $this->toDTO($history);
    }

    /**
     * @param array<string, mixed> $filters
     * @return Collection<int, PostHistoryEventDTO>
     */
    public function get(array $filters = []): Collection
    {
        $query = $this->buildFilterQuery($filters);

        /** @var \Illuminate\Database\Eloquent\Collection<int, PostHistory> $histories */
        $histories = $query
            ->orderBy('created_at', 'desc')
            ->get();

        return $histories->map(
            fn(PostHistory $history) => $this->toDTO($history)
        );
    }

    /**
     * Obtiene exclusivamente las versiones generadas de un post.
     *
     * @return Collection<int, PostHistoryEventDTO>
     */
    public function getVersions(int $postId): Collection
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, PostHistory> $histories */
        $histories = PostHistory::with(self::$defaultWith)
            ->where('post_id', $postId)
            ->where(
                'event_type',
                PostHistoryEventType::VERSION_GENERATED->value
            )
            ->orderBy('version_major')
            ->orderBy('version_minor')
            ->get();

        return $histories->map(
            fn(PostHistory $history) => $this->toDTO($history)
        );
    }

    /**
     * Convierte el modelo Eloquent en PostHistoryEventDTO.
     */
    private function toDTO(PostHistory $history): PostHistoryEventDTO
    {
        return new PostHistoryEventDTO(
            id: $history->id,
            postId: $history->post_id,
            eventType: $history->event_type,
            version: $history->version,
            basedOnHistoryId: $history->based_on_history_id,
            relatedVersionId: $history->related_version_id,
            summary: $history->summary,
            instruction: $history->instruction,
            reviewComment: $history->review_comment,
            contentSnapshot: \is_array($history->content_snapshot)
            ? (json_encode($history->content_snapshot) ?: null)
            : (\is_string($history->content_snapshot) ? $history->content_snapshot : null),
            createdAt: $history->created_at
            ? CarbonImmutable::parse($history->created_at)
            : null,
            updatedAt: $history->updated_at
            ? CarbonImmutable::parse($history->updated_at)
            : null,
            actor: $this->toUserDTO($history->actor),
        );
    }

    /**
     * @param array<string, mixed> $filters
     * @return Builder<PostHistory>
     */
    private function buildFilterQuery(array $filters): Builder
    {
        $query = PostHistory::with(self::$defaultWith);

        if (isset($filters['post_id'])) {
            $query->where('post_id', $filters['post_id']);
        }

        if (isset($filters['actor_id'])) {
            $query->where('actor_id', $filters['actor_id']);
        }

        if (isset($filters['actor_role'])) {
            $query->where('actor_role', $filters['actor_role']);
        }

        if (isset($filters['event_type'])) {
            $eventType = $filters['event_type'];

            if ($eventType instanceof PostHistoryEventType) {
                $eventType = $eventType->value;
            }

            $query->where('event_type', $eventType);
        }

        if (isset($filters['version'])) {
            $query->where('version', $filters['version']);
        }

        if (isset($filters['based_on_history_id'])) {
            $query->where(
                'based_on_history_id',
                $filters['based_on_history_id']
            );
        }

        if (isset($filters['related_version_id'])) {
            $query->where(
                'related_version_id',
                $filters['related_version_id']
            );
        }

        if (isset($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (isset($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        return $query;
    }
}
