<?php
// @usim: feature="admin", type="screen"
namespace App\UI\Screens\Admin\Concerns;

trait HandlesScreenParameters
{
    /**
     * @param array<string, mixed> $params
     */
    protected function optionalStringParam(array $params, string $key): ?string
    {
        $value = $params[$key] ?? null;
        return is_string($value) ? $value : null;
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function stringParamOrDefault(array $params, string $key, string $default): string
    {
        $value = $params[$key] ?? null;
        return is_string($value) ? $value : $default;
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function optionalIntParam(array $params, string $key): ?int
    {
        $value = $params[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function intParamOrDefault(array $params, string $key, int $default): int
    {
        $value = $this->optionalIntParam($params, $key);
        return $value ?? $default;
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function boolParamOrDefault(array $params, string $key, bool $default): bool
    {
        $value = $params[$key] ?? null;
        return is_bool($value) ? $value : $default;
    }

    /**
     * @param list<string> $keys
     * @param array<string, mixed> $params
     */
    protected function searchParam(array $params, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $params[$key] ?? null;
            if (is_string($value)) {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function selectableId(array $params, string $key): int|string|null
    {
        $value = $params[$key] ?? null;
        if (is_int($value) || is_string($value)) {
            return $value;
        }

        return null;
    }
}
