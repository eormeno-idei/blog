<?php

// @usim: feature="admin", type="screen"

namespace App\UI\Screens\Admin\Concerns;

trait HandlesModalFeedback
{
    /**
     * @param  array<string, mixed>  $content
     */
    abstract protected function updateModal(array $content): void;

    /**
     * Format validation errors array into modal update instructions.
     *
     * @param  array<string, mixed>  $errors
     * @return array<string, array{error: string}>
     */
    protected function formatModalValidationErrors(array $errors): array
    {
        $modalUpdates = [];

        foreach ($errors as $fieldName => $messages) {
            $modalUpdates[$fieldName] = [
                'error' => $this->formatModalErrorMessage($messages),
            ];
        }

        return $modalUpdates;
    }

    /**
     * Format a single or list of error messages into a string.
     */
    protected function formatModalErrorMessage(mixed $messages): string
    {
        if (is_array($messages)) {
            $stringMessages = array_filter(array_map($this->stringifyModalMessage(...), $messages));

            return implode(' ', $stringMessages);
        }

        return $this->stringifyModalMessage($messages);
    }

    /**
     * Convert a message value to string if it is scalar or stringable.
     */
    protected function stringifyModalMessage(mixed $message): string
    {
        if (is_scalar($message) || $message instanceof \Stringable) {
            return (string) $message;
        }

        return '';
    }

    /**
     * Dispatch modal updates with validation errors if present.
     *
     * @param  array<string, mixed>  $errors
     */
    protected function updateModalWithErrors(array $errors): void
    {
        if (! empty($errors)) {
            $this->updateModal($this->formatModalValidationErrors($errors));
        }
    }
}
