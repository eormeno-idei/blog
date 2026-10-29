<?php

namespace App\Services;

use App\Contracts\PostServiceInterface;
use App\DTOs\Posts\PostDTO;
use App\Enums\PostStatus;
use App\Repositories\Contracts\PostRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;

class PostService implements PostServiceInterface
{
    public function __construct(
        private readonly PostRepositoryInterface $postRepository
    ) {}

    public function getLatestPosts(?Authenticatable $user): Collection
    {
        throw new \Exception('Method not implemented yet.');
    }

    public function findById(int $id): ?PostDTO
    {
        return $this->postRepository->findById($id);
    }

    public function createPost(array $data): int
    {
        $post = $this->postRepository->create($data);

        return $post->id;
    }

    public function isCurrentUserOwner(int $id, Authenticatable $user): bool
    {
        $post = $this->findById($id);

        if (! $post) {
            return false;
        }

        return (int) $user->id === $post->author->id;
    }

    public function updatePost(int $id, array $data): bool
    {
        $post = $this->postRepository->update($id, $data);

        return $post !== null;
    }

    public function requestChanges(int $postId, int $reviewerId, string $comment): bool
    {
        $data = [
            'status'           => PostStatus::CHANGES_REQUESTED->value,
            'reviewer_comment' => $comment,
            'reviewed_by'      => $reviewerId,
            'reviewed_at'      => CarbonImmutable::now(),
        ];

        return $this->updatePost($postId, $data);
    }

    public function approvePost(int $postId, int $reviewerId): bool
    {
        $data = [
            'status'           => PostStatus::PUBLISHED->value,
            'reviewer_comment' => null, // Limpiamos comentarios anteriores si los hubiera
            'reviewed_by'      => $reviewerId,
            'reviewed_at'      => CarbonImmutable::now(),
        ];

        return $this->updatePost($postId, $data);
    }

    public function archiveExpiredPosts(): int
    {
        // Obtenemos todos los posts publicados
        $publishedPosts = $this->postRepository->get(['status' => PostStatus::PUBLISHED->value]);

        $now = CarbonImmutable::now();
        $archivedCount = 0;

        // Filtramos en memoria cuáles ya expiraron
        $expiredPosts = $publishedPosts->filter(
            fn(PostDTO $post) => $post->to !== null && $post->to->isPast()
        );

        foreach ($expiredPosts as $post) {
            $this->updatePost($post->id, ['status' => PostStatus::ARCHIVED->value]);
            $archivedCount++;
        }

        return $archivedCount;
    }

    public function deletePost(int $id, Authenticatable $user): bool
    {
        $post = $this->postRepository->findById($id);

        if (! $post) {
            throw new ModelNotFoundException("Post with ID {$id} not found.");
        }

        // Lanzará automáticamente una AuthorizationException (403) si falla la Policy
        Gate::forUser($user)->authorize('delete', $post);

        return $this->postRepository->delete($id);
    }
}
