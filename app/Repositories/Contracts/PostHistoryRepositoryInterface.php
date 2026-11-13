<?php

namespace App\Repositories\Contracts;

use App\DTOs\Posts\PostHistoryEventDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface PostHistoryRepositoryInterface
{
    public function findById(int $id): ?PostHistoryEventDTO;

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): PostHistoryEventDTO;

    /**
     * Obtiene los eventos históricos de un post.
     *
     * @param array<string, mixed> $filters
     * @return Collection<int, PostHistoryEventDTO>
     */
    public function get(array $filters = []): Collection;

    /**
     * Obtiene los eventos históricos paginados.
     *
     * @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, PostHistoryEventDTO>
     */
    public function paginate(
        array $filters = [],
        int $perPage = 15
    ): LengthAwarePaginator;

    /**
     * Obtiene exclusivamente las versiones generadas de un post.
     *
     * @return Collection<int, PostHistoryEventDTO>
     */
    public function getVersions(int $postId): Collection;
}
