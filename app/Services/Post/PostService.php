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
use Illuminate\Support\Facades\Auth;
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

        $user = $user ?? Auth::user();

        // 1. Caso Guest (Sin autenticar): Solo posts publicados
        if (!$user) {
            $filters['status'] = PostStatus::PUBLISHED->value;

            return $this->fetchAndSortByUpdated($filters);
        }

        /** @var \App\Models\User $user */

        // 2. Alcance global (Admin o Reviewer): Ver posts no draft de cualquier usuario + borradores propios
        if ($user->can('post.view.any') || $user->can('post.review') || $user->hasRole(['admin', 'reviewer'])) {
            $filters['own_drafts_only_user_id'] = $user->getAuthIdentifier();
            return $this->fetchAndSortByUpdated($filters);
        }

        // 3. Alcance personal (Publisher)
        if ($user->can('post.view.own')) {
            $filters['user_id'] = $user->getAuthIdentifier();

            return $this->fetchAndSortByUpdated($filters);
        }

        // 4. Fallback (Usuario autenticado sin permisos de gestión): solo posts públicos
        $filters['status'] = PostStatus::PUBLISHED->value;
        return $this->fetchAndSortByUpdated($filters);
    }

    /**
     * Helper privado para obtener y ordenar la colección.
     *
     * @param array<string, mixed> $filters
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
        $post = $this->postRepository->findById($id);
        if ($post !== null && $post->to !== null && $post->to->isPast()) {
            $currentStatus = is_string($post->status) ? $post->status : $post->status->value;
            if ($currentStatus === PostStatus::PUBLISHED->value) {
                $this->postRepository->update($id, ['status' => PostStatus::ARCHIVED->value]);

                return $this->postRepository->findById($id);
            }
        }

        return $post;
    }

    public function createPost(array $data): int
    {
        $userId = is_numeric($data['user_id'] ?? null) ? (int) $data['user_id'] : 0;

        if (empty($data['title'])) {
            $data['title'] = $this->nextPostTitle($userId);
        }

        if (empty($data['content'])) {
            $data['content'] = $this->getDefaultContent();
        }

        $postDto = $this->postRepository->create($data);

        return $postDto->id;
    }

    public function isCurrentUserOwner(int $id, ?Authenticatable $user): bool
    {
        if (!$user) {
            return false;
        }

        $post = $this->findById($id);

        if (!$post) {
            return false;
        }

        return $user->getAuthIdentifier() === $post->author?->id;
    }

    /**
     * @inheritDoc
     */
    public function updatePost(int $id, array $data, ?Authenticatable $user = null): bool
    {
        $post = $this->postRepository->findById($id);

        if (!$post) {
            throw new ModelNotFoundException("No se encontró el post con ID {$id}.");
        }

        $user = $user ?? Auth::user();
        if ($user !== null) {
            Gate::forUser($user)->authorize('update', $post);
        }

        $post = $this->postRepository->update($id, $data);

        return $post !== null;
    }

    public function requestChanges(int $postId, ?Authenticatable $reviewer, string $comment): bool
    {
        $data = [
            'status' => PostStatus::CHANGES_REQUESTED->value,
            'reviewer_comment' => $comment,
            'reviewed_by' => $reviewer?->getAuthIdentifier(),
            'reviewed_at' => CarbonImmutable::now(),
        ];

        return $this->updatePost($postId, $data, $reviewer);
    }

    public function approvePost(int $postId, Authenticatable $reviewer): bool
    {
        $data = [
            'status' => PostStatus::PUBLISHED->value,
            'reviewer_comment' => null, // Limpiamos comentarios anteriores si los hubiera
            'reviewed_by' => $reviewer->getAuthIdentifier(),
            'reviewed_at' => CarbonImmutable::now(),
        ];

        return $this->updatePost($postId, $data, $reviewer);
    }

    public function archiveExpiredPosts(Authenticatable $user): int
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
            $this->updatePost($post->id, ['status' => PostStatus::ARCHIVED->value], $user);
            $archivedCount++;
        }

        return $archivedCount;
    }

    public function deletePost(int $id, Authenticatable $user): bool
    {
        $post = $this->postRepository->findById($id);

        if (!$post) {
            throw new ModelNotFoundException("No se encontró el post con ID {$id}.");
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

        return "{$defaultTitle} {$maxNumber}";
    }

    /**
     * Devuelve la plantilla inicial de contenido.
     */
    private function getDefaultContent(): string
    {
        return t('post.default_content');
    }
}
