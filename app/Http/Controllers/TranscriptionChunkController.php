<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RecordingStatus;
use App\Exceptions\TranscriptionFailed;
use App\Http\Requests\StoreTranscriptionChunkRequest;
use App\Models\Recording;
use App\Services\Transcription\GroqTranscriber;
use App\Support\ApiCredentials;
use Illuminate\Http\JsonResponse;

class TranscriptionChunkController extends Controller
{
    public function __construct(
        private readonly GroqTranscriber $transcriber,
        private readonly ApiCredentials $credentials,
    ) {}

    public function store(StoreTranscriptionChunkRequest $request, Recording $recording): JsonResponse
    {
        if ($recording->status === RecordingStatus::Cancelled) {
            return response()->json(['message' => 'Transkripsi rekaman ini sudah dibatalkan.'], 409);
        }

        $apiKey = $this->credentials->transcriptionKey();

        if ($apiKey === null) {
            throw TranscriptionFailed::missingApiKey();
        }

        if (mb_strlen($recording->transcript()) > (int) config('notulensi.limits.transcript_characters')) {
            throw new TranscriptionFailed(
                'Transkrip sudah mencapai batas panjang maksimum. Bagi rekaman menjadi beberapa berkas.',
                status: 422,
            );
        }

        $text = $this->transcriber->transcribe(
            $request->file('audio'),
            $recording->language,
            $apiKey,
        );

        $recording->storeSegment(
            (int) $request->validated('index'),
            (float) $request->validated('start_seconds'),
            (float) $request->validated('end_seconds'),
            $text,
        );

        $recording->status = $recording->completedChunks() >= $recording->total_chunks
            ? RecordingStatus::Completed
            : RecordingStatus::Processing;
        $recording->error = null;
        $recording->save();

        return response()->json([
            'text' => $text,
            'recording' => $recording->toPayload(),
        ]);
    }
}
