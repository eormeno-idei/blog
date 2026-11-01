<?php

namespace App\Repositories\Contracts;

use App\DTOs\Posts\PostDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface PostRepositoryInterface
{
    public function findById(int $id): ?PostDTO;
    public function delete(int $id): bool;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PostDTO;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): ?PostDTO;

    /**
     * Obtiene una colección de PostDTOs basada en filtros.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, PostDTO>
     */
    public function get(array $filters = []): Collection;

    /**
     * Obtiene resultados paginados de PostDTOs.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, PostDTO>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Obtiene un array con los títulos de los posts de un usuario que coincidan con un prefijo.
     *
     * @return array<int, string>
     */
    public function getTitlesByPrefix(int $userId, string $prefix): array;
}
