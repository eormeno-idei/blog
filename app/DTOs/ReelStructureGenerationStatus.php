<?php

namespace App\DTOs;

final readonly class ReelStructureGenerationStatus
{
    /**
     * @param  array<string, mixed>|null  $parsedScript
     */
    public function __construct(
        public string $status,
        public ?string $initialInstructions = null,
        public ?int $postId = null,
        public ?int $sceneCount = null,
        public ?int $durationSeconds = null,
        public ?string $jsonScript = null,
        public ?array $parsedScript = null,
    ) {}

    public function persisted(): self
    {
        return clone($this);
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

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, ?string $fallbackStatus = null): self
    {
        /** @var array<string, mixed>|null $parsedScript */
        $parsedScript = isset($data['parsed_script']) && is_array($data['parsed_script'])
            ? $data['parsed_script']
            : null;

        $rawStatus = $data['status'] ?? $fallbackStatus ?? 'idle';
        $status = is_string($rawStatus) && $rawStatus !== '' ? $rawStatus : 'idle';

        return new self(
            postId: isset($data['post_id']) && is_numeric($data['post_id']) ? (int) $data['post_id'] : null,
            status: $status,
            sceneCount: isset($data['scene_count']) && is_numeric($data['scene_count']) ? (int) $data['scene_count'] : null,
            durationSeconds: isset($data['duration_seconds']) && is_numeric($data['duration_seconds']) ? (int) $data['duration_seconds'] : null,
            jsonScript: isset($data['json_script']) && is_string($data['json_script']) ? $data['json_script'] : null,
            parsedScript: $parsedScript,
        );
    }

    public static function generatingFirstScript(int $postId, ?string $initialInstructions = null): self {
        return new self(
            postId: $postId,
            status: 'generating_script',
            initialInstructions: $initialInstructions,
        );
    }

    /**
     * @param  array<string, mixed>  $parsedScript
     */
    public static function completed(
        int $postId,
        int $sceneCount,
        int $durationSeconds,
        string $jsonScript,
        array $parsedScript,
    ): self {
        return new self(
            postId: $postId,
            status: 'completed',
            sceneCount: $sceneCount,
            durationSeconds: $durationSeconds,
            jsonScript: $jsonScript,
            parsedScript: $parsedScript
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
            'scene_count' => $this->sceneCount,
            'duration_seconds' => $this->durationSeconds,
            'json_script' => $this->jsonScript,
            'parsed_script' => $this->parsedScript,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
