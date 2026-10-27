<?php

use App\UI\Screens\Admin\Concerns\HandlesModalFeedback;

it('formats validation errors for modal updates and updates modal when errors exist', function () {
    $instance = new class
    {
        use HandlesModalFeedback;

        public array $updatedModalContent = [];

        protected function updateModal(array $content): void
        {
            $this->updatedModalContent = $content;
        }

        public function format(array $errors): array
        {
            return $this->formatModalValidationErrors($errors);
        }

        public function update(array $errors): void
        {
            $this->updateModalWithErrors($errors);
        }
    };

    $stringable = new class implements Stringable
    {
        public function __toString(): string
        {
            return 'Stringable error';
        }
    };

    $errors = [
        'name' => 'Name is required.',
        'email' => ['Email is required.', 'Email must be valid.'],
        'age' => 42,
        'bio' => $stringable,
        'tags' => ['Tag 1', null, new stdClass, 'Tag 2'],
        'invalid' => new stdClass,
    ];

    $formatted = $instance->format($errors);

    expect($formatted)->toBe([
        'name' => ['error' => 'Name is required.'],
        'email' => ['error' => 'Email is required. Email must be valid.'],
        'age' => ['error' => '42'],
        'bio' => ['error' => 'Stringable error'],
        'tags' => ['error' => 'Tag 1 Tag 2'],
        'invalid' => ['error' => ''],
    ]);

    $instance->update([]);
    expect($instance->updatedModalContent)->toBe([]);

    $instance->update(['name' => 'Name is required.']);
    expect($instance->updatedModalContent)->toBe([
        'name' => ['error' => 'Name is required.'],
    ]);
});
