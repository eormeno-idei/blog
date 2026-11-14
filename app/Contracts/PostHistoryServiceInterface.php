<?php

namespace App\Contracts;

use App\DTOs\Posts\PostHistoryEventDTO;
use App\Enums\PostHistoryEventType;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

interface PostHistoryServiceInterface
{

    /**
     * Verifica si un post tiene un evento de borrador inicial.
     *
     * @param  ?Authenticatable  $user
     * @param  int  $post_id
     * @return bool
     */
    public function hasFirstDraftEvent(?Authenticatable $user, int $post_id): bool;

    /**
     * Obtiene el historial de eventos de un post.
     *
     * @param  ?Authenticatable  $user
     * @param  int  $post_id
     * @return Collection<int, PostHistoryEventDTO>
     */
    public function getPostEventHistory(?Authenticatable $user, int $post_id): Collection;

    /**
     * Obtiene un evento específico del historial de un post.
     *
     * @param  ?Authenticatable  $user
     * @param  int  $post_id
     * @param  int  $event_id
     * @return ?PostHistoryEventDTO
     */
    public function getPostEventHistoryById(?Authenticatable $user, int $post_id, int $event_id): ?PostHistoryEventDTO;

    /**
     * Crea un nuevo evento en el historial de un post.
     *
     * @param  ?Authenticatable  $user
     * @param  int  $postId
     * @param  PostHistoryEventType  $eventType
     * @param  ?Authenticatable  $actor
     * @return PostHistoryEventDTO
     */
    public function createPostEventHistory(?Authenticatable $user, int $postId, PostHistoryEventType $eventType, ?Authenticatable $actor): PostHistoryEventDTO;

}
