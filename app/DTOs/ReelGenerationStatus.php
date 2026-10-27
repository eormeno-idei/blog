<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class ReelGenerationStatus
{
    /**
     * @param  array<string, mixed>|null  $parsedScript
     * @param  list<array<string, mixed>>  $versions
     */
    public function __construct(
        public string $status,
        public ?int $version = null,
        public ?int $step = null,
        public ?int $totalSteps = null,
        public ?string $message = null,
        public ?string $detail = null,
        public ?int $sceneCount = null,
        public ?int $durationSeconds = null,
        public ?string $jsonScript = null,
        public ?array $parsedScript = null,
        public ?string $videoFilename = null,
        public ?string $videoUrl = null,
        public ?string $feedbackApplied = null,
        public array $versions = [],
        public ?string $updatedAt = null,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $versions
     */
    public function persisted(array $versions): self
    {
        return clone($this, [
            'versions' => $versions,
            'updatedAt' => now()->toIso8601String(),
        ]);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isGeneratingScript(): bool
    {
        return $this->status === 'generating_script';
    }

    public function isRenderingVideo(): bool
    {
        return $this->status === 'rendering_video';
    }

    public function isInProgress(): bool
    {
        return in_array($this->status, ['generating_script', 'rendering_video'], true);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, ?string $fallbackStatus = null): self
    {
        /** @var array<string, mixed>|null $parsedScript */
        $parsedScript = isset($data['parsed_script']) && is_array($data['parsed_script'])
            ? $data['parsed_script']
            : null;

        /** @var list<array<string, mixed>> $versions */
        $versions = isset($data['versions']) && is_array($data['versions'])
            ? array_values($data['versions'])
            : [];

        $rawStatus = $data['status'] ?? $fallbackStatus ?? 'idle';
        $status = is_string($rawStatus) && $rawStatus !== '' ? $rawStatus : 'idle';

        return new self(
            status: $status,
            version: isset($data['version']) && is_numeric($data['version']) ? (int) $data['version'] : null,
            step: isset($data['step']) && is_numeric($data['step']) ? (int) $data['step'] : null,
            totalSteps: isset($data['total_steps']) && is_numeric($data['total_steps']) ? (int) $data['total_steps'] : null,
            message: isset($data['message']) && is_string($data['message']) ? $data['message'] : null,
            detail: isset($data['detail']) && is_string($data['detail']) ? $data['detail'] : null,
            sceneCount: isset($data['scene_count']) && is_numeric($data['scene_count']) ? (int) $data['scene_count'] : null,
            durationSeconds: isset($data['duration_seconds']) && is_numeric($data['duration_seconds']) ? (int) $data['duration_seconds'] : null,
            jsonScript: isset($data['json_script']) && is_string($data['json_script']) ? $data['json_script'] : null,
            parsedScript: $parsedScript,
            videoFilename: isset($data['video_filename']) && is_string($data['video_filename']) ? $data['video_filename'] : null,
            videoUrl: isset($data['video_url']) && is_string($data['video_url']) ? $data['video_url'] : null,
            feedbackApplied: isset($data['feedback_applied']) && is_string($data['feedback_applied']) ? $data['feedback_applied'] : null,
            versions: $versions,
            updatedAt: isset($data['updated_at']) && is_string($data['updated_at']) ? $data['updated_at'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toVersionEntry(int $fallbackVersion): array
    {
        return [
            'version' => $this->version ?? $fallbackVersion,
            'video_url' => $this->videoUrl ?? '',
            'video_filename' => $this->videoFilename ?? '',
            'json_script' => $this->jsonScript ?? '',
            'scene_count' => $this->sceneCount ?? 0,
            'duration_seconds' => $this->durationSeconds ?? 0,
            'feedback_applied' => $this->feedbackApplied,
        ];
    }

    public static function generatingScript(
        int $version,
        bool $isRefinement,
        ?string $userFeedback = null,
        ?string $message = null,
        ?string $detail = null,
    ): self {
        return new self(
            status: 'generating_script',
            version: $version,
            step: 1,
            totalSteps: 2,
            message: $message ?? ($isRefinement
                ? "Paso 1/2: Ajustando el guion (Versión {$version}) según tus indicaciones..."
                : 'Paso 1/2: Analizando tus notas y creando el guion con IA...'),
            detail: $detail ?? ($isRefinement
                ? "Modificación solicitada: \"{$userFeedback}\""
                : 'Conectando con el agente de IA para diseñar las escenas del Reel.'),
        );
    }

    /**
     * @param  array<string, mixed>  $parsedScript
     */
    public static function renderingVideo(
        int $version,
        int $sceneCount,
        int $durationSeconds,
        string $jsonScript,
        array $parsedScript,
    ): self {
        return new self(
            status: 'rendering_video',
            version: $version,
            step: 2,
            totalSteps: 2,
            message: "Paso 2/2: Renderizando video con Remotion ({$sceneCount} escenas, {$durationSeconds}s)...",
            detail: 'Generando animaciones y codificando el archivo MP4. Esto puede tomar entre 20 y 60 segundos.',
            sceneCount: $sceneCount,
            durationSeconds: $durationSeconds,
            jsonScript: $jsonScript,
            parsedScript: $parsedScript,
        );
    }

    /**
     * @param  array<string, mixed>  $parsedScript
     */
    public static function completed(
        int $version,
        int $sceneCount,
        int $durationSeconds,
        string $jsonScript,
        array $parsedScript,
        string $videoFilename,
        ?string $feedbackApplied = null,
    ): self {
        return new self(
            status: 'completed',
            version: $version,
            step: 2,
            totalSteps: 2,
            message: "¡Previsualización v{$version} generada con éxito!",
            sceneCount: $sceneCount,
            durationSeconds: $durationSeconds,
            jsonScript: $jsonScript,
            parsedScript: $parsedScript,
            videoFilename: $videoFilename,
            videoUrl: "/storage/reels/{$videoFilename}?v=".time(),
            feedbackApplied: $feedbackApplied,
        );
    }

    public static function failed(int $version, string $errorDetail): self
    {
        return new self(
            status: 'failed',
            version: $version,
            message: 'No se pudo completar la generación del video.',
            detail: $errorDetail,
        );
    }

    /**
     * Devuelve el payload persistible, omitiendo los campos sin valor.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'status' => $this->status,
            'step' => $this->step,
            'total_steps' => $this->totalSteps,
            'version' => $this->version,
            'scene_count' => $this->sceneCount,
            'duration_seconds' => $this->durationSeconds,
            'json_script' => $this->jsonScript,
            'parsed_script' => $this->parsedScript,
            'video_filename' => $this->videoFilename,
            'video_url' => $this->videoUrl,
            'feedback_applied' => $this->feedbackApplied,
            'message' => $this->message,
            'detail' => $this->detail,
            'versions' => $this->versions ?: null,
            'updated_at' => $this->updatedAt,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
