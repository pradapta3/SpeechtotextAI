<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\UploadLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecordingRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(UploadLimits $limits): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'size_bytes' => ['required', 'integer', 'min:0', 'max:'.(50 * 1024 * 1024 * 1024)],
            'duration_seconds' => ['required', 'numeric', 'min:0', 'max:86400'],
            'language' => ['required', Rule::in(array_keys(config('notulensi.languages')))],
            'chunk_seconds' => ['required', 'integer', 'between:15,'.$limits->maxChunkSeconds()],
            'total_chunks' => ['required', 'integer', 'between:1,20000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'duration_seconds.max' => 'Durasi audio melebihi 24 jam.',
            'language.in' => 'Bahasa audio tidak dikenali.',
        ];
    }
}
