<?php

namespace App\Contracts;

use App\DTOs\Posts\PostDTO;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

interface PostServiceInterface
{
    /**
     * Obtiene los últimos posts ordenados por fecha descendente.
     *
     * @param  ?Authenticatable  $user  El usuario actual, o null si no hay usuario autenticado.
     * @param  string  $filter  Filtro de búsqueda.
     * @return Collection<int, PostDTO>
     */
    public function getLatestPosts(?Authenticatable $user, string $filter = ''): Collection;

    /**
     * Encuentra un post por su ID.
     *
     * @param  int  $id
     * @return ?PostDTO
     */
    public function findById(int $id): ?PostDTO;

    /**
     * Crea un nuevo post.
     *
     * @param  array  $data
     * @return int  El ID del post creado.
     */
    public function createPost(array $data): int;

    /**
     * Verifica si el usuario actual es el propietario del post.
     *
     * @param  int  $id
     * @param  Authenticatable  $user
     * @return bool
     */
    public function isCurrentUserOwner(int $id, Authenticatable $user): bool;

    /**
     * Actualiza un post existente.
     *
     * @param  int  $id
     * @param  array  $data
     * @return bool
     */
    public function updatePost(int $id, array $data): bool;

    /**
     * Solicita cambios en un post.
     *
     * @param  int  $postId
     * @param  int  $reviewerId
     * @param  string  $comment
     * @return bool
     */
    public function requestChanges(int $postId, int $reviewerId, string $comment): bool;

    /**
     * Aprueba un post.
     *
     * @param  int  $postId
     * @param  int  $reviewerId
     * @return bool
     */
    public function approvePost(int $postId, int $reviewerId): bool;

    /**
     * Archiva los posts que han expirado.
     *
     * @return int  El número de posts archivados.
     */
    public function archiveExpiredPosts(): int;

    /**
     * Elimina un post si el usuario actual es el propietario, o tiene permisos
     * de administrador y el post no está publicado.
     *
     * @param  int  $id
     * @param  Authenticatable  $user
     * @return bool
     */
    public function deletePost(int $id, Authenticatable $user): bool;
}
