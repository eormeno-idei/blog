<?php
// @usim: feature="core", type="test"
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

if (!function_exists('screenApiUrl')) {
    function screenApiUrl(string $screenClass, array $query = []): string
    {
        $path = '/api/ui' . $screenClass::getRoutePath();

        if (empty($query)) {
            return $path;
        }

        return $path . '?' . http_build_query($query);
    }
}

if (!function_exists('getScreenJson')) {
    function getScreenJson(TestCase $test, string $screenClass, array $query = []): TestResponse
    {
        return $test->getJson(screenApiUrl($screenClass, $query));
    }
}

if (!function_exists('findComponentByName')) {
    function findComponentByName(array $payload, string $name): ?array
    {
        foreach ($payload as $key => $component) {
            if (!is_array($component)) {
                continue;
            }

            if (($component['name'] ?? null) === $name) {
                $component['_json_key'] = (string) $key;
                return $component;
            }
        }

        return null;
    }
}

if (!function_exists('firstUiComponentFromPayload')) {
    function firstUiComponentFromPayload(array $payload): ?array
    {
        $reservedKeys = ['storage', 'action', 'redirect', 'toast', 'abort', 'modal', 'update_modal'];

        foreach ($payload as $key => $value) {
            if (in_array((string) $key, $reservedKeys, true)) {
                continue;
            }

            if (is_array($value) && isset($value['type'], $value['parent'])) {
                $value['_json_key'] = (string) $key;
                return $value;
            }
        }

        return null;
    }
}

if (!function_exists('serviceRootComponentId')) {
    function serviceRootComponentId(array $payload, ?string $screenClass = null): int
    {
        if ($screenClass !== null) {
            foreach ($payload as $id => $component) {
                if (!is_array($component) || !is_numeric($id)) {
                    continue;
                }
                $context = \Idei\Usim\Support\UIIdGenerator::getContextFromId((int) $id);
                if ($context === $screenClass && ($component['type'] ?? null) === 'container') {
                    return (int) $id;
                }
            }
        }

        // Prefer container belonging to the host screen, skipping layout screens and Menu
        foreach ($payload as $id => $component) {
            if (!is_array($component) || !is_numeric($id)) {
                continue;
            }

            if (($component['type'] ?? null) === 'container') {
                $context = \Idei\Usim\Support\UIIdGenerator::getContextFromId((int) $id);
                if (
                    $context !== null
                    && !is_subclass_of($context, \Idei\Usim\Layout\AbstractLayout::class)
                    && $context !== 'App\\UI\\Screens\\Menu'
                    && !is_subclass_of($context, 'App\\UI\\Screens\\Menu')
                ) {
                    return (int) $id;
                }
            }
        }

        foreach ($payload as $id => $component) {
            if (!is_array($component)) {
                continue;
            }

            if (($component['type'] ?? null) === 'container' && is_numeric($id)) {
                return (int) $id;
            }
        }

        throw new RuntimeException('Service root component id not found in UI payload.');
    }
}
