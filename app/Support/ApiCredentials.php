<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Crypt;

/**
 * Menyimpan dan menyelesaikan API key.
 *
 * Key milik pengguna dienkripsi dengan APP_KEY lalu ditaruh di session server —
 * berbeda dengan versi statis lama yang menyimpannya di localStorage browser.
 * Jika pengguna tidak mengisi key sendiri, key dari .env yang dipakai.
 */
class ApiCredentials
{
    public const GROQ = 'groq';

    public const ANTHROPIC = 'anthropic';

    public function __construct(private readonly Session $session) {}

    public function transcriptionKey(): ?string
    {
        return $this->userKey(self::GROQ)
            ?? $this->stringOrNull(config('notulensi.transcription.api_key'));
    }

    public function minutesKey(): ?string
    {
        return $this->userKey(self::ANTHROPIC)
            ?? $this->stringOrNull(config('notulensi.minutes.api_key'));
    }

    public function hasUserKey(string $provider): bool
    {
        return $this->userKey($provider) !== null;
    }

    public function hasServerKey(string $provider): bool
    {
        return $this->stringOrNull(config(match ($provider) {
            self::GROQ => 'notulensi.transcription.api_key',
            self::ANTHROPIC => 'notulensi.minutes.api_key',
        })) !== null;
    }

    public function storeUserKey(string $provider, ?string $key): void
    {
        $key = $this->stringOrNull($key);

        if ($key === null) {
            $this->session->forget($this->sessionKey($provider));

            return;
        }

        $this->session->put($this->sessionKey($provider), Crypt::encryptString($key));
    }

    public function forgetUserKeys(): void
    {
        foreach ([self::GROQ, self::ANTHROPIC] as $provider) {
            $this->session->forget($this->sessionKey($provider));
        }
    }

    /**
     * Empat karakter terakhir saja — cukup untuk memastikan key yang benar
     * tersimpan tanpa pernah menampilkan ulang key-nya.
     */
    public function maskUserKey(string $provider): ?string
    {
        $key = $this->userKey($provider);

        return $key === null ? null : '••••'.substr($key, -4);
    }

    private function userKey(string $provider): ?string
    {
        if (! config('notulensi.allow_user_keys')) {
            return null;
        }

        $stored = $this->session->get($this->sessionKey($provider));

        if (! is_string($stored) || $stored === '') {
            return null;
        }

        try {
            return $this->stringOrNull(Crypt::decryptString($stored));
        } catch (DecryptException) {
            // APP_KEY berubah / session rusak — buang saja, pengguna tinggal isi ulang.
            $this->session->forget($this->sessionKey($provider));

            return null;
        }
    }

    private function sessionKey(string $provider): string
    {
        return "notulensi.keys.{$provider}";
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
