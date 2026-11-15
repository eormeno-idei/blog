<?php

namespace App\DTOs;

/**
 * Summary of ReelJobStatusDTO
 * @deprecated Información redundante
 */
final readonly class ReelJobStatusDTO
{
    /**
     * @param  array<string, mixed>|null  $reel_script
     */
    public function __construct(
        public string $status,
        public ?string $context = null,
        public ?string $extra_instructions = null,
        public ?int $post_id = null,
        public ?int $scene_count = null,
        public ?int $event_id = null,    // ID del evento de historial en el que se basó este estado, si corresponde
        public ?int $scene_number = null, // Número de escena actual, si corresponde
        public ?int $duration_seconds = null,
        public ?string $json_script = null,
        public ?array $reel_script = null,
    ) {
    }

    public function persisted(): self
    {
        return clone ($this);
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
        /** @var array<string, mixed>|null $reel_script */
        $reelScript = isset($data['reel_script']) && is_array($data['reel_script'])
            ? $data['reel_script']
            : null;

        $rawStatus = $data['status'] ?? $fallbackStatus ?? 'idle';
        $status = is_string($rawStatus) && $rawStatus !== '' ? $rawStatus : 'idle';

        return new self(
            post_id: isset($data['post_id']) && is_numeric($data['post_id']) ? (int) $data['post_id'] : null,
            status: $status,
            context: isset($data['context']) && is_string($data['context']) ? $data['context'] : null,
            extra_instructions: isset($data['extra_instructions']) && is_string($data['extra_instructions']) ? $data['extra_instructions'] : null,
            event_id: isset($data['event_id']) && is_numeric($data['event_id']) ? (int) $data['event_id'] : null,
            scene_number: isset($data['scene_number']) && is_numeric($data['scene_number']) ? (int) $data['scene_number'] : null,
            scene_count: isset($data['scene_count']) && is_numeric($data['scene_count']) ? (int) $data['scene_count'] : null,
            duration_seconds: isset($data['duration_seconds']) && is_numeric($data['duration_seconds']) ? (int) $data['duration_seconds'] : null,
            json_script: isset($data['json_script']) && is_string($data['json_script']) ? $data['json_script'] : null,
            reel_script: $reelScript,
        );
    }

    public static function generatingScript(
        int $post_id,
        string $context,
        ?string $extra_instructions = null,
        ?int $event_id = null,
        ?int $scene_number = null
    ): self {
        return new self(
            status: 'generating_script',
            context: $context,
            post_id: $post_id,
            extra_instructions: $extra_instructions,
            event_id: $event_id,
            scene_number: $scene_number
        );
    }

    /**
     * @param  array<string, mixed>  $reel_script
     */
    public static function completed(
        int $post_id,
        int $scene_count,
        int $durationSeconds,
        array $reel_script,
    ): self {
        return new self(
            post_id: $post_id,
            status: 'completed',
            scene_count: $scene_count,
            duration_seconds: $durationSeconds,
            reel_script: $reel_script
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
            'post_id' => $this->post_id,
            'scene_count' => $this->scene_count,
            'duration_seconds' => $this->duration_seconds,
            'json_script' => $this->json_script,
            'reel_script' => $this->reel_script,
            'context' => $this->context,
            'extra_instructions' => $this->extra_instructions,
            'event_id' => $this->event_id,
            'scene_number' => $this->scene_number,
        ], static fn(mixed $value): bool => $value !== null);
    }
}
