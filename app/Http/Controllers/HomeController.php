<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Recording;
use App\Support\Markdown;
use App\Support\SessionOwner;
use App\Support\SettingsState;
use App\Support\UploadLimits;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(SessionOwner $owner, SettingsState $settings, UploadLimits $uploads): View
    {
        $recordings = Recording::query()
            ->ownedBy($owner->key())
            ->latest('id')
            ->get()
            ->map(fn (Recording $recording): array => $recording->toPayload() + [
                'minutes_html' => Markdown::toHtml($recording->minutes),
            ])
            ->all();

        return view('notulensi', [
            'initialState' => [
                'recordings' => $recordings,
                'settings' => $settings->toArray(),
                'limits' => [
                    'max_chunk_seconds' => $uploads->maxChunkSeconds(),
                    'recordings_per_session' => (int) config('notulensi.limits.recordings_per_session'),
                ],
            ],
        ]);
    }
}
