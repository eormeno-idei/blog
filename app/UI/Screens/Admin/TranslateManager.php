<?php
// @usim: feature="admin", type="screen"
namespace App\UI\Screens\Admin;

use App\UI\Screens\Admin\TableModels\TranslationKeysTableModel;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\Select;
use Idei\Usim\Components\Table;
use Idei\Usim\Enums\DialogType;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Modals\ConfirmDialog;
use Idei\Usim\Screen;
use Idei\Usim\Support\TranslationService;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

class TranslateManager extends Screen
{
    protected Table $translations_table;
    protected Input $search_translations;
    protected Select $language_filter;
    protected Select $group_filter;

    public static function authorize(): bool
    {
        //return self::requireRole(['admin', 'root', 'translator']);
        return self::requirePermission('admin.translate_manager.access');
    }

    public static function getMenuLabel(): string
    {
        return t('screens.admin.translate_manager.title');
    }

    public static function getMenuIcon(): ?string
    {
        return '🌐';
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container
            ->maxWidth(Size::px(1360))
            ->centerHorizontal()
            ->padding(Spacing::px(10))
            ->plain();

        $toolbar = UI::container('translations_toolbar')
            ->layout(LayoutType::HORIZONTAL)
            ->fullWidth()
            ->shadow(0)
            ->width(Size::full())
            ->gap(Spacing::px(12));

        $search = UI::input('search_translations')
            ->label(t('screens.admin.translate_manager.search.label'))
            ->placeholder(t('screens.admin.translate_manager.search.placeholder'))
            ->width(Size::px(420))
            ->autocomplete('off')
            ->onInput('search_translations', [])
            ->debounce(500);

        $languageFilter = UI::select('language_filter')
            ->label(t('screens.admin.translate_manager.language.label'))
            ->placeholder(t('screens.admin.translate_manager.language.placeholder'))
            ->options($this->getLanguageOptions())
            ->value('all')
            ->onChange('language_filter_change')
            ->style('primary')
            ->width(Size::px(200));

        $groupFilter = UI::select('group_filter')
            ->label(t('screens.admin.translate_manager.group.label'))
            ->placeholder(t('screens.admin.translate_manager.group.placeholder'))
            ->options($this->getGroupOptions())
            ->value('all')
            ->searchable(true, t('screens.admin.translate_manager.group.search_placeholder'))
            ->onChange('group_filter_change')
            ->style('primary')
            ->width(Size::px(200));

        $toolbar->add($search)->add($languageFilter)->add($groupFilter);
        $container->add($toolbar);

        $table = UI::table('translations_table')
            ->pagination(10)
            ->sortedBy('key')
            ->dataModel(TranslationKeysTableModel::class)
            ->width(Size::full())
            ->rowMinHeight(40);

        $container->add($table);
    }

    protected function postLoadUI(): void
    {
        /** @var TranslationKeysTableModel $model */
        $model = $this->translations_table->getModel();
        $search = $this->translations_table->getSearchTerm();
        $this->search_translations->value($search ?? '');
        $this->language_filter->value($model->getLanguageFilter());
        $this->group_filter->value($model->getGroupFilter());
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onSearchTranslations(array $params): void
    {
        $search = $this->normalizeToString($params['value'] ?? null);
        $this->translations_table->setSearchTerm($search);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onLanguageFilterChange(array $params): void
    {
        /** @var TranslationKeysTableModel $model */
        $model = $this->translations_table->getModel();
        $model->setLanguageFilter($this->normalizeToString($params['value'] ?? null, 'all'));
        $this->translations_table->refreshColumns();
        $this->translations_table->page(1);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onGroupFilterChange(array $params): void
    {
        /** @var TranslationKeysTableModel $model */
        $model = $this->translations_table->getModel();
        $model->setGroupFilter($this->normalizeToString($params['value'] ?? null, 'all'));
        $this->translations_table->page(1);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onTranslationsTableColumnClicked(array $params): void
    {
        $column = $this->normalizeToNullableString($params['sort_by'] ?? null);
        if ($column === null || $column === '') {
            return;
        }

        $this->translations_table->sortedBy($column);
        $this->translations_table->page(1);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onChangePage(array $params): void
    {
        $page = $this->normalizeToInt($params['page'] ?? null, 1);
        $this->translations_table->page($page);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onEditTranslation(array $params): void
    {
        $key = $this->normalizeToString($params['key'] ?? null);
        if ($key === '') {
            $this->toast(t('screens.admin.translate_manager.errors.key_required'), 'error');
            return;
        }

        $languages = $this->resolveEditableLanguages();
        $fallbackCode = $languages['fallback'];
        $selectedCode = $languages['selected'];

        /** @var TranslationService $translationService */
        $translationService = app(TranslationService::class);
        $fallbackEntry = $translationService->getDirectEntry($key, $fallbackCode);
        $selectedEntry = $selectedCode ? $translationService->getDirectEntry($key, $selectedCode) : null;

        EditTranslationDialog::open(
            key: $key,
            group: $this->normalizeToString($params['group'] ?? null),
            fallbackLanguageCode: $fallbackCode,
            selectedLanguageCode: $selectedCode,
            fallbackText: $this->normalizeToString($fallbackEntry['text'] ?? null),
            selectedText: $this->normalizeToString($selectedEntry['text'] ?? null),
            fallbackNeedsReview: (bool) ($fallbackEntry['needs_review'] ?? false),
            selectedNeedsReview: (bool) ($selectedEntry['needs_review'] ?? false),
            callerServiceId: $this->getScreenComponentId()
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onDeleteTranslation(array $params): void
    {
        $key = $this->normalizeToString($params['key'] ?? null);
        if ($key === '') {
            $this->toast(t('screens.admin.translate_manager.errors.key_required'), 'error');
            return;
        }

        ConfirmDialog::open(
            caller: $this,
            type: DialogType::WARNING,
            title: t('screens.admin.translate_manager.delete.title'),
            message: t('screens.admin.translate_manager.delete.confirm', ['key' => $key]),
            confirmAction: 'confirm_delete_translation',
            confirmParams: ['key' => $key],
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onConfirmDeleteTranslation(array $params): void
    {
        $key = $this->normalizeToString($params['key'] ?? null);
        if ($key === '') {
            $this->toast(t('screens.admin.translate_manager.errors.key_required_for_deletion'), 'error');
            return;
        }

        /** @var TranslationService $translationService */
        $translationService = app(TranslationService::class);
        $deleted = $translationService->deleteKey($key);

        if (!$deleted) {
            $this->toast(t('screens.admin.translate_manager.errors.key_not_found'), 'error');
            return;
        }

        $this->translations_table->refresh();
        $this->toast(t('screens.admin.translate_manager.delete.success'), 'success');
        $this->closeModal();
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onSubmitUpdateTranslation(array $params): void
    {
        $key = $this->normalizeToString($params['translation_key'] ?? null);
        $fallbackLanguageCode = $this->normalizeToString($params['fallback_language_code'] ?? null);
        $selectedLanguageCode = $this->normalizeToString($params['selected_language_code'] ?? null);

        if ($key === '' || $fallbackLanguageCode === '') {
            $this->toast(t('screens.admin.translate_manager.errors.update_payload_incomplete'), 'error');
            return;
        }

        /** @var TranslationService $translationService */
        $translationService = app(TranslationService::class);
        $fallbackReviewed = $this->normalizeCheckboxValue($params['fallback_mark_reviewed'] ?? false);
        $fallbackNeedsReview = !$fallbackReviewed;

        $translationService->upsertValue(
            $key,
            $fallbackLanguageCode,
            $this->normalizeToString($params['fallback_text'] ?? null),
            needsReview: $fallbackNeedsReview
        );

        if ($selectedLanguageCode !== '' && $selectedLanguageCode !== $fallbackLanguageCode) {
            $selectedReviewed = $this->normalizeCheckboxValue($params['selected_mark_reviewed'] ?? false);
            $selectedNeedsReview = !$selectedReviewed;

            $translationService->upsertValue(
                $key,
                $selectedLanguageCode,
                $this->normalizeToString($params['selected_text'] ?? null),
                needsReview: $selectedNeedsReview
            );
        }

        $this->translations_table->refresh();
        $this->toast(t('screens.admin.translate_manager.update.success'), 'success');
        $this->closeModal();
    }

    protected function normalizeCheckboxValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            return in_array($normalized, ['1', 'true', 'on', 'yes'], true);
        }

        if ($value instanceof \Stringable) {
            $normalized = strtolower(trim((string) $value));
            return in_array($normalized, ['1', 'true', 'on', 'yes'], true);
        }

        return false;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function getLanguageOptions(): array
    {
        /** @var TranslationService $translationService */
        $translationService = app(TranslationService::class);
        $dataset = $translationService->listLanguagesDataset();

        $options = [
            ['value' => 'all', 'label' => t('screens.admin.translate_manager.language.option_none')],
        ];

        // Resolve fallback code to exclude it from options
        $fallbackCode = 'en';
        foreach ($dataset['items'] as $language) {
            if ((bool) ($language['is_fallback'] ?? false)) {
                $fallbackCode = $this->normalizeToString($language['code'] ?? null, 'en');
            }
        }

        foreach ($dataset['items'] as $language) {
            $code = $this->normalizeToString($language['code'] ?? null);
            if ($code === '' || $code === $fallbackCode) {
                continue;
            }

            $label = $this->normalizeToString($language['native_name'] ?? $language['name'] ?? null, strtoupper($code));
            $options[] = [
                'value' => $code,
                'label' => strtoupper($code) . ' - ' . $label,
            ];
        }

        return $options;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function getGroupOptions(): array
    {
        /** @var TranslationService $translationService */
        $translationService = app(TranslationService::class);
        $dataset = $translationService->listKeyGroupsDataset();

        $options = [
            ['value' => 'all', 'label' => t('screens.admin.translate_manager.group.option_all')],
        ];

        foreach ($dataset['items'] as $group) {
            $value = $this->normalizeToString($group['group'] ?? null);
            if ($value === '') {
                continue;
            }

            $options[] = [
                'value' => $value,
                'label' => $value,
            ];
        }

        return $options;
    }

    /**
     * @return array{fallback: string, selected: string|null}
     */
    protected function resolveEditableLanguages(): array
    {
        /** @var TranslationService $translationService */
        $translationService = app(TranslationService::class);
        $dataset = $translationService->listLanguagesDataset();

        $fallbackCode = 'en';
        $firstActiveNonFallback = null;

        foreach ($dataset['items'] as $language) {
            $code = $this->normalizeToString($language['code'] ?? null);
            if ($code === '') {
                continue;
            }

            if ((bool) ($language['is_fallback'] ?? false)) {
                $fallbackCode = $code;
            }

            if ((bool) ($language['is_active'] ?? false) && !(bool) ($language['is_fallback'] ?? false) && $firstActiveNonFallback === null) {
                $firstActiveNonFallback = $code;
            }
        }

        /** @var TranslationKeysTableModel $model */
        $model = $this->translations_table->getModel();
        $filterCode = $model->getLanguageFilter();

        // Selected is null when no specific language is chosen, or when the filter matches the fallback itself
        $selectedCode = ($filterCode === 'all' || $filterCode === $fallbackCode) ? null : $filterCode;

        return [
            'fallback' => $fallbackCode,
            'selected' => $selectedCode,
        ];
    }

    protected function normalizeToString(mixed $value, string $default = ''): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '';
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        return $default;
    }

    protected function normalizeToNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->normalizeToString($value);
    }

    protected function normalizeToInt(mixed $value, int $default = 0): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        return $default;
    }
}
