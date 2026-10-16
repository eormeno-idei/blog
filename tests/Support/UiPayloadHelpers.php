<?php

use Illuminate\Testing\TestResponse;
use Tests\Support\UiComponentRef;
use Tests\Support\UiScenario;

// @usim: feature="core", type="test"
if (! function_exists('uiFind')) {
    /**
     * Depth-first search over nested arrays/scalars.
     * Returns the first matched node or null.
     */
    function uiFind(mixed $value, callable $predicate): mixed
    {
        if ($predicate($value)) {
            return $value;
        }

        if (! is_array($value)) {
            return null;
        }

        foreach ($value as $child) {
            $match = uiFind($child, $predicate);
            if ($match !== null) {
                return $match;
            }
        }

        return null;
    }
}

if (! function_exists('uiPayloadContainsAction')) {
    function uiPayloadContainsAction(mixed $value, string $action): bool
    {
        return uiFind(
            $value,
            static fn (mixed $node): bool => is_array($node) && (($node['action'] ?? null) === $action)
        ) !== null;
    }
}

if (! function_exists('uiPayloadContainsText')) {
    function uiPayloadContainsText(mixed $value, string $needle): bool
    {
        return uiFind(
            $value,
            static fn (mixed $node): bool => is_string($node) && str_contains($node, $needle)
        ) !== null;
    }
}

if (! function_exists('uiPayloadContainsStyle')) {
    function uiPayloadContainsStyle(mixed $value, string $style): bool
    {
        return uiFind(
            $value,
            static fn (mixed $node): bool => is_array($node) && (($node['style'] ?? null) === $style)
        ) !== null;
    }
}

if (! function_exists('payloadContainsAction')) {
    function payloadContainsAction(mixed $value, string $action): bool
    {
        return uiPayloadContainsAction($value, $action);
    }
}

if (! function_exists('payloadContainsText')) {
    function payloadContainsText(mixed $value, string $needle): bool
    {
        return uiPayloadContainsText($value, $needle);
    }
}

if (! function_exists('demoUiPayloadContainsText')) {
    function demoUiPayloadContainsText(mixed $value, string $needle): bool
    {
        return uiPayloadContainsText($value, $needle);
    }
}

if (! function_exists('demoUiPayloadContainsStyle')) {
    function demoUiPayloadContainsStyle(mixed $value, string $style): bool
    {
        return uiPayloadContainsStyle($value, $style);
    }
}

if (! function_exists('calendarEventsContainDate')) {
    function calendarEventsContainDate(array $events, string $date): bool
    {
        return uiFind(
            $events,
            static fn (mixed $node): bool => is_array($node) && (
                ($node['date'] ?? null) === $date ||
                ($node['start'] ?? null) === $date ||
                ($node['end'] ?? null) === $date
            )
        ) !== null;
    }
}

if (! function_exists('calendarEventsContainTitle')) {
    function calendarEventsContainTitle(array $events, string $fragment): bool
    {
        return uiFind(
            $events,
            static fn (mixed $node): bool => is_array($node)
                && is_string($node['title'] ?? null)
                && str_contains($node['title'], $fragment)
        ) !== null;
    }
}

if (! function_exists('hasModalComponents')) {
    function hasModalComponents(array $payload): bool
    {
        return uiFind(
            $payload,
            static fn (mixed $node): bool => is_array($node) && (($node['parent'] ?? null) === 'modal')
        ) !== null;
    }
}

if (! function_exists('firstTimeoutModalComponent')) {
    /** @return array<string, mixed>|null */
    function firstTimeoutModalComponent(array $payload): ?array
    {
        $match = uiFind(
            $payload,
            static fn (mixed $node): bool => is_array($node)
                && (($node['parent'] ?? null) === 'modal')
                && isset($node['_timeout'])
        );

        return is_array($match) ? $match : null;
    }
}

if (! function_exists('modalPayloadHasNamedComponent')) {
    function modalPayloadHasNamedComponent(array $payload, string $name): bool
    {
        return uiFind(
            $payload,
            static fn (mixed $node): bool => is_array($node)
                && (($node['parent'] ?? null) === 'modal')
                && (($node['name'] ?? null) === $name)
        ) !== null;
    }
}

if (! function_exists('cardHasAction')) {
    function cardHasAction(array $cardComponent, string $action): bool
    {
        $actions = $cardComponent['actions'] ?? [];

        return uiFind(
            $actions,
            static fn (mixed $node): bool => is_array($node) && (($node['action'] ?? null) === $action)
        ) !== null;
    }
}

if (! function_exists('menuItemsContainLabel')) {
    function menuItemsContainLabel(array $items, string $label): bool
    {
        return uiFind(
            $items,
            static fn (mixed $node): bool => is_array($node) && (($node['label'] ?? null) === $label)
        ) !== null;
    }
}

if (! function_exists('ddPrint')) {
    function ddPrint(mixed $data): void
    {
        if (defined('STDERR') && is_resource(STDERR)) {
            fwrite(STDERR, (string) $data.PHP_EOL);
        } else {
            echo (string) $data.PHP_EOL;
        }
        exit(1);
    }
}

if (! function_exists('dumpJson')) {
    /**
     * Dumps formatted JSON to the console.
     * Accepts arrays, objects, JSON strings, TestResponse, UiScenario, UiComponentRef, etc.
     *
     * @param  resource|null  $stream  Optional stream resource to write to (defaults to STDERR)
     * @return mixed Returns the original input value.
     */
    function dumpJson(mixed $value = null, bool $exit = false, mixed $stream = null): mixed
    {
        $data = $value;

        if ($data instanceof TestResponse) {
            $data = $data->json();
        } elseif ($data instanceof UiComponentRef) {
            $data = $data->data();
        } elseif ($data instanceof UiScenario) {
            $data = $data->components();
        } elseif (is_object($data) && method_exists($data, 'toArray')) {
            $data = $data->toArray();
        } elseif (is_string($data)) {
            $decoded = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data = $decoded;
            }
        }

        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ($json === false) {
            $json = json_encode([
                'error' => 'Unable to encode to JSON: '.json_last_error_msg(),
                'raw' => is_scalar($value) ? (string) $value : get_debug_type($value),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if (is_resource($stream)) {
            fwrite($stream, $json.PHP_EOL);
        } elseif (defined('STDERR') && is_resource(STDERR)) {
            fwrite(STDERR, $json.PHP_EOL);
        } else {
            echo $json.PHP_EOL;
        }

        if ($exit) {
            exit(1);
        }

        return $value;
    }
}

if (! function_exists('printJson')) {
    /**
     * Prints formatted JSON to the console (alias of dumpJson).
     */
    function printJson(mixed $value = null, bool $exit = false, mixed $stream = null): mixed
    {
        return dumpJson($value, $exit, $stream);
    }
}

if (! function_exists('ddJson')) {
    /**
     * Dumps formatted JSON to the console and halts execution.
     */
    function ddJson(mixed $value = null): never
    {
        dumpJson($value, true);
        exit(1);
    }
}

if (! function_exists('uiDumpJson')) {
    /**
     * Dumps formatted JSON to the console (alias of dumpJson).
     */
    function uiDumpJson(mixed $value = null, bool $exit = false, mixed $stream = null): mixed
    {
        return dumpJson($value, $exit, $stream);
    }
}
