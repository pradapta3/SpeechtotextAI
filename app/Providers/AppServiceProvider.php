<?php

namespace App\Providers;

use Anthropic\Client;
use App\Models\Recording;
use App\Services\Minutes\ClaudeMinutesGenerator;
use App\Services\Minutes\MeetingMinutesPrompt;
use App\Services\Minutes\MinutesGenerator;
use App\Support\SessionOwner;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MinutesGenerator::class, fn ($app) => new ClaudeMinutesGenerator(
            $app->make(MeetingMinutesPrompt::class),
            fn (string $apiKey): Client => new Client(
                apiKey: $apiKey,
                baseUrl: (string) config('notulensi.minutes.endpoint'),
            ),
        ));
    }

    public function boot(): void
    {
        $this->bindRecordingsToSession();
        $this->configureRateLimiting();
    }

    /**
     * Rekaman selalu dicari dalam lingkup session pemanggil, sehingga id milik
     * orang lain menghasilkan 404 alih-alih membocorkan transkrip.
     */
    private function bindRecordingsToSession(): void
    {
        Route::bind('recording', fn (string $value): Recording => Recording::query()
            ->ownedBy($this->app->make(SessionOwner::class)->key())
            ->findOrFail($value));
    }

    private function configureRateLimiting(): void
    {
        // Satu segmen ≈ satu permintaan; rekaman satu jam dengan segmen 2 menit
        // hanya butuh 30 permintaan, jadi batas ini longgar untuk pemakaian wajar
        // tapi tetap membatasi penyalahgunaan kunci API server.
        RateLimiter::for('transcription', fn (Request $request) => Limit::perMinute(120)
            ->by($request->session()->getId()));

        RateLimiter::for('minutes', fn (Request $request) => Limit::perMinute(10)
            ->by($request->session()->getId()));
    }
}
