<?php

namespace App\Contracts;

use Illuminate\Support\Collection;

interface PostServiceInterface
{
    /**
     * Obtiene los últimos posts ordenados por fecha descendente.
     * 
     * @param int $ownerId El ID del propietario de los posts. Si es 0, se obtienen todos los posts.
     * @param string $filter Un filtro para buscar posts específicos.
     * @return Collection Una colección de objetos que representan los posts.
     */
    public function getLatestPosts(int $ownerId = 0, string $filter=''): Collection;

    /**
     * Busca un post por su ID.
     */
    public function findById(int $id): ?object;

    /**
     * Crea un nuevo post y devuelve su ID.
     *
     * @param array{user_id: int, title: string, content: string, image: ?string} $data
     */
    public function createPost(array $data): int;

    /**
     * Elimina un post por su ID.
     */
    public function deletePost(int $id): bool;
}

