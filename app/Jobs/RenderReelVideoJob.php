<?php

declare(strict_types=1);

namespace App\Jobs;

use App\DTOs\ReelGenerationStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class RenderReelVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1200;

    public int $tries = 1;

    public function __construct(
        public int $postId,
        public int $versionNumber = 1,
    ) {
        $this->onQueue('videos');
    }

    public function handle(): void
    {
        $post = DB::table('posts')->where('id', $this->postId)->first(['ai_script']);
        $rawScript = $post instanceof \stdClass && isset($post->ai_script) && is_string($post->ai_script)
            ? trim($post->ai_script)
            : '';

        if ($rawScript === '') {
            $this->reportFailedStatus('No se encontró el guion estructurado para renderizar el video.');

            return;
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($rawScript, true);
        $parsedScript = is_array($decoded) ? $decoded : [];

        /** @var list<array<string, mixed>> $scenes */
        $scenes = is_array($parsedScript['scenes'] ?? null) ? $parsedScript['scenes'] : [];
        $sceneCount = count($scenes);
        $totalSeconds = 0;
        foreach ($scenes as $scene) {
            $duration = $scene['duration_seconds'] ?? 0;
            $totalSeconds += is_numeric($duration) ? (int) $duration : 0;
        }

        $this->reportRenderingStatus($sceneCount, $totalSeconds, $rawScript, $parsedScript);

        $jsonPath = null;
        try {
            [$jsonPath, $videoFilename] = $this->renderVideoWithRemotion($rawScript);

            $this->reportCompletedStatus($sceneCount, $totalSeconds, $rawScript, $parsedScript, $videoFilename);
        } catch (\Throwable $e) {
            Log::error('[RenderReelVideoJob] Error: ' . $e->getMessage());
            $this->reportFailedStatus($e->getMessage());
        } finally {
            $this->deleteTempJsonFile($jsonPath);
        }
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    private function reportRenderingStatus(int $sceneCount, int $totalSeconds, string $jsonString, array $decoded): void
    {
        GenerateReelPreviewJob::putStatus($this->postId, ReelGenerationStatus::renderingVideo(
            $this->versionNumber,
            $sceneCount,
            $totalSeconds,
            $jsonString,
            $decoded,
        ));

        DB::table('posts')->where('id', $this->postId)->update([
            'ai_job_status' => 'rendering_video',
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function renderVideoWithRemotion(string $jsonString): array
    {
        $tempDir = storage_path('app/temp');
        File::ensureDirectoryExists($tempDir);

        $jsonPath = $tempDir . "/reel_{$this->postId}_v{$this->versionNumber}.json";
        File::put($jsonPath, $jsonString);

        $videoFilename = "preview_{$this->postId}_v{$this->versionNumber}.mp4";
        $reelsDir = storage_path('app/public/reels');
        File::ensureDirectoryExists($reelsDir);
        $videoPath = "{$reelsDir}/{$videoFilename}";

        Log::info("[RenderReelVideoJob] Iniciando Remotion: JSON={$jsonPath}, Video={$videoPath}");

        $result = Process::path(base_path('video-engine'))
            ->timeout(1200)
            ->run("npx remotion render src/index.ts ReelInstitucional {$videoPath} --props={$jsonPath}");

        if (! $result->successful()) {
            Log::error('[RenderReelVideoJob] Falló Remotion: ' . $result->errorOutput() . "\n" . $result->output());
            throw new \RuntimeException('Error durante el renderizado de video con Remotion.');
        }

        return [$jsonPath, $videoFilename];
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    private function reportCompletedStatus(
        int $sceneCount,
        int $totalSeconds,
        string $jsonString,
        array $decoded,
        string $videoFilename
    ): void {
        GenerateReelPreviewJob::putStatus($this->postId, ReelGenerationStatus::completed(
            $this->versionNumber,
            $sceneCount,
            $totalSeconds,
            $jsonString,
            $decoded,
            $videoFilename,
        ));

        DB::table('posts')->where('id', $this->postId)->update([
            'reel_video' => $videoFilename,
            'ai_job_status' => 'completed',
            'updated_at' => now(),
        ]);
    }

    private function reportFailedStatus(string $errorDetail): void
    {
        GenerateReelPreviewJob::putStatus($this->postId, ReelGenerationStatus::failed($this->versionNumber, $errorDetail));

        DB::table('posts')->where('id', $this->postId)->update([
            'ai_job_status' => 'failed',
            'updated_at' => now(),
        ]);
    }

    private function deleteTempJsonFile(?string $jsonPath): void
    {
        if ($jsonPath !== null && File::exists($jsonPath)) {
            File::delete($jsonPath);
        }
    }
}
