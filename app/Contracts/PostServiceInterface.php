<?php

namespace App\Contracts;

use Illuminate\Support\Collection;

interface PostServiceInterface
{
    /**
     * Obtiene los últimos posts ordenados por fecha descendente.
     *
     * @param  int  $ownerId  El ID del propietario de los posts. Si es 0, se obtienen todos los posts.
     * @param  string  $filter  Un filtro para buscar posts específicos.
     * @return Collection<int, \stdClass> Una colección de objetos que representan los posts.
     */
    public function getLatestPosts(int $ownerId = 0, string $filter = ''): Collection;

    /**
     * Busca un post por su ID.
     */
    public function findById(int $id): ?object;

    /**
     * Crea un nuevo post y devuelve su ID.
     *
     * @param  array<string, mixed>  $data
     */
    public function createPost(array $data): int;

    /**
     * Verifica si el usuario actual es el propietario del post.
     * 
     * @param  \stdClass  $post  El objeto del post a verificar.
     * @return bool  Verdadero si el usuario actual es el propietario del post, falso en caso contrario.
     */
    public function isCurrentUserOwner(\stdClass $post): bool;

    /**
     * Actualiza un post existente.
     *
     * @param  array<string, mixed>  $data
     */
    public function updatePost(int $id, array $data): bool;

    /**
     * Solicita modificaciones a un post dejando un comentario del revisor.
     */
    public function requestChanges(int $postId, int $reviewerId, string $comment): bool;

    /**
     * Aprueba y publica un post revisado.
     */
    public function approvePost(int $postId, int $reviewerId): bool;

    /**
     * Archiva todos los posts publicados cuyo período de vigencia (to) ya finalizó.
     *
     * @return int Cantidad de posts archivados.
     */
    public function archiveExpiredPosts(): int;

    /**
     * Elimina un post por su ID.
     */
    public function deletePost(int $id): bool;
}
