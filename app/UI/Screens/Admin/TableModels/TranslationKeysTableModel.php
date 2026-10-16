<?php
// @usim: feature="admin", type="screen"
namespace App\UI\Screens\Admin\TableModels;

use Idei\Usim\Models\UsimTextKey;
use Idei\Usim\Components\Table;
use Idei\Usim\DataTable\AbstractTableModel;
use Idei\Usim\Support\TranslationService;
use Idei\Usim\Support\UIStateManager;
use Illuminate\Database\Eloquent\Builder;

class TranslationKeysTableModel extends AbstractTableModel
{
    protected const MAX_CELL_STRING_LENGTH = 47;
    protected TranslationService $translationService;
    protected const SEARCH_KEY = 'translation_table_search';
    protected const LANGUAGE_FILTER_KEY = 'translation_table_language_filter';
    protected const GROUP_FILTER_KEY = 'translation_table_group_filter';

    /** @var array<string, string> */
    protected array $languages = [];

    /** @var array<int, string> */
    protected array $languageCodes = [];

    protected string $fallbackCode = 'en';

    private const EDIT_ICON_SVG = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
SVG;

    private const DELETE_ICON_SVG = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14H7L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
SVG;

    public function __construct(Table $tableBuilder)
    {
        parent::__construct($tableBuilder);

        $this->translationService = app(TranslationService::class);

        $dataset = $this->translationService->listLanguagesDataset();
        foreach ($dataset['items'] as $language) {
            $code = $this->mixedToString($language['code'] ?? null);
            if ($code === '') {
                continue;
            }

            $name = $this->mixedToString($language['native_name'] ?? null);
            if ($name === '') {
                $name = $this->mixedToString($language['name'] ?? null);
            }
            if ($name === '') {
                $name = strtoupper($code);
            }
            $this->languages[$code] = $name;
            $this->languageCodes[] = $code;

            if ((bool) ($language['is_fallback'] ?? false)) {
                $this->fallbackCode = $code;
            }
        }
    }

    public function getColumns(): array
    {
        $columns = [
            // Fixed width: keep key column stable regardless of content length.
            'key' => ['label' => t('datatable.translation_keys.columns.key'), 'width' => 320, 'sort_by' => 'key'],
            // Fixed width: narrow column showing only the percentage value.
            'completion' => ['label' => t('datatable.translation_keys.columns.completion'), 'width' => 52],
        ];

        // Fallback language column — always visible
        $fallbackName = $this->languages[$this->fallbackCode] ?? strtoupper($this->fallbackCode);
        $columns['lang_fallback'] = [
            'label' => strtoupper($this->fallbackCode) . ' (' . $fallbackName . ')',
            // Fixed width: keep fallback translation column stable regardless of content length.
            'width' => 320,
        ];

        // Selected language column — always present to keep column count stable;
        // collapsed to width 0 when no non-fallback language is selected.
        $selectedCode = $this->getLanguageFilter();
        $hasSelectedLang = $selectedCode !== 'all'
            && $selectedCode !== $this->fallbackCode
            && isset($this->languages[$selectedCode]);

        if ($hasSelectedLang) {
            $selectedName = $this->languages[$selectedCode];
            $columns['lang_selected'] = [
                'label' => strtoupper($selectedCode) . ' (' . $selectedName . ')',
                'width' => 320,
            ];
        } else {
            $columns['lang_selected'] = [
                'label' => '',
                'width' => 0,
            ];
        }

        $columns['edit'] = ['label' => '', 'width' => 20];
        $columns['delete'] = ['label' => '', 'width' => 20];

        return $columns;
    }

    protected function countTotal(): int
    {
        return $this->buildBaseQuery()->count();
    }

    public function setLanguageFilter(?string $languageCode): void
    {
        UIStateManager::storeKeyValue(self::LANGUAGE_FILTER_KEY, $this->normalizeFilterValue($languageCode));
    }

    public function getLanguageFilter(): string
    {
        $value = UIStateManager::getKeyValue(self::LANGUAGE_FILTER_KEY);

        return $this->normalizeFilterValue($this->mixedToNullableString($value));
    }

    public function setGroupFilter(?string $group): void
    {
        UIStateManager::storeKeyValue(self::GROUP_FILTER_KEY, $this->normalizeFilterValue($group));
    }

    public function getGroupFilter(): string
    {
        $value = UIStateManager::getKeyValue(self::GROUP_FILTER_KEY);

        return $this->normalizeFilterValue($this->mixedToNullableString($value));
    }

    public function getPageData(): array
    {
        $rows = $this->getTextKeysPageItems();

        return array_map(
            static fn (UsimTextKey $row): array => [
                'key' => $row->key,
                'group' => $row->group,
            ],
            $rows
        );
    }

    /**
     * @return list<UsimTextKey>
     */
    private function getTextKeysPageItems(): array
    {
        $pagination = $this->tableBuilder->getPaginationData();
        $sortBy = $this->tableBuilder->getSortColumn();
        $sortDirection = $this->tableBuilder->getSortDirection();

        $allowedSorts = ['key'];
        $sortColumn = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'key';
        $direction = strtolower((string) $sortDirection) === 'desc' ? 'desc' : 'asc';

        $query = $this->buildBaseQuery()
            ->with(['values.language'])
            ->orderBy($sortColumn, $direction);

        $items = $query
            ->forPage((int) $pagination['current_page'], (int) $pagination['per_page'])
            ->get()
            ->all();

        return array_values($items);
    }

    public function getFormattedPageData(int $currentPage, int $perPage): array
    {
        $rows = $this->getTextKeysPageItems();
        $formatted = [];

        foreach ($rows as $row) {
            $rowData = [
                'key' => $this->truncateCellString((string) $row->key),
            ];

            $valuesByCode = [];
            foreach ($row->values as $value) {
                $code = $value->language?->code;
                if (!$code) {
                    continue;
                }

                $valuesByCode[$code] = [
                    'text' => $value->text_value,
                    'needs_review' => (bool) $value->needs_review,
                ];
            }

            $rowData['completion'] = $this->buildCompletionCell($valuesByCode);

            // Fallback column — always present
            $fallbackEntry = $valuesByCode[$this->fallbackCode] ?? null;
            $rowData['lang_fallback'] = $this->buildTranslationCell($fallbackEntry);

            // Selected column — always present in row data to match fixed column count
            $selectedCode = $this->getLanguageFilter();
            $hasSelectedLang = $selectedCode !== 'all'
                && $selectedCode !== $this->fallbackCode
                && isset($this->languages[$selectedCode]);

            if ($hasSelectedLang) {
                $selectedEntry = $valuesByCode[$selectedCode] ?? null;
                $rowData['lang_selected'] = $this->buildTranslationCell($selectedEntry);
            } else {
                $rowData['lang_selected'] = '';
            }


            $rowData['edit'] = [
                'button' => [
                    'label' => t('datatable.translation_keys.actions.edit.label'),
                    'icon' => $this->svgDataUri(self::EDIT_ICON_SVG),
                    'icon_only' => true,
                    'tooltip' => t('datatable.translation_keys.actions.edit.tooltip'),
                    'icon_size' => 16,
                    'action' => 'edit_translation',
                    'style' => 'secondary',
                    'parameters' => [
                        'key' => $row->key,
                        'group' => $row->group,
                    ],
                ],
            ];

            $rowData['delete'] = [
                'button' => [
                    'label' => t('datatable.translation_keys.actions.delete.label'),
                    'icon' => $this->svgDataUri(self::DELETE_ICON_SVG),
                    'icon_only' => true,
                    'tooltip' => t('datatable.translation_keys.actions.delete.tooltip'),
                    'icon_size' => 16,
                    'action' => 'delete_translation',
                    'style' => 'danger',
                    'parameters' => [
                        'key' => $row->key,
                        'group' => $row->group,
                    ],
                ],
            ];

            $formatted[] = $rowData;
        }

        return $formatted;
    }

    /**
     * @param array{text?: mixed, needs_review?: bool}|null $entry
     * @return string|array<string, string>
     */
    protected function buildTranslationCell(?array $entry): string|array
    {
        $text = trim($this->mixedToString($entry['text'] ?? null));
        if ($text === '') {
            return '—';
        }

        $display = $this->truncateCellString($text);

        if (!(bool) ($entry['needs_review'] ?? false)) {
            return $display;
        }

        return [
            'text' => $display,
            'background_color' => 'var(--usim-label-warning-bg, rgba(243, 156, 18, 0.18))',
            'text_color' => 'var(--usim-label-warning-text, #8a5b12)',
            'border_color' => 'var(--usim-label-warning-border, rgba(243, 156, 18, 0.35))',
        ];
    }

    protected function truncateCellString(string $text): string
    {
        return mb_strlen($text) > self::MAX_CELL_STRING_LENGTH
            ? mb_strimwidth($text, 0, self::MAX_CELL_STRING_LENGTH, '...')
            : $text;
    }

    /**
     * @param array<string, array{text?: mixed, needs_review?: bool}> $valuesByCode
     * @return array<string, string>
     */
    protected function buildCompletionCell(array $valuesByCode): array
    {
        $totalLanguages = count($this->languageCodes);
        $completedTranslations = 0;

        foreach ($this->languageCodes as $code) {
            $entry = $valuesByCode[$code] ?? null;
            $text = trim($this->mixedToString($entry['text'] ?? null));
            if ($text !== '') {
                $completedTranslations++;
            }
        }

        $percentage = $totalLanguages > 0
            ? (int) round(($completedTranslations / $totalLanguages) * 100)
            : 0;

        return [
            'text' => $percentage . '%',
            'background_color' => $this->resolveCompletionBackgroundColor($percentage),
            'text_color' => $this->resolveCompletionTextColor($percentage),
            'border_color' => $this->resolveCompletionBorderColor($percentage),
        ];
    }

    protected function resolveCompletionBackgroundColor(int $percentage): string
    {
        if ($percentage >= 100) {
            return 'var(--usim-label-success-bg, rgba(46, 204, 113, 0.16))';
        }

        if ($percentage >= 60) {
            return 'var(--usim-label-warning-bg, rgba(243, 156, 18, 0.18))';
        }

        return 'rgba(230, 126, 34, 0.16)';
    }

    protected function resolveCompletionTextColor(int $percentage): string
    {
        if ($percentage >= 100) {
            return 'var(--usim-label-success-text, #1d6f44)';
        }

        if ($percentage >= 60) {
            return 'var(--usim-label-warning-text, #8a5b12)';
        }

        return '#9a5614';
    }

    protected function resolveCompletionBorderColor(int $percentage): string
    {
        if ($percentage >= 100) {
            return 'var(--usim-label-success-border, rgba(46, 204, 113, 0.35))';
        }

        if ($percentage >= 60) {
            return 'var(--usim-label-warning-border, rgba(243, 156, 18, 0.35))';
        }

        return 'rgba(230, 126, 34, 0.32)';
    }

    /**
     * @return Builder<UsimTextKey>
     */
    protected function buildBaseQuery(): Builder
    {
        $searchTerm = trim((string) ($this->tableBuilder->getSearchTerm() ?? ''));
        $groupFilter = $this->getGroupFilter();

        return UsimTextKey::query()
            ->where('is_active', true)
            ->when($groupFilter !== 'all', function ($query) use ($groupFilter): void {
                $query->where('group', $groupFilter);
            })
            ->when($searchTerm !== '', function ($query) use ($searchTerm): void {
                $like = '%' . $searchTerm . '%';
                $query->where(function ($searchQuery) use ($like): void {
                    $searchQuery->where('key', 'like', $like)
                        ->orWhere('group', 'like', $like);
                });
            });
    }

    protected function normalizeFilterValue(?string $value): string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? 'all' : $normalized;
    }

    protected function svgDataUri(string $svg): string
    {
        return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
    }

    private function mixedToNullableString(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        return null;
    }

    private function mixedToString(mixed $value): string
    {
        return $this->mixedToNullableString($value) ?? '';
    }
}
