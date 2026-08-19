<?php

declare(strict_types=1);

/*
 * Hanya aturan yang benar-benar dipakai aplikasi ini yang diterjemahkan,
 * sisanya jatuh ke bahasa Inggris lewat APP_FALLBACK_LOCALE.
 */

return [
    'between' => [
        'array' => ':attribute harus berisi antara :min sampai :max item.',
        'file' => ':attribute harus berukuran antara :min sampai :max kilobyte.',
        'numeric' => ':attribute harus bernilai antara :min sampai :max.',
        'string' => ':attribute harus berisi antara :min sampai :max karakter.',
    ],
    'boolean' => 'Isian :attribute harus bernilai benar atau salah.',
    'date' => ':attribute bukan tanggal yang sah.',
    'file' => ':attribute harus berupa berkas.',
    'gte' => [
        'numeric' => ':attribute harus lebih besar atau sama dengan :value.',
    ],
    'in' => 'Pilihan :attribute tidak sah.',
    'integer' => ':attribute harus berupa bilangan bulat.',
    'max' => [
        'file' => ':attribute tidak boleh lebih dari :max kilobyte.',
        'numeric' => ':attribute tidak boleh lebih dari :max.',
        'string' => ':attribute tidak boleh lebih dari :max karakter.',
    ],
    'mimetypes' => ':attribute harus berupa berkas bertipe: :values.',
    'min' => [
        'numeric' => ':attribute minimal :min.',
        'string' => ':attribute minimal :min karakter.',
    ],
    'numeric' => ':attribute harus berupa angka.',
    'required' => ':attribute wajib diisi.',
    'string' => ':attribute harus berupa teks.',

    'attributes' => [
        'anthropic_key' => 'Anthropic API key',
        'audio' => 'Segmen audio',
        'chunk_seconds' => 'Durasi segmen',
        'duration_seconds' => 'Durasi audio',
        'end_seconds' => 'Detik akhir segmen',
        'groq_key' => 'Groq API key',
        'index' => 'Nomor segmen',
        'language' => 'Bahasa audio',
        'meeting_attendees' => 'Peserta rapat',
        'meeting_context' => 'Konteks rapat',
        'meeting_date' => 'Tanggal rapat',
        'meeting_title' => 'Judul rapat',
        'name' => 'Nama berkas',
        'size_bytes' => 'Ukuran berkas',
        'start_seconds' => 'Detik awal segmen',
        'status' => 'Status',
        'total_chunks' => 'Jumlah segmen',
    ],
];
