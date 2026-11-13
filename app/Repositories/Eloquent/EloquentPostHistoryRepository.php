<?php

namespace App\Repositories\Eloquent;

use App\Models\PostHistory;
use App\Models\User;
use App\DTOs\Posts\PostHistoryEventDTO;
use App\DTOs\User\UserDTO;
use App\Enums\PostHistoryEventType;
use App\Repositories\Contracts\PostHistoryRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Carbon\CarbonImmutable;

class EloquentPostHistoryRepository implements PostHistoryRepositoryInterface
{
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
     * @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, PostHistoryEventDTO>
     */
    public function paginate(
        array $filters = [],
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = $this->buildFilterQuery($filters);

        $paginator = $query
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $histories = $paginator->getCollection()->map(
            fn(PostHistory $history) => $this->toDTO($history)
        );

        $paginator->setCollection($histories);

        /** @var LengthAwarePaginator<int, PostHistoryEventDTO> $paginator */
        return $paginator;
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
            actorRole: $history->actor_role,
            versionMajor: $history->version_major,
            versionMinor: $history->version_minor,
            basedOnHistoryId: $history->based_on_history_id,
            relatedVersionId: $history->related_version_id,
            summary: $history->summary,
            instruction: $history->instruction,
            reviewComment: $history->review_comment,
            contentSnapshot: is_array($history->content_snapshot)
            ? (json_encode($history->content_snapshot) ?: null)
            : (is_string($history->content_snapshot) ? $history->content_snapshot : null),
            metadata: is_array($history->metadata)
            ? (json_encode($history->metadata) ?: null)
            : (is_string($history->metadata) ? $history->metadata : null),
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

        if (isset($filters['version_major'])) {
            $query->where('version_major', $filters['version_major']);
        }

        if (isset($filters['version_minor'])) {
            $query->where('version_minor', $filters['version_minor']);
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

    private function toUserDTO(?User $user): ?UserDTO
    {
        if (!$user) {
            return null;
        }

        return new UserDTO(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            emailVerifiedAt: $user->email_verified_at
            ? CarbonImmutable::parse($user->email_verified_at)
            : null,
            termsAcceptedAt: $user->terms_accepted_at
            ? CarbonImmutable::parse($user->terms_accepted_at)
            : null,
            isRoot: $user->isRoot(),
            isRegisteredInLobby: $user->isRegisteredInLobby(),
            displayName: $user->getDisplayName(),
            createdAt: $user->created_at
            ? CarbonImmutable::parse($user->created_at)
            : null,
            updatedAt: $user->updated_at
            ? CarbonImmutable::parse($user->updated_at)
            : null,
        );
    }
}
