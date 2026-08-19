<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Recording;
use App\Support\UploadLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordingTest extends TestCase
{
    use RefreshDatabase;

    private const OWNER = 'owner-key-1';

    /** Durasi segmen yang pasti diterima pada konfigurasi PHP mesin ini. */
    private function chunkSeconds(): int
    {
        return min(60, app(UploadLimits::class)->maxChunkSeconds());
    }

    public function test_homepage_renders(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Unggah berkas audio', escape: false);
    }

    public function test_it_creates_a_recording_for_the_current_session(): void
    {
        $response = $this->withSession(['notulensi.owner' => self::OWNER])
            ->postJson('/api/recordings', [
                'name' => 'rapat.mp3',
                'size_bytes' => 1024,
                'duration_seconds' => 300,
                'language' => 'id',
                'chunk_seconds' => $this->chunkSeconds(),
                'total_chunks' => 3,
            ]);

        $response->assertCreated()
            ->assertJsonPath('recording.name', 'rapat.mp3')
            ->assertJsonPath('recording.status', 'pending');

        $this->assertDatabaseHas('recordings', ['name' => 'rapat.mp3', 'owner_key' => self::OWNER]);
    }

    public function test_it_rejects_an_unsupported_language(): void
    {
        $this->withSession(['notulensi.owner' => self::OWNER])
            ->postJson('/api/recordings', [
                'name' => 'rapat.mp3',
                'size_bytes' => 1024,
                'duration_seconds' => 300,
                'language' => 'klingon',
                'chunk_seconds' => $this->chunkSeconds(),
                'total_chunks' => 3,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('language');
    }

    public function test_it_enforces_the_stored_recording_limit(): void
    {
        config()->set('notulensi.limits.recordings_per_session', 1);
        Recording::factory()->create(['owner_key' => self::OWNER]);

        $this->withSession(['notulensi.owner' => self::OWNER])
            ->postJson('/api/recordings', [
                'name' => 'kedua.mp3',
                'size_bytes' => 1024,
                'duration_seconds' => 300,
                'language' => 'id',
                'chunk_seconds' => $this->chunkSeconds(),
                'total_chunks' => 3,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_it_only_lists_recordings_from_the_current_session(): void
    {
        Recording::factory()->create(['owner_key' => self::OWNER, 'name' => 'milik-saya.mp3']);
        Recording::factory()->create(['owner_key' => 'orang-lain', 'name' => 'rahasia.mp3']);

        $this->withSession(['notulensi.owner' => self::OWNER])
            ->getJson('/api/recordings')
            ->assertOk()
            ->assertJsonCount(1, 'recordings')
            ->assertJsonPath('recordings.0.name', 'milik-saya.mp3');
    }

    public function test_the_list_leaves_out_transcripts(): void
    {
        Recording::factory()
            ->transcribed('Isi rapat yang panjang sekali.')
            ->create(['owner_key' => self::OWNER]);

        $summary = $this->withSession(['notulensi.owner' => self::OWNER])
            ->getJson('/api/recordings')
            ->assertOk()
            ->assertJsonPath('recordings.0.has_transcript', true)
            ->json('recordings.0');

        // Daftar bisa berisi puluhan rekaman; transkrip diambil terpisah.
        $this->assertArrayNotHasKey('transcript', $summary);
        $this->assertArrayNotHasKey('segments', $summary);
        $this->assertArrayNotHasKey('minutes', $summary);
    }

    public function test_it_returns_the_detail_of_one_recording(): void
    {
        $recording = Recording::factory()
            ->transcribed('Rapat dibuka pukul sembilan.')
            ->create(['owner_key' => self::OWNER, 'language' => 'id']);

        $this->withSession(['notulensi.owner' => self::OWNER])
            ->getJson("/api/recordings/{$recording->id}")
            ->assertOk()
            ->assertJsonPath('recording.transcript', 'Rapat dibuka pukul sembilan.')
            ->assertJsonPath('recording.word_count', 4)
            ->assertJsonPath('recording.language_label', 'Indonesia')
            ->assertJsonPath('recording.segments.0.start', 0)
            ->assertJsonPath('recording.segments.0.end', 120);
    }

    public function test_it_hides_the_detail_of_another_session(): void
    {
        $recording = Recording::factory()->transcribed()->create(['owner_key' => 'orang-lain']);

        $this->withSession(['notulensi.owner' => self::OWNER])
            ->getJson("/api/recordings/{$recording->id}")
            ->assertNotFound();
    }

    public function test_it_hides_recordings_owned_by_another_session(): void
    {
        $recording = Recording::factory()->create(['owner_key' => 'orang-lain']);

        $this->withSession(['notulensi.owner' => self::OWNER])
            ->patchJson("/api/recordings/{$recording->id}", ['status' => 'cancelled'])
            ->assertNotFound();
    }

    public function test_it_marks_a_recording_as_cancelled(): void
    {
        $recording = Recording::factory()->create(['owner_key' => self::OWNER]);

        $this->withSession(['notulensi.owner' => self::OWNER])
            ->patchJson("/api/recordings/{$recording->id}", [
                'status' => 'cancelled',
                'error' => 'Dibatalkan pengguna.',
            ])
            ->assertOk()
            ->assertJsonPath('recording.status', 'cancelled');
    }

    public function test_it_refuses_a_status_the_browser_may_not_set(): void
    {
        $recording = Recording::factory()->create(['owner_key' => self::OWNER]);

        $this->withSession(['notulensi.owner' => self::OWNER])
            ->patchJson("/api/recordings/{$recording->id}", ['status' => 'completed'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_it_deletes_a_recording(): void
    {
        $recording = Recording::factory()->create(['owner_key' => self::OWNER]);

        $this->withSession(['notulensi.owner' => self::OWNER])
            ->deleteJson("/api/recordings/{$recording->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('recordings', ['id' => $recording->id]);
    }
}
