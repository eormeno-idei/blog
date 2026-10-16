<?php

// @usim: feature="admin", type="service"

namespace App\Services\Units;

use Idei\Usim\Contracts\UnitsServiceInterface;
use Idei\Usim\Models\UsimUnit;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class UnitsService implements UnitsServiceInterface
{
    public function __construct(
        protected UnitTranslationGenerator $translationGenerator,
        protected ?DatabaseManager $database = null
    ) {}

    /**
     * Determine whether organizational units (teams) are enabled in the application.
     */
    public function isTeamsEnabled(): bool
    {
        return (bool) config('permission.teams', false);
    }

    /**
     * Synchronize organizational units based on the given structure or config.
     *
     * @param array<string, array{
     *     type?: string|null,
     *     parent?: string|null,
     *     default_translations?: array<string, mixed>
     * }>|null $structure
     * @param  (callable(int $current, int $total, string $slug): void)|null  $onProgress
     * @param  string|null  $baseLangPath  Optional base directory for translation files
     */
    public function sync(?array $structure = null, ?callable $onProgress = null, ?string $baseLangPath = null): UnitSyncResult
    {
        if (! $this->isTeamsEnabled()) {
            return UnitSyncResult::skipped(
                'Units are disabled in the Spatie (permission.php) configuration. Skipping unit synchronization.'
            );
        }

        /** @var array<string, array{
         *     type?: string|null,
         *     parent?: string|null,
         *     default_translations?: array<string, mixed>
         * }> $resolvedStructure
         */
        $resolvedStructure = $structure ?? $this->getStructure();
        $configuredSlugs = array_keys($resolvedStructure);

        $runInTransaction = function () use ($resolvedStructure, $configuredSlugs, $onProgress, $baseLangPath): UnitSyncResult {
            // 1. DELETE: Desvincular padres de obsoletos para evitar eliminación accidental por cascada, y luego eliminarlos
            $deletedCount = $this->deleteObsoleteUnits($configuredSlugs);

            // 2. UPSERT: Crear o actualizar unidades básicas (sin relaciones todavía)
            $upsertedCount = $this->upsertUnits($resolvedStructure, $onProgress);

            // 3. HIERARCHY: Actualizar relaciones padre-hijo
            $hierarchyUpdatedCount = $this->updateHierarchy($resolvedStructure);

            // 4. I18N: Generar archivos físicos de idioma
            $generatedFiles = $this->syncTranslations($resolvedStructure, 'unit', $baseLangPath);

            return new UnitSyncResult(
                skipped: false,
                deletedCount: $deletedCount,
                upsertedCount: $upsertedCount,
                hierarchyUpdatedCount: $hierarchyUpdatedCount,
                generatedTranslationFiles: $generatedFiles
            );
        };

        if ($this->database !== null) {
            return $this->database->transaction($runInTransaction);
        }

        return DB::transaction($runInTransaction);
    }

    /**
     * Remove units from the database that are no longer present in the configuration.
     *
     * @param  array<int, string>  $configuredSlugs
     */
    public function deleteObsoleteUnits(array $configuredSlugs): int
    {
        $obsoleteIds = UsimUnit::whereNotIn('slug', $configuredSlugs)->pluck('id');

        if ($obsoleteIds->isEmpty()) {
            return 0;
        }

        // Desvincular parent_id en cualquier unidad que apunte a una unidad obsoleta
        // para prevenir que cascadeOnDelete en la BD elimine accidentalmente unidades hijas vigentes
        UsimUnit::whereIn('parent_id', $obsoleteIds)->update(['parent_id' => null]);

        /** @var int $deleted */
        $deleted = UsimUnit::whereIn('id', $obsoleteIds)->delete();

        return $deleted;
    }

    /**
     * Create or update units from the given structure.
     *
     * @param array<string, array{
     *     type?: string|null,
     *     parent?: string|null,
     *     default_translations?: array<string, mixed>
     * }> $structure
     * @param  (callable(int $current, int $total, string $slug): void)|null  $onProgress
     */
    public function upsertUnits(array $structure, ?callable $onProgress = null): int
    {
        $total = count($structure);
        $current = 0;

        foreach ($structure as $slug => $data) {
            if (trim($slug) === '') {
                continue;
            }

            UsimUnit::updateOrCreate(
                ['slug' => $slug],
                ['type' => $data['type'] ?? null]
            );

            $current++;

            if ($onProgress !== null) {
                $onProgress($current, $total, $slug);
            }
        }

        return $current;
    }

    /**
     * Update parent-child relationships according to the structure.
     *
     * @param array<string, array{
     *     type?: string|null,
     *     parent?: string|null,
     *     default_translations?: array<string, mixed>
     * }> $structure
     */
    public function updateHierarchy(array $structure): int
    {
        $updated = 0;

        foreach ($structure as $slug => $data) {
            if (trim($slug) === '') {
                continue;
            }

            $parentId = null;
            if (! empty($data['parent'])) {
                $parentId = UsimUnit::where('slug', $data['parent'])->value('id');
            }

            UsimUnit::where('slug', $slug)->update(['parent_id' => $parentId]);
            $updated++;
        }

        return $updated;
    }

    /**
     * Generate unit translation files for all configured locales.
     *
     * @param array<string, array{
     *     type?: string|null,
     *     parent?: string|null,
     *     default_translations?: array<string, mixed>
     * }> $structure
     * @param  string|null  $baseLangPath  Optional base directory for translation files
     * @return array<int, string> List of generated file paths
     */
    public function syncTranslations(array $structure, string $filePrefix = 'unit', ?string $baseLangPath = null): array
    {
        return $this->translationGenerator->generate($structure, $filePrefix, $baseLangPath);
    }

    /**
     * Retrieve the configured units structure.
     *
     * @return array<string, array{
     *     type?: string|null,
     *     parent?: string|null,
     *     default_translations?: array<string, mixed>
     * }>
     */
    public function getStructure(): array
    {
        $structure = config('usim.units.structure', []);

        /** @var array<string, array{
         *     type?: string|null,
         *     parent?: string|null,
         *     default_translations?: array<string, mixed>
         * }> $resolved
         */
        $resolved = is_array($structure) ? $structure : [];

        return $resolved;
    }

    /**
     * Find a unit by its slug.
     */
    public function findBySlug(string $slug): ?UsimUnit
    {
        return UsimUnit::where('slug', $slug)->first();
    }

    /**
     * Return all units.
     *
     * @return Collection<int, UsimUnit>
     */
    public function all(): Collection
    {
        return UsimUnit::all();
    }
}
