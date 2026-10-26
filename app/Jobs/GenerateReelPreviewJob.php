<?php

namespace App\Jobs;

use App\Ai\Agents\PostAgent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class GenerateReelPreviewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1200;

    public int $tries = 1;

    public function __construct(
        public int|string $jobId,
        public string $notes,
        public ?string $previousJson = null,
        public ?string $userFeedback = null,
        public int $versionNumber = 1,
    ) {
    }

    public static function cacheKey(int|string $jobId): string
    {
        return "reel_preview_job:{$jobId}";
    }

    /**
     * Persiste el estado del procesamiento en la tabla `posts` (o en caché si no hay post numérico).
     *
     * @param array<string, mixed> $payload
     */
    public static function putStatus(int|string $jobId, array $payload): void
    {
        $enriched = array_merge($payload, ['updated_at' => now()->toIso8601String()]);
        $status = (string) ($enriched['status'] ?? 'idle');

        if (is_numeric($jobId) && (int) $jobId > 0) {
            $postId = (int) $jobId;
            $existing = self::getStatus($postId);
            $versions = is_array($existing['versions'] ?? null) ? $existing['versions'] : [];

            if ($status === 'completed') {
                $versions[] = [
                    'version' => (int) ($enriched['version'] ?? (count($versions) + 1)),
                    'video_url' => (string) ($enriched['video_url'] ?? ''),
                    'video_filename' => (string) ($enriched['video_filename'] ?? ''),
                    'json_script' => (string) ($enriched['json_script'] ?? ''),
                    'scene_count' => (int) ($enriched['scene_count'] ?? 0),
                    'duration_seconds' => (int) ($enriched['duration_seconds'] ?? 0),
                    'feedback_applied' => $enriched['feedback_applied'] ?? null,
                ];
            }

            $enriched['versions'] = $versions;

            $dbUpdate = [
                'ai_job_status' => $status,
                'ai_job_payload' => json_encode($enriched, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ];

            if ($status === 'completed') {
                $dbUpdate['ai_script'] = $enriched['json_script'] ?? null;
                $dbUpdate['reel_video'] = $enriched['video_filename'] ?? null;
            }

            DB::table('posts')->where('id', $postId)->update($dbUpdate);

            return;
        }

        Cache::put(self::cacheKey($jobId), $enriched, now()->addHours(2));
    }

    /**
     * Obtiene el estado del procesamiento desde la tabla `posts` (o caché como fallback).
     *
     * @return array<string, mixed>|null
     */
    public static function getStatus(int|string $jobId): ?array
    {
        if (is_numeric($jobId) && (int) $jobId > 0) {
            $row = DB::table('posts')
                ->where('id', (int) $jobId)
                ->first(['ai_job_status', 'ai_job_payload', 'ai_script', 'reel_video']);

            if (! $row) {
                return null;
            }

            $decoded = is_string($row->ai_job_payload ?? null) && $row->ai_job_payload !== ''
                ? json_decode($row->ai_job_payload, true)
                : [];

            if (! is_array($decoded)) {
                $decoded = [];
            }

            $decoded['status'] = (string) ($row->ai_job_status ?? ($decoded['status'] ?? 'idle'));

            return $decoded;
        }

        $data = Cache::get(self::cacheKey($jobId));

        return is_array($data) ? $data : null;
    }

    public function handle(): void
    {
        $isRefinement = $this->isRefinementMode();
        $this->reportGeneratingScriptStatus($isRefinement);

        $jsonPath = null;

        try {
            $jsonString = $this->generateJsonScript($isRefinement);
            $decoded = $this->parseAndValidateScript($jsonString);
            [$sceneCount, $totalSeconds] = $this->calculateMetrics($decoded);

            $this->reportRenderingStatus($sceneCount, $totalSeconds, $jsonString, $decoded);

            [$jsonPath, $videoFilename] = $this->renderVideoWithRemotion($jsonString);

            $this->reportCompletedStatus($sceneCount, $totalSeconds, $jsonString, $decoded, $videoFilename, $isRefinement);
        } catch (\Throwable $e) {
            Log::error('[GenerateReelPreviewJob] Error: ' . $e->getMessage());
            $this->reportFailedStatus($e->getMessage());
        } finally {
            $this->deleteTempJsonFile($jsonPath);
        }
    }

    private function isRefinementMode(): bool
    {
        return trim((string) $this->previousJson) !== ''
            && trim((string) $this->userFeedback) !== '';
    }

    private function reportGeneratingScriptStatus(bool $isRefinement): void
    {
        self::putStatus($this->jobId, [
            'status' => 'generating_script',
            'step' => 1,
            'total_steps' => 2,
            'version' => $this->versionNumber,
            'message' => $isRefinement
                ? 'Paso 1/2: Aplicando tus correcciones al guion con IA...'
                : 'Paso 1/2: Analizando tus notas y estructurando el guion con IA...',
            'detail' => $isRefinement
                ? "Ajuste solicitado: \"{$this->userFeedback}\""
                : 'Diseñando escenas, textos clave y transiciones para cartelería digital.',
        ]);
    }

    private function generateJsonScript(bool $isRefinement): string
    {
        $prompt = $this->buildPrompt($isRefinement);
        $answer = app(PostAgent::class)->prompt($prompt);

        return $this->sanitizeJsonResponse((string) $answer->text);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseAndValidateScript(string $jsonString): array
    {
        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($jsonString, true);

        if (! is_array($decoded) || empty($decoded['scenes']) || ! is_array($decoded['scenes'])) {
            throw new \RuntimeException('La IA devolvió un guion con formato JSON inválido. Intenta nuevamente.');
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $decoded
     * @return array{0: int, 1: int}
     */
    private function calculateMetrics(array $decoded): array
    {
        /** @var list<array<string, mixed>> $scenes */
        $scenes = $decoded['scenes'];
        $sceneCount = count($scenes);
        $totalSeconds = 0;

        foreach ($scenes as $scene) {
            $totalSeconds += (int) ($scene['duration_seconds'] ?? 0);
        }

        $fallbackSeconds = (int) ($decoded['metadata']['duracion_total_segundos'] ?? 30);

        return [$sceneCount, $totalSeconds > 0 ? $totalSeconds : $fallbackSeconds];
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private function reportRenderingStatus(int $sceneCount, int $totalSeconds, string $jsonString, array $decoded): void
    {
        self::putStatus($this->jobId, [
            'status' => 'rendering_video',
            'step' => 2,
            'total_steps' => 2,
            'version' => $this->versionNumber,
            'scene_count' => $sceneCount,
            'duration_seconds' => $totalSeconds,
            'json_script' => $jsonString,
            'parsed_script' => $decoded,
            'message' => "Paso 2/2: Renderizando video con Remotion ({$sceneCount} escenas, {$totalSeconds}s)...",
            'detail' => 'Generando animaciones y codificando el archivo MP4. Esto puede tomar entre 20 y 60 segundos.',
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function renderVideoWithRemotion(string $jsonString): array
    {
        $tempDir = storage_path('app/temp');
        File::ensureDirectoryExists($tempDir);

        $jsonPath = $tempDir . "/reel_{$this->jobId}_v{$this->versionNumber}.json";
        File::put($jsonPath, $jsonString);

        $videoFilename = "preview_{$this->jobId}_v{$this->versionNumber}.mp4";
        $reelsDir = storage_path('app/public/reels');
        File::ensureDirectoryExists($reelsDir);
        $videoPath = "{$reelsDir}/{$videoFilename}";

        Log::info("[GenerateReelPreviewJob] Iniciando Remotion: JSON={$jsonPath}, Video={$videoPath}");

        $result = Process::path(base_path('video-engine'))
            ->timeout(1200)
            ->run("npx remotion render src/index.ts ReelInstitucional {$videoPath} --props={$jsonPath}");

        if (! $result->successful()) {
            Log::error('[GenerateReelPreviewJob] Falló Remotion: ' . $result->errorOutput() . "\n" . $result->output());
            throw new \RuntimeException('Error durante el renderizado de video con Remotion.');
        }

        return [$jsonPath, $videoFilename];
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private function reportCompletedStatus(
        int $sceneCount,
        int $totalSeconds,
        string $jsonString,
        array $decoded,
        string $videoFilename,
        bool $isRefinement
    ): void {
        self::putStatus($this->jobId, [
            'status' => 'completed',
            'step' => 2,
            'total_steps' => 2,
            'version' => $this->versionNumber,
            'scene_count' => $sceneCount,
            'duration_seconds' => $totalSeconds,
            'json_script' => $jsonString,
            'parsed_script' => $decoded,
            'video_filename' => $videoFilename,
            'video_url' => "/storage/reels/{$videoFilename}?v=" . time(),
            'feedback_applied' => $isRefinement ? $this->userFeedback : null,
            'message' => "¡Previsualización v{$this->versionNumber} generada con éxito!",
        ]);
    }

    private function reportFailedStatus(string $errorDetail): void
    {
        self::putStatus($this->jobId, [
            'status' => 'failed',
            'version' => $this->versionNumber,
            'message' => 'No se pudo completar la generación del video.',
            'detail' => $errorDetail,
        ]);
    }

    private function deleteTempJsonFile(?string $jsonPath): void
    {
        if ($jsonPath !== null && File::exists($jsonPath)) {
            File::delete($jsonPath);
        }
    }

    private function buildPrompt(bool $isRefinement): string
    {
        if (! $isRefinement) {
            return $this->notes;
        }

        return <<<PROMPT
        NOTAS ORIGINALES DEL PROYECTO:
        {$this->notes}

        GUION JSON ACTUAL:
        {$this->previousJson}

        SOLICITUD DE MODIFICACIÓN DEL USUARIO:
        {$this->userFeedback}

        Instrucción: Modifica el GUION JSON ACTUAL aplicando exactamente la SOLICITUD DE MODIFICACIÓN DEL USUARIO. Mantén el esquema JSON estricto y devuelve únicamente el JSON crudo resultante.
        PROMPT;
    }

    private function sanitizeJsonResponse(string $raw): string
    {
        $trimmed = trim($raw);

        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/i', $trimmed, $matches)) {
            $trimmed = trim($matches[1]);
        }

        $firstBrace = strpos($trimmed, '{');
        $lastBrace = strrpos($trimmed, '}');
        if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
            $trimmed = substr($trimmed, $firstBrace, $lastBrace - $firstBrace + 1);
        }

        return $trimmed;
    }
}
