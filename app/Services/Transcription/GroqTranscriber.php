<?php

declare(strict_types=1);

namespace App\Services\Transcription;

use App\Exceptions\TranscriptionFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

/**
 * Meneruskan satu segmen audio ke Groq Whisper.
 *
 * Browser yang memotong dan me-resample audio; server hanya memegang API key
 * dan menerjemahkan error Groq menjadi respons yang bisa ditindaklanjuti UI.
 * Rate limit sengaja tidak ditunggu di sisi server (yang akan menahan worker
 * PHP puluhan detik) — statusnya dikembalikan ke browser untuk dicoba ulang.
 */
class GroqTranscriber
{
    public function transcribe(UploadedFile $chunk, string $language, string $apiKey): string
    {
        $maxKilobytes = (int) config('notulensi.transcription.max_chunk_kilobytes');

        if ($chunk->getSize() > $maxKilobytes * 1024) {
            throw TranscriptionFailed::chunkTooLarge($maxKilobytes);
        }

        $payload = ['model' => (string) config('notulensi.transcription.model'), 'response_format' => 'text'];

        if ($language !== 'auto') {
            $payload['language'] = $language;
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout((int) config('notulensi.transcription.timeout'))
                ->retry(2, 300, fn ($exception) => $exception instanceof ConnectionException, throw: false)
                ->attach('file', $chunk->getContent(), $this->filename($chunk))
                ->asMultipart()
                ->post((string) config('notulensi.transcription.endpoint'), $payload);
        } catch (ConnectionException $exception) {
            throw TranscriptionFailed::unreachable($exception->getMessage());
        }

        if ($response->status() === 429) {
            throw TranscriptionFailed::rateLimited($this->retryAfter($response->header('Retry-After')));
        }

        if ($response->failed()) {
            throw TranscriptionFailed::upstream($response->status(), $response->body());
        }

        return trim($response->body());
    }

    private function filename(UploadedFile $chunk): string
    {
        $extension = strtolower($chunk->getClientOriginalExtension() ?: 'wav');

        return 'segment.'.(preg_match('/^[a-z0-9]{1,5}$/', $extension) ? $extension : 'wav');
    }

    private function retryAfter(?string $header): int
    {
        $seconds = is_numeric($header) ? (int) ceil((float) $header) : 20;

        return max(5, min($seconds + 2, (int) config('notulensi.transcription.max_retry_seconds')));
    }
}
