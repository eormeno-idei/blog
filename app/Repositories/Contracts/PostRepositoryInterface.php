<?php

namespace App\Repositories\Contracts;

use App\DTOs\Posts\PostDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface PostRepositoryInterface
{
    public function findById(int $id, array $with = []): ?PostDTO;
    public function delete(int $id): bool;
    public function create(array $data): PostDTO;

    /**
     * Obtiene una colección de PostDTOs basada en filtros.
     *
     * @return Collection<int, PostDTO>
     */
    public function get(array $filters = [], array $with = []): Collection;

    /**
     * Obtiene resultados paginados de PostDTOs.
     *
     * @return LengthAwarePaginator
     */
    public function paginate(array $filters = [], array $with = [], int $perPage = 15): LengthAwarePaginator;
}
