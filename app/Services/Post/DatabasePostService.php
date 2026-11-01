<?php

namespace App\Services\Post;

use App\Contracts\PostServiceInterface;
use App\DTOs\Posts\PostDTO;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

/**
 * Legacy Database Post Service Adapter.
 *
 * @deprecated Use PostService instead.
 */
class DatabasePostService implements PostServiceInterface
{
    public function __construct(
        protected PostService $postService
    ) {}

    /**
     * @return Collection<int, PostDTO>
     */
    public function getLatestPosts(?Authenticatable $user = null, string $filter = ''): Collection
    {
        return $this->postService->getLatestPosts($user, $filter);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createPost(array $data): int
    {
        return $this->postService->createPost($data);
    }

    public function findById(int $id): ?PostDTO
    {
        return $this->postService->findById($id);
    }

    public function isCurrentUserOwner(int $id, ?Authenticatable $user): bool
    {
        return $this->postService->isCurrentUserOwner($id, $user);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updatePost(int $id, array $data, ?Authenticatable $user = null): bool
    {
        return $this->postService->updatePost($id, $data, $user);
    }

    public function requestChanges(int $postId, Authenticatable $reviewer, string $comment): bool
    {
        return $this->postService->requestChanges($postId, $reviewer, $comment);
    }

    public function approvePost(int $postId, Authenticatable $reviewer): bool
    {
        return $this->postService->approvePost($postId, $reviewer);
    }

    public function archiveExpiredPosts(Authenticatable $user): int
    {
        return $this->postService->archiveExpiredPosts($user);
    }

    public function deletePost(int $id, Authenticatable $user): bool
    {
        return $this->postService->deletePost($id, $user);
    }
}
