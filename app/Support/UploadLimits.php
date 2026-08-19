<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Segmen dikirim sebagai WAV 16 kHz mono 16-bit, jadi ukurannya bisa dihitung
 * persis dari durasi. Batas PHP (upload_max_filesize / post_max_size) sering
 * lebih kecil dari batas Groq, dan kalau terlampaui permintaan ditolak sebelum
 * sampai ke Laravel — karena itu durasi segmen maksimum diturunkan dari batas
 * yang paling ketat lalu dikirim ke browser.
 */
class UploadLimits
{
    private const BYTES_PER_SECOND = TranscriptionAudio::SAMPLE_RATE * 2;

    private const WAV_HEADER_BYTES = 44;

    public function maxChunkBytes(): int
    {
        $limits = array_filter([
            (int) config('notulensi.transcription.max_chunk_kilobytes') * 1024,
            $this->iniBytes('upload_max_filesize'),
            // Multipart membawa field lain juga; sisakan sedikit ruang.
            $this->iniBytes('post_max_size') > 0 ? $this->iniBytes('post_max_size') - 16 * 1024 : 0,
        ], static fn (int $value): bool => $value > 0);

        return $limits === [] ? PHP_INT_MAX : (int) min($limits);
    }

    public function maxChunkSeconds(): int
    {
        $seconds = (int) floor(($this->maxChunkBytes() - self::WAV_HEADER_BYTES) / self::BYTES_PER_SECOND);

        return max(15, min(600, $seconds));
    }

    /** Mengubah notasi ini PHP ("8M", "512K") menjadi byte. */
    private function iniBytes(string $directive): int
    {
        $value = trim((string) ini_get($directive));

        if ($value === '' || $value === '-1' || $value === '0') {
            return 0;
        }

        $multiplier = match (strtolower(substr($value, -1))) {
            'g' => 1024 ** 3,
            'm' => 1024 ** 2,
            'k' => 1024,
            default => 1,
        };

        return (int) $value * $multiplier;
    }
}
