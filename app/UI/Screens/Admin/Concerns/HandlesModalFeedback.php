<?php
// @usim: feature="admin", type="screen"
namespace App\UI\Screens\Admin\Concerns;

trait HandlesModalFeedback
{
    /**
     * @param array<string, mixed> $content
     */
    abstract protected function updateModal(array $content): void;

    /**
     * Format validation errors array into modal update instructions.
     *
     * @param array<string, mixed> $errors
     * @return array<string, array{error: string}>
     */
    protected function formatModalValidationErrors(array $errors): array
    {
        $modalUpdates = [];

        foreach ($errors as $fieldName => $messages) {
            $text = '';
            if (is_array($messages)) {
                $stringMessages = array_filter(array_map(static fn (mixed $m): string => is_scalar($m) || (is_object($m) && method_exists($m, '__toString')) ? (string) $m : '', $messages));
                $text = implode(' ', $stringMessages);
            } elseif (is_scalar($messages) || (is_object($messages) && method_exists($messages, '__toString'))) {
                $text = (string) $messages;
            }

            $modalUpdates[$fieldName] = [
                'error' => $text,
            ];
        }

        return $modalUpdates;
    }

    /**
     * Dispatch modal updates with validation errors if present.
     *
     * @param array<string, mixed> $errors
     */
    protected function updateModalWithErrors(array $errors): void
    {
        if (!empty($errors)) {
            $this->updateModal($this->formatModalValidationErrors($errors));
        }
    }
}
