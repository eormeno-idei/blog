<?php

use App\Contracts\LogServiceInterface;
use App\DTOs\LogQuery;
use App\DTOs\LogResult;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $dir = storage_path('logs');
    if (! File::isDirectory($dir)) {
        File::makeDirectory($dir, 0755, true);
    }
});

it('renders the log viewer index screen with log files', function () {
    $file = storage_path('logs/test-viewer.log');
    File::put($file, "line 1\nline 2\n");

    $res = $this->get(route('logs.index'));

    $res->assertOk();
    $res->assertViewIs('logs.viewer');
    $res->assertViewHas('logFiles');
    $res->assertViewHas('selectedFile', 'laravel.log');

    File::delete($file);
});

it('returns 404 when log file for content does not exist', function () {
    $res = $this->get(route('logs.content', ['file' => 'non-existent.log']));

    $res->assertStatus(404);
    $res->assertJson(['error' => 'Log file not found']);
});

it('returns log content, size, and line count for existing log file', function () {
    $file = storage_path('logs/sample.log');
    File::put($file, "error: line 1\ninfo: line 2\nerror: line 3\n");

    $res = $this->get(route('logs.content', ['file' => 'sample.log', 'lines' => 2]));

    $res->assertOk();
    $res->assertJsonStructure(['content', 'fileSize', 'totalLines']);
    $data = $res->json();
    expect($data['totalLines'])->toBe(4); // trailing newline creates 4th empty line in explode
    expect($data['content'])->toContain('error: line 3');

    // Filter with search term
    $searchRes = $this->get(route('logs.content', ['file' => 'sample.log', 'search' => 'info']));
    $searchRes->assertOk();
    expect($searchRes->json('content'))->toContain('info: line 2');
    expect($searchRes->json('content'))->not->toContain('error: line 1');

    File::delete($file);
});

it('downloads log file or aborts 404 when missing', function () {
    $missingRes = $this->get(route('logs.download', ['file' => 'missing.log']));
    $missingRes->assertStatus(404);

    $file = storage_path('logs/sample-dl.log');
    File::put($file, 'download content');

    $res = $this->get(route('logs.download', ['file' => 'sample-dl.log']));
    $res->assertOk();

    File::delete($file);
});

it('clears log file with json response', function () {
    $file = storage_path('logs/to-clear.log');
    File::put($file, 'something here');

    $res = $this->post(route('logs.clear'), ['file' => 'to-clear.log']);
    $res->assertOk();
    $res->assertJson([
        'success' => true,
        'message' => 'Log file cleared successfully',
    ]);
    expect(File::get($file))->toBe('');

    $missingRes = $this->post(route('logs.clear'), ['file' => 'not-here.log']);
    $missingRes->assertStatus(404);
    $missingRes->assertJson([
        'success' => false,
        'error' => 'Log file not found',
    ]);

    File::delete($file);
});

it('clears log file with redirect response', function () {
    $file = storage_path('logs/to-clear-redir.log');
    File::put($file, 'some text');

    $res = $this->post(route('logs.clear'), [
        'file' => 'to-clear-redir.log',
        'redirect' => true,
    ]);
    $res->assertRedirect(route('logs.index'));
    $res->assertSessionHas('success', 'Log file cleared successfully');

    $missingRes = $this->post(route('logs.clear'), [
        'file' => 'not-found.log',
        'redirect' => true,
    ]);
    $missingRes->assertRedirect(route('logs.index'));
    $missingRes->assertSessionHas('error', 'Log file not found');

    File::delete($file);
});

it('handles exceptions when clearing logs', function () {
    $mock = new class implements LogServiceInterface
    {
        public function list(): array
        {
            return [];
        }

        public function read(LogQuery $q): LogResult
        {
            return LogResult::fail('err');
        }

        public function path(string $f): ?string
        {
            return null;
        }

        public function clear(string $f): bool
        {
            throw new RuntimeException('Disk error');
        }
    };

    app()->instance(LogServiceInterface::class, $mock);

    $jsonRes = $this->post(route('logs.clear'), ['file' => 'fake.log']);
    $jsonRes->assertStatus(500);
    $jsonRes->assertJson([
        'success' => false,
        'error' => 'Error clearing log: Disk error',
    ]);

    $redirRes = $this->post(route('logs.clear'), [
        'file' => 'fake.log',
        'redirect' => true,
    ]);
    $redirRes->assertRedirect(route('logs.index'));
    $redirRes->assertSessionHas('error', 'Error clearing log: Disk error');
});
