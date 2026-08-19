<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Recording;
use App\Support\SessionOwner;
use App\Support\SettingsState;
use App\Support\UploadLimits;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(SessionOwner $owner, SettingsState $settings, UploadLimits $uploads): View
    {
        return view('notulensi', [
            'initialState' => [
                // Hanya ringkasan: transkrip dan notulensi diambil lewat
                // /api/recordings/{id} saat rekaman dibuka.
                'recordings' => Recording::query()
                    ->ownedBy($owner->key())
                    ->latest('id')
                    ->get()
                    ->map->toSummary()
                    ->all(),
                'settings' => $settings->toArray(),
                'limits' => [
                    'max_chunk_seconds' => $uploads->maxChunkSeconds(),
                    'max_chunk_megabytes' => round($uploads->maxChunkBytes() / 1024 / 1024, 1),
                    'recordings_per_session' => (int) config('notulensi.limits.recordings_per_session'),
                ],
                'models' => [
                    'transcription' => (string) config('notulensi.transcription.model'),
                    'minutes' => (string) config('notulensi.minutes.model'),
                    'effort' => (string) config('notulensi.minutes.effort'),
                ],
            ],
        ]);
    }
}
