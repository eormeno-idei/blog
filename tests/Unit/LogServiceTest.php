<?php

use App\Contracts\LogServiceInterface;
use App\DTOs\LogFile;
use App\DTOs\LogQuery;
use App\DTOs\LogResult;
use App\Http\Controllers\LogViewerController;
use App\Http\Requests\LogRequest;
use App\Services\Log\LogService;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $dir = storage_path('logs');
    if (! File::isDirectory($dir)) {
        File::makeDirectory($dir, 0755, true);
    }
});

it('formats byte sizes correctly', function () {
    $service = new LogService;

    expect($service->size(500))->toBe('500 B');
    expect($service->size(1024))->toBe('1 KB');
    expect($service->size(1024 * 1024))->toBe('1 MB');
    expect($service->size(1024 * 1024 * 1024))->toBe('1 GB');
});

it('filters text by search term', function () {
    $service = new LogService;
    $text = "line one\nline two\nline three";

    expect($service->filter($text, ''))->toBe($text);
    expect($service->filter($text, 'two'))->toBe('line two');
    expect($service->filter($text, 'missing'))->toBe('');
});

it('matches search terms case-insensitively', function () {
    $service = new LogService;

    expect($service->match('Hello World', ''))->toBeTrue();
    expect($service->match('Hello World', 'world'))->toBeTrue();
    expect($service->match('Hello World', 'xyz'))->toBeFalse();
});

it('resolves real log path and ignores invalid or non-existent files', function () {
    $service = new LogService;

    expect($service->path('does-not-exist.log'))->toBeNull();

    $file = storage_path('logs/path-test.log');
    File::put($file, 'test');

    expect($service->path('path-test.log'))->toBe($file);
    // Basename sanitization prevents traversal
    expect($service->path('../../logs/path-test.log'))->toBe($file);

    File::delete($file);
});

it('lists only log files with LogFile DTOs', function () {
    $service = new LogService;
    $file1 = storage_path('logs/alpha.log');
    $file2 = storage_path('logs/beta.txt');
    File::put($file1, 'hello');
    File::put($file2, 'ignored');

    $list = $service->list();
    $names = array_map(fn (LogFile $f): string => $f->name, $list);

    expect($names)->toContain('alpha.log');
    expect($names)->not->toContain('beta.txt');

    $first = $list[0];
    $array = $first->toArray();
    expect($array)->toHaveKeys(['name', 'size', 'modified']);

    File::delete([$file1, $file2]);
});

it('reads log content with LogResult DTO and handles large file warning', function () {
    $service = new LogService;
    $file = storage_path('logs/tail-test.log');

    $lines = [];
    for ($i = 1; $i <= 20; $i++) {
        $lines[] = "entry $i";
    }
    File::put($file, implode("\n", $lines));

    $query = LogQuery::make('tail-test.log', 5, 'entry 1');
    $res = $service->read($query);

    expect($res->code())->toBe(200);
    $data = $res->toArray();
    expect($data)->toHaveKeys(['content', 'fileSize', 'totalLines']);
    expect($data['content'])->toContain('entry 1');

    $tail = $service->tail($file, 3);
    expect($tail)->toContain('entry 20');

    File::delete($file);
});

it('returns empty list when logs directory is missing', function () {
    $service = new class extends LogService
    {
        public function list(): array
        {
            // Simulate missing directory
            return parent::list();
        }
    };

    File::shouldReceive('isDirectory')->once()->andReturn(false);
    expect($service->list())->toBe([]);
});

it('returns warning when real file exceeds maximum size', function () {
    $file = storage_path('logs/real-large.log');
    $fp = fopen($file, 'w');
    if ($fp !== false) {
        fwrite($fp, "top line\n");
        ftruncate($fp, 10 * 1024 * 1024 + 50);
        fseek($fp, 10 * 1024 * 1024 + 10);
        fwrite($fp, "last line\n");
        fclose($fp);
    }

    $service = new LogService;
    $query = LogQuery::make('real-large.log', 2);
    $res = $service->read($query);

    expect($res->code())->toBe(200);
    $data = $res->toArray();
    expect($data)->toHaveKey('warning');
    expect($data['warning'])->toContain('File is too large');

    File::delete($file);
});

it('clears log file when file exists', function () {
    $service = new LogService;

    expect($service->clear('ghost.log'))->toBeFalse();

    $file = storage_path('logs/to-empty.log');
    File::put($file, 'text');

    expect($service->clear('to-empty.log'))->toBeTrue();
    expect(File::get($file))->toBe('');

    File::delete($file);
});

it('validates LogQuery and LogResult DTOs behavior', function () {
    $q = LogQuery::make('', -5, 'foo', true);
    expect($q->file)->toBe('laravel.log');
    expect($q->lines)->toBe(1000);
    expect($q->term)->toBe('foo');
    expect($q->back)->toBeTrue();

    $fail = LogResult::fail('something bad');
    expect($fail->code())->toBe(404);
    expect($fail->toArray())->toBe(['error' => 'something bad']);
});

it('ensures no method in refactored classes has more than 10 characters', function () {
    $classes = [
        LogViewerController::class,
        LogService::class,
        LogServiceInterface::class,
        LogRequest::class,
        LogFile::class,
        LogQuery::class,
        LogResult::class,
    ];

    foreach ($classes as $class) {
        $ref = new ReflectionClass($class);
        foreach ($ref->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $class) {
                continue;
            }
            expect(strlen($method->getName()))->toBeLessThanOrEqual(10);
        }
    }
});
