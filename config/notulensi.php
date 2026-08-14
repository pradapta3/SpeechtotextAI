<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Transkripsi (Groq Whisper)
    |--------------------------------------------------------------------------
    |
    | Audio dipotong menjadi segmen di browser lalu dikirim ke server. Server
    | yang meneruskannya ke Groq, sehingga API key tidak pernah dikirim ke
    | browser.
    |
    */

    'transcription' => [
        'endpoint' => env('GROQ_ENDPOINT', 'https://api.groq.com/openai/v1/audio/transcriptions'),
        'api_key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'whisper-large-v3-turbo'),
        'timeout' => (int) env('GROQ_TIMEOUT', 120),

        // Groq menolak file di atas 25 MB. Batas ini dipakai untuk validasi
        // upload sekaligus sebagai acuan durasi segmen di sisi browser.
        'max_chunk_kilobytes' => (int) env('GROQ_MAX_CHUNK_KILOBYTES', 20480),

        // Percobaan ulang saat kena rate limit (HTTP 429).
        'max_attempts' => (int) env('GROQ_MAX_ATTEMPTS', 3),
        'max_retry_seconds' => (int) env('GROQ_MAX_RETRY_SECONDS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notulensi (Claude)
    |--------------------------------------------------------------------------
    */

    'minutes' => [
        'endpoint' => env('ANTHROPIC_ENDPOINT', 'https://api.anthropic.com'),
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5'),
        'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 16000),
        'effort' => env('ANTHROPIC_EFFORT', 'medium'),
        'timeout' => (int) env('ANTHROPIC_TIMEOUT', 300),

        // Panjang minimum transkrip sebelum notulensi boleh dibuat.
        'min_transcript_characters' => 200,
    ],

    /*
    |--------------------------------------------------------------------------
    | API key milik pengguna
    |--------------------------------------------------------------------------
    |
    | Jika diaktifkan, pengguna boleh memakai API key sendiri lewat halaman
    | Pengaturan. Key dienkripsi dengan APP_KEY lalu disimpan di session server
    | (tidak pernah masuk localStorage) dan menimpa key dari .env.
    |
    */

    'allow_user_keys' => (bool) env('NOTULENSI_ALLOW_USER_KEYS', true),

    /*
    |--------------------------------------------------------------------------
    | Default perekaman
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'language' => env('NOTULENSI_DEFAULT_LANGUAGE', 'id'),
        'chunk_seconds' => (int) env('NOTULENSI_CHUNK_SECONDS', 60),
    ],

    'languages' => [
        'id' => 'Indonesia',
        'auto' => 'Deteksi otomatis',
        'en' => 'English',
        'jv' => 'Jawa',
        'su' => 'Sunda',
        'ms' => 'Melayu',
    ],

    /*
    |--------------------------------------------------------------------------
    | Batas penyimpanan
    |--------------------------------------------------------------------------
    */

    'limits' => [
        'recordings_per_session' => (int) env('NOTULENSI_MAX_RECORDINGS', 50),
        'transcript_characters' => (int) env('NOTULENSI_MAX_TRANSCRIPT_CHARS', 400000),
    ],

];
