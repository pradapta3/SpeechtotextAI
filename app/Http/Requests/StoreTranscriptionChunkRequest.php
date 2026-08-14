<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTranscriptionChunkRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'audio' => [
                'required',
                'file',
                'mimetypes:audio/*',
                'max:'.(int) config('notulensi.transcription.max_chunk_kilobytes'),
            ],
            'index' => ['required', 'integer', 'min:0', 'max:19999'],
            'start_seconds' => ['required', 'numeric', 'min:0'],
            'end_seconds' => ['required', 'numeric', 'gte:start_seconds'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'audio.mimetypes' => 'Segmen yang dikirim bukan berkas audio.',
            'audio.max' => 'Segmen audio terlalu besar. Perkecil durasi segmen di Pengaturan.',
        ];
    }
}
