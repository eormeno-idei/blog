<?php

namespace App\Contracts;

use App\DTOs\Posts\PostDTO;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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
     * @param  ?Authenticatable  $user
     * @return bool
     */
    public function isCurrentUserOwner(int $id, ?Authenticatable $user): bool;

    /**
     * Actualiza un post existente.
     *
     * @param  int  $id
     * @param  array  $data
     * @param  ?Authenticatable  $user
     * @throws ModelNotFoundException
     * @throws AuthorizationException
     * @return bool
     */
    public function updatePost(int $id, array $data, ?Authenticatable $user): bool;

    /**
     * Solicita cambios en un post.
     *
     * @param  int  $postId
     * @param  Authenticatable  $reviewer
     * @param  string  $comment
     * @return bool
     */
    public function requestChanges(int $postId, Authenticatable $reviewer, string $comment): bool;

    /**
     * Aprueba un post.
     *
     * @param  int  $postId
     * @param  Authenticatable  $reviewer
     * @return bool
     */
    public function approvePost(int $postId, Authenticatable $reviewer): bool;

    /**
     * Archiva los posts que han expirado.
     *
     * @param  Authenticatable  $user  El usuario que realiza la acción.
     * @return int  El número de posts archivados.
     */
    public function archiveExpiredPosts(Authenticatable $user): int;

    /**
     * Elimina un post si el usuario actual es el propietario, o tiene permisos
     * de administrador y el post no está publicado.
     *
     * @param  int  $id
     * @param  Authenticatable  $user
     * @throws ModelNotFoundException
     * @throws AuthorizationException
     * @return bool
     */
    public function deletePost(int $id, Authenticatable $user): bool;
}
