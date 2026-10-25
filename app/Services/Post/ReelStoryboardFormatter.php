<?php

namespace App\Services\Post;

class ReelStoryboardFormatter
{
    /**
     * Construye un resumen legible en Markdown a partir del guion JSON o arreglo de escenas.
     *
     * @param string|array<string, mixed>|null $scriptOrJson
     */
    public function format(string|array|null $scriptOrJson, ?string $feedbackApplied = null): string
    {
        $parsedScript = $this->normalizeScript($scriptOrJson);
        $scenes = is_array($parsedScript['scenes'] ?? null) ? $parsedScript['scenes'] : [];

        if ($scenes === []) {
            return 'Sin detalle de escenas disponible.';
        }

        $lines = $this->buildFeedbackHeader($feedbackApplied);

        foreach ($scenes as $idx => $scene) {
            if (is_array($scene)) {
                $lines = array_merge($lines, $this->formatSceneLines($scene, (int) $idx));
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param string|array<string, mixed>|null $scriptOrJson
     * @return array<string, mixed>
     */
    private function normalizeScript(string|array|null $scriptOrJson): array
    {
        if (is_array($scriptOrJson)) {
            return $scriptOrJson;
        }

        if (! is_string($scriptOrJson) || trim($scriptOrJson) === '') {
            return [];
        }

        $decoded = json_decode($scriptOrJson, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return list<string>
     */
    private function buildFeedbackHeader(?string $feedbackApplied): array
    {
        if ($feedbackApplied === null || trim($feedbackApplied) === '') {
            return [];
        }

        return [
            "*Último ajuste aplicado: \"{$feedbackApplied}\"*",
            '',
        ];
    }

    /**
     * @param array<string, mixed> $scene
     * @return list<string>
     */
    private function formatSceneLines(array $scene, int $fallbackIndex): array
    {
        $num = (int) ($scene['scene_index'] ?? ($fallbackIndex + 1));
        $duration = (int) ($scene['duration_seconds'] ?? 0);
        $template = (string) ($scene['layout_template'] ?? 'escena');
        $textContent = is_array($scene['text_content'] ?? null) ? $scene['text_content'] : [];
        $headline = (string) ($textContent['headline'] ?? 'Sin título');
        $subheadline = (string) ($textContent['subheadline'] ?? '');
        $bodyItems = is_array($textContent['body'] ?? null) ? $textContent['body'] : [];

        $templateBadge = $this->resolveTemplateLabel($template);
        $headerLine = "**Escena {$num}** ({$duration}s · *{$templateBadge}*): **{$headline}**";

        if ($subheadline !== '') {
            $headerLine .= " — _{$subheadline}_";
        }

        $lines = [$headerLine];
        foreach ($bodyItems as $bullet) {
            if (is_string($bullet) && trim($bullet) !== '') {
                $lines[] = '  • ' . trim($bullet);
            }
        }

        return $lines;
    }

    private function resolveTemplateLabel(string $template): string
    {
        return match ($template) {
            'title_hero' => 'Portada',
            'bullet_points' => 'Lista',
            'call_to_action' => 'Cierre / CTA',
            default => $template,
        };
    }
}

