<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Recording;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aplikasi dipakai beberapa orang sekaligus dan tiap orang mengirim satu
 * segmen setiap beberapa detik, jadi penulisan yang tumpang tindih adalah
 * kondisi normal — bukan kasus tepi.
 */
class ConcurrentWriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_stale_instance_does_not_wipe_segments_written_meanwhile(): void
    {
        $recording = Recording::factory()->create(['total_chunks' => 2]);

        // Dua permintaan yang berjalan bersamaan sama-sama memuat rekaman ini
        // sebelum salah satunya menulis.
        $permintaanLain = Recording::query()->findOrFail($recording->id);

        $recording->storeSegment(0, 0, 15, 'Segmen pertama.');
        $permintaanLain->storeSegment(1, 15, 30, 'Segmen kedua.');

        // Tanpa pembacaan ulang di dalam transaksi, penulisan kedua akan
        // menimpa segmen pertama dengan data yang sudah usang.
        $tersimpan = $recording->fresh();
        $this->assertCount(2, $tersimpan->segments);
        $this->assertSame('Segmen pertama. Segmen kedua.', $tersimpan->transcript());
    }

    public function test_sqlite_is_configured_for_more_than_one_writer(): void
    {
        $sqlite = config('database.connections.sqlite');

        // Setelan bawaan Laravel (DEFERRED, tanpa WAL maupun busy_timeout)
        // membuat unggahan segmen yang bersamaan gagal dengan
        // "database is locked"; nilai di bawah ini yang mencegahnya.
        $this->assertSame('WAL', $sqlite['journal_mode']);
        $this->assertSame('IMMEDIATE', $sqlite['transaction_mode']);
        $this->assertGreaterThanOrEqual(1000, (int) $sqlite['busy_timeout']);
    }
}
