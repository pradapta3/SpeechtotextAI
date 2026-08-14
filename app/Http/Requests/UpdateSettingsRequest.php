<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\UploadLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(UploadLimits $limits): array
    {
        return [
            'groq_key' => ['nullable', 'string', 'max:255'],
            'anthropic_key' => ['nullable', 'string', 'max:255'],
            'forget_keys' => ['nullable', 'boolean'],
            'language' => ['nullable', Rule::in(array_keys(config('notulensi.languages')))],
            'chunk_seconds' => ['nullable', 'integer', 'between:15,'.$limits->maxChunkSeconds()],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'chunk_seconds.between' => 'Durasi segmen harus antara :min dan :max detik '
                .'(dibatasi oleh ukuran unggahan maksimum server).',
        ];
    }
}
