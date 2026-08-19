<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\MinutesGenerationFailed;
use App\Http\Requests\GenerateMinutesRequest;
use App\Models\Recording;
use App\Services\Minutes\MinutesGenerator;
use App\Support\ApiCredentials;
use Illuminate\Http\JsonResponse;

class MinutesController extends Controller
{
    public function __construct(
        private readonly MinutesGenerator $generator,
        private readonly ApiCredentials $credentials,
    ) {}

    public function store(GenerateMinutesRequest $request, Recording $recording): JsonResponse
    {
        $recording->fill($request->validated())->save();

        $transcript = $recording->transcript();
        $minimum = (int) config('notulensi.minutes.min_transcript_characters');

        if (mb_strlen($transcript) < $minimum) {
            throw MinutesGenerationFailed::transcriptTooShort($minimum);
        }

        $apiKey = $this->credentials->minutesKey();

        if ($apiKey === null) {
            throw MinutesGenerationFailed::missingApiKey();
        }

        $recording->minutes = $this->generator->generate($recording, $transcript, $apiKey);
        $recording->minutes_model = (string) config('notulensi.minutes.model');
        $recording->minutes_generated_at = now();
        $recording->save();

        return response()->json(['recording' => $recording->toDetail()]);
    }
}
