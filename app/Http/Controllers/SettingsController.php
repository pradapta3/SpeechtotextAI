<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Support\ApiCredentials;
use App\Support\SettingsState;
use App\Support\UserPreferences;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    public function __construct(
        private readonly ApiCredentials $credentials,
        private readonly UserPreferences $preferences,
        private readonly SettingsState $state,
    ) {}

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $allowUserKeys = (bool) config('notulensi.allow_user_keys');
        $submittedKeys = [
            ApiCredentials::GROQ => $request->validated('groq_key'),
            ApiCredentials::ANTHROPIC => $request->validated('anthropic_key'),
        ];

        if ($request->boolean('forget_keys')) {
            $this->credentials->forgetUserKeys();
        } elseif ($allowUserKeys) {
            foreach ($submittedKeys as $provider => $key) {
                // Kolom kosong berarti "biarkan seperti sekarang"; untuk menghapus
                // key tersimpan gunakan tombol Hapus (forget_keys).
                if (filled($key)) {
                    $this->credentials->storeUserKey($provider, $key);
                }
            }
        } elseif (filled($submittedKeys[ApiCredentials::GROQ]) || filled($submittedKeys[ApiCredentials::ANTHROPIC])) {
            throw ValidationException::withMessages([
                'groq_key' => 'Aplikasi ini dikonfigurasi memakai API key dari server.',
            ]);
        }

        $this->preferences->merge([
            'language' => $request->validated('language'),
            'chunk_seconds' => $request->validated('chunk_seconds'),
        ]);

        return response()->json(['settings' => $this->state->toArray()]);
    }
}
