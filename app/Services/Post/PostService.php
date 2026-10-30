<?php

namespace App\Services\Post;

use App\Contracts\PostServiceInterface;
use App\DTOs\Posts\PostDTO;
use App\Enums\PostStatus;
use App\Repositories\Contracts\PostRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;

class PostService implements PostServiceInterface
{
    public function __construct(
        private readonly PostRepositoryInterface $postRepository
    ) {
    }

    /**
     * @return Collection<int, PostDTO>
     */
    public function getLatestPosts(?Authenticatable $user = null, string $filter = ''): Collection
    {
        $filters = [];

        if (!empty($filter)) {
            $filters['search'] = $filter;
        }

        // 1. Caso Guest (Sin autenticar): Solo posts publicados
        if (!$user) {
            $filters['status'] = PostStatus::PUBLISHED->value;

            return $this->fetchAndSortByUpdated($filters);
        }

        /** @var \App\Models\User $user */

        // 2. Caso Admin o Reviewer: Todos los posts enviados a revisión
        if ($user->hasRole('admin') || $user->hasRole('reviewer')) {
            $filters['status'] = PostStatus::PENDING_REVIEW->value;

            return $this->fetchAndSortByUpdated($filters);
        }

        // 3. Caso Usuario Normal: Sus propios posts
        $filters['user_id'] = $user->getAuthIdentifier();

        return $this->fetchAndSortByUpdated($filters);
    }

    /**
     * Helper privado para obtener y ordenar la colección.
     *
     * @return Collection<int, PostDTO>
     */
    private function fetchAndSortByUpdated(array $filters): Collection
    {
        return $this->postRepository->get($filters)
            ->sortByDesc(function (PostDTO $post) {
                // Ordenamos por updatedAt, y si es null, caemos a createdAt o ahora
                return $post->updatedAt ?? $post->createdAt ?? CarbonImmutable::now();
            })
            ->values(); // values() resetea los índices del array tras el sort
    }

    public function findById(int $id): ?PostDTO
    {
        return $this->postRepository->findById($id);
    }

    public function createPost(array $data): int
    {
        $userId = $data['user_id'];

        if (empty($data['title'])) {
            $data['title'] = $this->nextPostTitle($userId);
        }

        if (empty($data['content'])) {
            $data['content'] = $this->getDefaultContent();
        }

        $postDto = $this->postRepository->create($data);

        return $postDto->id;
    }

    public function isCurrentUserOwner(int $id, Authenticatable $user): bool
    {
        $post = $this->findById($id);

        if (!$post) {
            return false;
        }

        return $user->getAuthIdentifier() === $post->author->id;
    }

    public function updatePost(int $id, array $data): bool
    {
        $post = $this->postRepository->update($id, $data);

        return $post !== null;
    }

    public function requestChanges(int $postId, int $reviewerId, string $comment): bool
    {
        $data = [
            'status' => PostStatus::CHANGES_REQUESTED->value,
            'reviewer_comment' => $comment,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => CarbonImmutable::now(),
        ];

        return $this->updatePost($postId, $data);
    }

    public function approvePost(int $postId, int $reviewerId): bool
    {
        $data = [
            'status' => PostStatus::PUBLISHED->value,
            'reviewer_comment' => null, // Limpiamos comentarios anteriores si los hubiera
            'reviewed_by' => $reviewerId,
            'reviewed_at' => CarbonImmutable::now(),
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

        if (!$post) {
            throw new ModelNotFoundException("Post with ID {$id} not found.");
        }

        // Lanzará automáticamente una AuthorizationException (403) si falla la Policy
        Gate::forUser($user)->authorize('delete', $post);

        return $this->postRepository->delete($id);
    }

    /**
     * Calcula el próximo título disponible para el borrador del usuario.
     */
    private function nextPostTitle(int $userId): string
    {
        $defaultTitle = t('post.default_title');

        // Delegamos la consulta a la capa de infraestructura (Repositorio)
        $titlesStartingWithDefault = $this->postRepository->getTitlesByPrefix($userId, $defaultTitle);

        $maxNumber = 0;

        foreach ($titlesStartingWithDefault as $title) {
            if (preg_match('/^' . preg_quote($defaultTitle, '/') . ' (\d+)$/', $title, $matches)) {
                $number = (int) $matches[1];
                if ($number > $maxNumber) {
                    $maxNumber = $number;
                }
            }
        }

        $maxNumber++;

        return $maxNumber > 0 ? "{$defaultTitle} {$maxNumber}" : $defaultTitle;
    }

    /**
     * Devuelve la plantilla inicial de contenido.
     */
    private function getDefaultContent(): string
    {
        return t('post.default_content');
    }
}
