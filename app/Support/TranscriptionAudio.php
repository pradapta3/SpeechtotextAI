<?php

declare(strict_types=1);

namespace App\Support;

class TranscriptionAudio
{
    /**
     * Whisper bekerja pada 16 kHz mono. Angka yang sama dipakai encoder di
     * browser (resources/js/audio/chunker.js).
     */
    public const SAMPLE_RATE = 16000;
}
