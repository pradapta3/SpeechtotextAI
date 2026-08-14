<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\RecordingStatus;
use App\Models\Recording;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_joins_segments_in_order_regardless_of_arrival(): void
    {
        $recording = Recording::factory()->create(['total_chunks' => 3]);

        $recording->storeSegment(2, 240, 360, 'Rapat ditutup.');
        $recording->storeSegment(0, 0, 120, 'Rapat dibuka.');
        $recording->storeSegment(1, 120, 240, 'Pembahasan anggaran.');

        $this->assertSame('Rapat dibuka. Pembahasan anggaran. Rapat ditutup.', $recording->transcript());
    }

    public function test_storing_the_same_index_twice_replaces_the_segment(): void
    {
        $recording = Recording::factory()->create(['total_chunks' => 1]);

        $recording->storeSegment(0, 0, 120, 'Versi pertama.');
        $recording->storeSegment(0, 0, 120, 'Versi kedua.');

        $this->assertCount(1, $recording->segments);
        $this->assertSame('Versi kedua.', $recording->transcript());
    }

    public function test_progress_never_reaches_a_hundred_before_completion(): void
    {
        $recording = Recording::factory()->create(['total_chunks' => 2]);
        $recording->storeSegment(0, 0, 120, 'Bagian pertama.');
        $recording->storeSegment(1, 120, 240, 'Bagian kedua.');

        $this->assertSame(99, $recording->progressPercent());

        $recording->status = RecordingStatus::Completed;

        $this->assertSame(100, $recording->progressPercent());
    }

    public function test_empty_segments_are_skipped(): void
    {
        $recording = Recording::factory()->create(['total_chunks' => 2]);
        $recording->storeSegment(0, 0, 120, 'Ada isinya.');
        $recording->storeSegment(1, 120, 240, '   ');

        $this->assertSame('Ada isinya.', $recording->transcript());
    }
}
