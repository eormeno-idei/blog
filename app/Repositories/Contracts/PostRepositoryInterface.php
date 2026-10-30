<?php

namespace App\Repositories\Contracts;

use App\DTOs\Posts\PostDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface PostRepositoryInterface
{
    public function findById(int $id): ?PostDTO;
    public function delete(int $id): bool;
    public function create(array $data): PostDTO;
    public function update(int $id, array $data): ?PostDTO;

    /**
     * Obtiene una colección de PostDTOs basada en filtros.
     * @return Collection<int, PostDTO>
     */
    public function get(array $filters = []): Collection;

    /**
     * Obtiene resultados paginados de PostDTOs.
     * @return LengthAwarePaginator
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Obtiene un array con los títulos de los posts de un usuario que coincidan con un prefijo.
     *
     * @return array<int, string>
     */
    public function getTitlesByPrefix(int $userId, string $prefix): array;
}
