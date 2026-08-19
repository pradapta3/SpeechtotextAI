<?php

declare(strict_types=1);

namespace App\Services\Minutes;

use App\Models\Recording;

/**
 * Menyusun prompt notulensi. Dipisah dari pemanggilan API supaya isi prompt
 * bisa diuji tanpa menyentuh jaringan.
 */
class MeetingMinutesPrompt
{
    public function system(): string
    {
        return <<<'PROMPT'
        Anda adalah sekretaris rapat profesional. Anda mengubah transkrip otomatis (hasil speech-to-text, sering
        salah eja dan penuh kata pengisi) menjadi notulensi rapat berbahasa Indonesia yang rapi.

        Keluarkan HANYA notulensi dalam format Markdown dengan struktur berikut:

        # Notulensi Rapat
        ## Informasi Rapat
        ## Agenda
        ## Jalannya Rapat
        ## Keputusan
        ## Tindak Lanjut
        ## Kesimpulan

        Aturan:
        - Gunakan bahasa Indonesia formal; buang kata pengisi dan pengulangan.
        - Pertahankan seluruh angka, tanggal, nama, dan keputusan apa adanya. Jangan mengarang fakta.
        - Bagian "Tindak Lanjut" berbentuk tabel Markdown dengan kolom: Tindakan | Penanggung Jawab | Tenggat.
          Tulis "-" jika tidak disebutkan dalam transkrip.
        - Tandai bagian yang tidak terdengar jelas dengan [tidak jelas], jangan ditebak.
        - Panjang notulensi mengikuti isi rapat. Jangan menambahkan bagian pembuka, penutup, catatan untuk
          pengguna, atau ringkasan tambahan di luar struktur di atas.
        PROMPT;
    }

    public function user(Recording $recording, string $transcript): string
    {
        $context = collect([
            'Judul rapat' => $recording->meeting_title,
            'Tanggal' => $recording->meeting_date?->translatedFormat('d F Y'),
            'Peserta' => $recording->meeting_attendees,
            'Konteks tambahan' => $recording->meeting_context,
            'Berkas sumber' => $recording->name,
        ])
            ->filter(fn (?string $value): bool => filled($value))
            ->map(fn (string $value, string $label): string => "{$label}: {$value}")
            ->implode("\n");

        return collect([
            $context === '' ? null : "Metadata rapat:\n{$context}",
            "Transkrip rapat:\n<transkrip>\n{$transcript}\n</transkrip>",
            'Susun notulensi rapat dari transkrip di atas.',
        ])->filter()->implode("\n\n");
    }
}
