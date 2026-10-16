<?php
// @usim: feature="admin", type="screen"
namespace App\UI\Screens\Admin;

use Idei\Usim\Contracts\ModalInterface;
use Idei\Usim\Components\Container;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

class EditTranslationDialog extends Screen implements ModalInterface
{
    /**
     * @param mixed ...$params Dialog UI parameters.
     */
    public static function open(?Screen $caller = null, mixed ...$params): void
    {
        static::openAsModal(params: $params, caller: $caller);
    }

    /**
     * @param array<int|string, mixed> $params
     */
    private function stringParam(array $params, string $name, string $default = ''): string
    {
        $value = $params[$name] ?? $default;

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @param mixed ...$params
     */
    protected function buildBaseUI(Container $container, ...$params): void
    {
        $key = $this->stringParam($params, 'key');
        $group = $this->stringParam($params, 'group');
        $fallbackLanguageCode = $this->stringParam($params, 'fallbackLanguageCode');
        $selectedLanguageCode = $this->stringParam($params, 'selectedLanguageCode');
        $fallbackText = $this->stringParam($params, 'fallbackText');
        $selectedText = $this->stringParam($params, 'selectedText');
        $fallbackNeedsReview = (bool) ($params['fallbackNeedsReview'] ?? false);
        $selectedNeedsReview = (bool) ($params['selectedNeedsReview'] ?? false);
        $submitAction = $this->stringParam($params, 'submitAction', 'submit_update_translation');
        $cancelAction = array_key_exists('cancelAction', $params)
            ? $this->stringParam($params, 'cancelAction')
            : 'close_modal';
        $callerServiceId = $params['callerServiceId'] ?? $this->callerScreenId;

        $container
            ->parent('modal')
            ->shadow(false)
            ->plain()
            ->padding(Spacing::px(20));

        $container->add(
            UI::label('translation_dialog_title')
                ->text('Edit Translation')
                ->style('info')
        );

        $container->add(
            UI::label('translation_dialog_key')
                ->text('Key: ' . $key)
                ->style('default')
        );

        $container->add(
            UI::label('translation_dialog_group')
                ->text('Group: ' . ($group !== '' ? $group : 'global'))
                ->style('default')
        );

        $container->add(UI::input('translation_key')->type('hidden')->value($key));
        $container->add(UI::input('fallback_language_code')->type('hidden')->value($fallbackLanguageCode));
        $container->add(UI::input('selected_language_code')->type('hidden')->value($selectedLanguageCode));

        $container->add(
            UI::input('fallback_text')
                ->label('Fallback (' . strtoupper($fallbackLanguageCode) . ')')
                ->placeholder('Enter fallback translation')
                ->value($fallbackText)
                ->autocomplete('off')
                ->width(Size::full())
        );

        $container->add(
            UI::checkbox('fallback_mark_reviewed')
                ->label('Fallback translation reviewed by a human (no longer needs review)')
                ->checked(!$fallbackNeedsReview)
        );

        if ($selectedLanguageCode !== '') {
            $container->add(
                UI::input('selected_text')
                    ->label('Selected (' . strtoupper($selectedLanguageCode) . ')')
                    ->placeholder('Enter selected language translation')
                    ->value($selectedText)
                    ->autocomplete('off')
                    ->width(Size::full())
            );

            $container->add(
                UI::checkbox('selected_mark_reviewed')
                    ->label('Selected translation reviewed by a human (no longer needs review)')
                    ->checked(!$selectedNeedsReview)
            );
        }

        $buttons = UI::container('edit_translation_buttons')
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->shadow(false)
            ->plain()
            ->gap(Spacing::px(10))
            ->padding(Spacing::each(Spacing::px(10)));

        if ($cancelAction !== '') {
            $buttons->add(
                UI::button('btn_cancel_translation')
                    ->label('Cancel')
                    ->style('secondary')
                    ->action($cancelAction, [
                        '_caller_service_id' => $callerServiceId,
                    ])
            );
        }

        $buttons->add(
            UI::button('btn_submit_translation')
                ->label('Save Translation')
                ->style('primary')
                ->action($submitAction, [
                    '_caller_service_id' => $callerServiceId,
                ])
        );

        $container->add($buttons);

    }
}
