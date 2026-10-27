<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\LogQuery;
use Illuminate\Foundation\Http\FormRequest;

class LogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'file' => 'nullable|string',
            'lines' => 'nullable',
            'search' => 'nullable|string',
            'redirect' => 'nullable',
        ];
    }

    public function dto(): LogQuery
    {
        $rawFile = $this->input('file') ?? $this->query('file', 'laravel.log');
        $file = is_string($rawFile) && $rawFile !== '' ? $rawFile : 'laravel.log';

        $rawLines = $this->input('lines') ?? $this->query('lines', 1000);
        $lines = is_numeric($rawLines) ? (int) $rawLines : 1000;

        $rawSearch = $this->input('search') ?? $this->query('search', '');
        $term = is_string($rawSearch) ? $rawSearch : '';

        $rawBack = $this->input('redirect') ?? $this->query('redirect', false);
        $back = filter_var($rawBack, FILTER_VALIDATE_BOOLEAN);

        return LogQuery::make($file, $lines, $term, $back);
    }
}
