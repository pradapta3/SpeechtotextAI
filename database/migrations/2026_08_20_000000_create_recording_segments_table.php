<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Segmen semula disimpan sebagai satu kolom JSON di tabel recordings. Kolom itu
 * harus dibaca, diubah, lalu ditulis ulang setiap satu segmen masuk — pola yang
 * membuat dua unggahan bersamaan saling menunggu dan gagal dengan
 * "database is locked" (SQLite baru menerapkan mode transaksi IMMEDIATE pada
 * PHP 8.4+). Dengan satu baris per segmen dan indeks unik, tiap unggahan menulis
 * barisnya sendiri lewat satu pernyataan upsert yang atomik.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recording_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recording_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->float('start_seconds')->default(0);
            $table->float('end_seconds')->default(0);
            $table->text('text')->nullable();
            $table->timestamps();

            $table->unique(['recording_id', 'position']);
        });

        if (Schema::hasColumn('recordings', 'segments')) {
            $this->pindahkanSegmenLama();

            Schema::table('recordings', function (Blueprint $table) {
                $table->dropColumn('segments');
            });
        }
    }

    public function down(): void
    {
        Schema::table('recordings', function (Blueprint $table) {
            $table->json('segments')->nullable();
        });

        Schema::dropIfExists('recording_segments');
    }

    private function pindahkanSegmenLama(): void
    {
        $now = now();

        DB::table('recordings')->select('id', 'segments')->orderBy('id')->chunk(100, function ($recordings) use ($now) {
            $baris = [];

            foreach ($recordings as $recording) {
                foreach (json_decode((string) $recording->segments, true) ?: [] as $segment) {
                    $baris[] = [
                        'recording_id' => $recording->id,
                        'position' => (int) ($segment['index'] ?? 0),
                        'start_seconds' => (float) ($segment['start'] ?? 0),
                        'end_seconds' => (float) ($segment['end'] ?? 0),
                        'text' => $segment['text'] ?? '',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if ($baris !== []) {
                DB::table('recording_segments')->insert($baris);
            }
        });
    }
};
