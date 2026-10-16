<?php

namespace App\Contracts;

use Illuminate\Support\Collection;

interface PostServiceInterface
{
    /**
     * Obtiene los últimos posts ordenados por fecha descendente.
     */
    public function getLatestPosts(): Collection;

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

