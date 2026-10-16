<?php
// @usim: feature="core", type="test"
namespace Tests\Support;

final class UiMemoryRenderer
{
    private const RESERVED_KEYS = [
        'storage',
        'action',
        'redirect',
        'toast',
        'abort',
        'modal',
        'update_modal',
        'clear_uploaders',
        'set_uploader_existing_file',
        'navigate',
    ];

    /** @var array<string, array<string, mixed>> */
    private array $componentsByKey = [];

    /** @var array<int, string> */
    private array $keyByInternalId = [];

    /** @var array<int, array<string, mixed>> */
    private array $issues = [];

    /** @var array<string, mixed> */
    private array $meta = [
        'storage' => [],
        'toasts' => [],
        'redirect' => null,
        'abort' => null,
        'action' => null,
        'update_modal' => null,
        'clear_uploaders' => [],
        'set_uploader_existing_file' => null,
        'navigate' => null,
    ];

    private ?string $rawUsim = null;

    private bool $hasSnapshot = false;

    public function ingest(array $payload, string $mode = 'auto'): void
    {
        $resolvedMode = $this->resolveMode($mode);

        if ($resolvedMode === 'initial') {
            $this->applyInitial($payload);
            return;
        }

        $this->applyDelta($payload);
    }

    /** @return array<string, mixed>|null */
    public function componentByName(string $name): ?array
    {
        foreach ($this->componentsByKey as $component) {
            if (($component['name'] ?? null) === $name) {
                return $component;
            }
        }

        return null;
    }

    /** @return array<string, array<string, mixed>> */
    public function components(): array
    {
        return $this->componentsByKey;
    }

    /** @return array<int, array<string, mixed>> */
    public function issues(): array
    {
        return $this->issues;
    }

    public function usimStorage(): string
    {
        // Browser-like behavior: replay the exact encrypted payload returned by backend.
        if (is_string($this->rawUsim) && $this->rawUsim !== '') {
            return $this->rawUsim;
        }

        return '';
    }

    public function opaqueUsim(): string
    {
        return is_string($this->rawUsim) ? $this->rawUsim : '';
    }

    private function resolveMode(string $mode): string
    {
        if ($mode === 'initial' || $mode === 'delta') {
            return $mode;
        }

        if ($mode !== 'auto') {
            $this->issues[] = [
                'type' => 'invalid_mode',
                'mode' => $mode,
                'message' => 'Unknown ingest mode. Falling back to auto.',
            ];
        }

        return $this->hasSnapshot ? 'delta' : 'initial';
    }

    private function applyInitial(array $payload): void
    {
        $this->componentsByKey = [];
        $this->keyByInternalId = [];
        $this->captureMeta($payload);

        foreach ($payload as $key => $value) {
            if ($this->isReservedKey((string) $key) || !is_array($value)) {
                continue;
            }

            $this->setComponent((string) $key, $value);
        }

        $this->hasSnapshot = true;
    }

    private function applyDelta(array $payload): void
    {
        $this->captureMeta($payload);

        foreach ($payload as $key => $value) {
            $jsonKey = $this->resolveComponentKey((string) $key);

            if ($this->isReservedKey($jsonKey) || !is_array($value)) {
                continue;
            }

            if (isset($this->componentsByKey[$jsonKey])) {
                $this->componentsByKey[$jsonKey] = $this->mergeComponent(
                    $this->componentsByKey[$jsonKey],
                    $value
                );
                $this->syncInternalId($jsonKey, $this->componentsByKey[$jsonKey]);
                continue;
            }

            if ($this->looksLikeComponent($value)) {
                $this->setComponent($jsonKey, $value);
                continue;
            }

            $this->issues[] = [
                'type' => 'missing_component',
                'component' => $jsonKey,
                'message' => 'Delta received for unknown component.',
            ];
        }

        $this->hasSnapshot = true;
    }

    private function resolveComponentKey(string $incomingKey): string
    {
        if (is_numeric($incomingKey)) {
            $mappedKey = $this->keyByInternalId[(int) $incomingKey] ?? null;
            if (is_string($mappedKey) && $mappedKey !== '') {
                return $mappedKey;
            }
        }

        return $incomingKey;
    }

    private function isReservedKey(string $key): bool
    {
        return in_array($key, self::RESERVED_KEYS, true);
    }

    /** @param array<string, mixed> $payload */
    private function captureMeta(array $payload): void
    {
        $storage_key = config('usim.front_store_key', 'my-app');
        if (isset($payload['storage']) && is_array($payload['storage'])) {
            $this->meta['storage'] = array_merge($this->meta['storage'], $payload['storage']);

            if (isset($payload['storage'][$storage_key]) && is_string($payload['storage'][$storage_key]) && $payload['storage'][$storage_key] !== '') {
                $this->rawUsim = $payload['storage'][$storage_key];
            }
        }

        if (isset($payload['toast']) && is_array($payload['toast'])) {
            $this->meta['toasts'][] = $payload['toast'];
        }

        if (array_key_exists('redirect', $payload)) {
            $this->meta['redirect'] = $payload['redirect'];
        }

        if (array_key_exists('abort', $payload)) {
            $this->meta['abort'] = $payload['abort'];
        }

        if (array_key_exists('action', $payload)) {
            $this->meta['action'] = $payload['action'];
        }

        if (array_key_exists('update_modal', $payload)) {
            $this->meta['update_modal'] = $payload['update_modal'];
        }

        if (isset($payload['clear_uploaders']) && is_array($payload['clear_uploaders'])) {
            $this->meta['clear_uploaders'] = $payload['clear_uploaders'];
        }

        if (array_key_exists('set_uploader_existing_file', $payload)) {
            $this->meta['set_uploader_existing_file'] = $payload['set_uploader_existing_file'];
        }

        if (array_key_exists('navigate', $payload)) {
            $this->meta['navigate'] = $payload['navigate'];
        }
    }

    /** @param array<string, mixed> $component */
    private function setComponent(string $jsonKey, array $component): void
    {
        $component['_json_key'] = $jsonKey;

        if (is_numeric($jsonKey) && !isset($component['_id'])) {
            $component['_id'] = (int) $jsonKey;
        }

        $this->componentsByKey[$jsonKey] = $component;
        $this->syncInternalId($jsonKey, $component);
    }

    /** @param array<string, mixed> $component */
    private function syncInternalId(string $jsonKey, array $component): void
    {
        if (!is_numeric($jsonKey)) {
            return;
        }

        $this->keyByInternalId[(int) $jsonKey] = $jsonKey;
    }

    /**
     * Merge a partial delta into the current component snapshot.
     *
     * @param array<string, mixed> $current
     * @param array<string, mixed> $delta
     * @return array<string, mixed>
     */
    private function mergeComponent(array $current, array $delta): array
    {
        foreach ($delta as $field => $value) {
            $current[$field] = $value;
        }

        return $current;
    }

    /** @param array<string, mixed> $component */
    private function looksLikeComponent(array $component): bool
    {
        return isset($component['type']) || isset($component['parent']);
    }
}
