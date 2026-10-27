<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\LogServiceInterface;
use App\DTOs\LogFile;
use App\Http\Requests\LogRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LogViewerController extends Controller
{
    public function index(LogRequest $req, LogServiceInterface $logs): View
    {
        return view('logs.viewer', [
            'logFiles' => array_map(static fn (LogFile $f): array => $f->toArray(), $logs->list()),
            'selectedFile' => $req->dto()->file,
        ]);
    }

    public function content(LogRequest $req, LogServiceInterface $logs): JsonResponse
    {
        $res = $logs->read($req->dto());

        return response()->json($res->toArray(), $res->code());
    }

    public function download(LogRequest $req, LogServiceInterface $logs): BinaryFileResponse
    {
        $dto = $req->dto();
        $path = $logs->path($dto->file);

        if ($path === null) {
            abort(404, 'Log file not found');
        }

        return Response::download($path, $dto->file);
    }

    public function clear(LogRequest $req, LogServiceInterface $logs): JsonResponse|RedirectResponse
    {
        $dto = $req->dto();

        try {
            $ok = $logs->clear($dto->file);
            $msg = $ok ? 'Log file cleared successfully' : 'Log file not found';
            $code = $ok ? 200 : 404;

            return $dto->back ? $this->back($ok, $msg) : $this->resp($ok, $msg, $code);
        } catch (\Throwable $e) {
            $msg = 'Error clearing log: '.$e->getMessage();

            return $dto->back ? $this->back(false, $msg) : $this->resp(false, $msg, 500);
        }
    }

    private function back(bool $ok, string $msg): RedirectResponse
    {
        $key = $ok ? 'success' : 'error';

        return redirect()->route('logs.index')->with($key, $msg);
    }

    private function resp(bool $ok, string $msg, int $code = 200): JsonResponse
    {
        $key = $ok ? 'message' : 'error';

        return response()->json(['success' => $ok, $key => $msg], $code);
    }
}
