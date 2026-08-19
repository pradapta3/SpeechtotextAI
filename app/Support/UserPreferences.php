<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Session\Session;

class UserPreferences
{
    private const KEY = 'notulensi.preferences';

    public function __construct(
        private readonly Session $session,
        private readonly UploadLimits $uploadLimits,
    ) {}

    /** @return array{language: string, chunk_seconds: int} */
    public function all(): array
    {
        $stored = $this->session->get(self::KEY, []);
        $stored = is_array($stored) ? $stored : [];

        $language = $stored['language'] ?? config('notulensi.defaults.language');
        $chunkSeconds = (int) ($stored['chunk_seconds'] ?? config('notulensi.defaults.chunk_seconds'));

        return [
            'language' => array_key_exists($language, config('notulensi.languages'))
                ? (string) $language
                : (string) config('notulensi.defaults.language'),
            // Batas unggah server bisa lebih kecil dari nilai default; kalau
            // begitu durasi segmen diturunkan agar unggahan tidak ditolak.
            'chunk_seconds' => max(15, min($chunkSeconds, $this->uploadLimits->maxChunkSeconds())),
        ];
    }

    public function merge(array $values): void
    {
        $this->session->put(self::KEY, array_merge($this->all(), array_filter(
            $values,
            static fn (mixed $value): bool => $value !== null,
        )));
    }
}
