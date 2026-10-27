<?php

declare(strict_types=1);

namespace App\Services\Log;

use App\Contracts\LogServiceInterface;
use App\DTOs\LogFile;
use App\DTOs\LogQuery;
use App\DTOs\LogResult;
use Illuminate\Support\Facades\File;

class LogService implements LogServiceInterface
{
    private const MAX_SIZE = 10 * 1024 * 1024; // 10MB

    /**
     * @return list<LogFile>
     */
    public function list(): array
    {
        $dir = storage_path('logs');
        if (! File::isDirectory($dir)) {
            return [];
        }

        $list = [];
        foreach (File::files($dir) as $file) {
            if ($file->getExtension() !== 'log') {
                continue;
            }

            $list[] = LogFile::make(
                $file->getFilename(),
                $this->size($file->getSize()),
                date('Y-m-d H:i:s', $file->getMTime())
            );
        }

        return $list;
    }

    public function read(LogQuery $query): LogResult
    {
        $path = $this->path($query->file);
        if ($path === null) {
            return LogResult::fail('Log file not found');
        }

        $bytes = File::size($path);
        if ($bytes > self::MAX_SIZE) {
            $body = $this->tail($path, $query->lines, $query->term);
            $warn = 'File is too large. Showing last '.$query->lines.' lines.';

            return LogResult::ok($body, $this->size($bytes), 0, $warn);
        }

        $raw = File::get($path);
        $text = $this->filter($raw, $query->term);
        $lines = explode("\n", $text);
        $slice = array_slice($lines, -$query->lines);

        return LogResult::ok(implode("\n", $slice), $this->size($bytes), count($lines));
    }

    public function path(string $file): ?string
    {
        $clean = basename($file === '' ? 'laravel.log' : $file);
        $path = storage_path('logs/'.$clean);

        return File::exists($path) ? $path : null;
    }

    public function clear(string $file): bool
    {
        $path = $this->path($file);
        if ($path === null) {
            return false;
        }

        File::put($path, '');

        return true;
    }

    public function tail(string $path, int $lines, string $term = ''): string
    {
        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $start = max(0, $file->key() - $lines);
        $file->seek($start);

        $rows = [];
        while (! $file->eof()) {
            $line = $file->current();
            if (is_string($line) && $this->match($line, $term)) {
                $rows[] = $line;
            }
            $file->next();
        }

        return implode('', $rows);
    }

    public function filter(string $text, string $term): string
    {
        if ($term === '') {
            return $text;
        }

        $lines = explode("\n", $text);
        $valid = array_filter($lines, static fn (string $l): bool => stripos($l, $term) !== false);

        return implode("\n", $valid);
    }

    public function size(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $val = (float) $bytes;
        $i = 0;

        while ($val >= 1024 && $i < count($units) - 1) {
            $val /= 1024;
            $i++;
        }

        return round($val, 2).' '.$units[$i];
    }

    public function match(string $line, string $term): bool
    {
        return $term === '' || stripos($line, $term) !== false;
    }
}

