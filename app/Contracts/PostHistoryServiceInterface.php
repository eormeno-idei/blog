<?php

namespace App\Contracts;

use App\DTOs\Posts\PostHistoryEventDTO;
use App\Enums\PostHistoryEventType;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

interface PostHistoryServiceInterface
{

    public function findPostEventHistoryById(?Authenticatable $user, int $event_id): ?PostHistoryEventDTO;

    /**
     * Verifica si un post tiene un evento de borrador inicial.
     *
     * @param  ?Authenticatable  $user
     * @param  int  $post_id
     * @return bool
     */
    public function hasFirstDraftEvent(?Authenticatable $user, int $post_id): bool;

    /**
     * Obtiene el último evento de reel de un post.
     *
     * @param  int  $post_id
     * @return ?PostHistoryEventDTO
     */
    public function getLatestReelEvent(int $post_id): ?PostHistoryEventDTO;

    /**
     * Obtiene el último evento de versión generada de un post.
     *
     * @param  ?Authenticatable  $user
     * @param  int  $post_id
     * @return ?PostHistoryEventDTO
     */
    public function getLastGeneratedVersionEvent(?Authenticatable $user, int $post_id): ?PostHistoryEventDTO;

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

    /**
     * Solicita la generación de un guion para un reel y crea un evento correspondiente en el historial del post.
     *
     * @param  ?Authenticatable  $user
     * @param  int  $postId
     * @param  ?string  $extra_instructions
     * @return PostHistoryEventDTO
     */
    public function requestGenerateReelScript(?Authenticatable $user, int $postId, ?string $extra_instructions): PostHistoryEventDTO;

    /**
     * Crea un evento de generación de guion para un reel en el historial del post.
     *
     * @param  PostHistoryEventDTO  $previousEvent
     * @return PostHistoryEventDTO
     */
    public function createGeneratingReelScriptEvent(PostHistoryEventDTO $previousEvent): PostHistoryEventDTO;

    /**
     * Crea un evento de guion completado para un reel en el historial del post.
     *
     * @param  PostHistoryEventDTO  $previousEvent
     * @param  array<string, mixed>  $reelScript
     * @return PostHistoryEventDTO
     */
    public function createCompletedReelScriptEvent(PostHistoryEventDTO $previousEvent, array $reelScript): PostHistoryEventDTO;

    /**
     * Crea un evento de generación de guion fallido para un reel en el historial del post.
     *
     * @param  PostHistoryEventDTO  $previousEvent
     * @param  string  $errorMessage
     * @return PostHistoryEventDTO
     */
    public function createFailedReelScriptEvent(PostHistoryEventDTO $previousEvent, string $errorMessage): PostHistoryEventDTO;
}
