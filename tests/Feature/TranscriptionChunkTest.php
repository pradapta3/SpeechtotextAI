<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Recording;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TranscriptionChunkTest extends TestCase
{
    use RefreshDatabase;

    private const OWNER = 'owner-key-1';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('notulensi.transcription.api_key', 'gsk_test');
        config()->set('notulensi.transcription.endpoint', 'https://api.groq.com/openai/v1/audio/transcriptions');

        // Sebuah panggilan yang lolos dari Http::fake harus menggagalkan tes,
        // bukan diam-diam menghubungi jaringan.
        Http::preventStrayRequests();
    }

    public function test_it_transcribes_a_chunk_and_stores_the_segment(): void
    {
        Http::fake(['api.groq.com/*' => Http::response('Selamat pagi semuanya.', 200)]);
        $recording = Recording::factory()->create(['owner_key' => self::OWNER, 'total_chunks' => 2]);

        $this->postChunk($recording, index: 0)
            ->assertOk()
            ->assertJsonPath('text', 'Selamat pagi semuanya.')
            ->assertJsonPath('recording.status', 'processing')
            ->assertJsonPath('recording.transcript', 'Selamat pagi semuanya.');

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer gsk_test'));
    }

    public function test_it_completes_the_recording_once_every_chunk_arrives(): void
    {
        Http::fake(['api.groq.com/*' => Http::sequence()
            ->push('Bagian pertama.')
            ->push('Bagian kedua.')]);

        $recording = Recording::factory()->create(['owner_key' => self::OWNER, 'total_chunks' => 2]);

        $this->postChunk($recording, index: 0)->assertOk();
        $this->postChunk($recording, index: 1, start: 120, end: 240)
            ->assertOk()
            ->assertJsonPath('recording.status', 'completed')
            ->assertJsonPath('recording.progress', 100)
            ->assertJsonPath('recording.transcript', 'Bagian pertama. Bagian kedua.');
    }

    public function test_resending_a_chunk_replaces_it_instead_of_duplicating(): void
    {
        Http::fake(['api.groq.com/*' => Http::sequence()
            ->push('Versi lama.')
            ->push('Versi baru.')]);

        $recording = Recording::factory()->create(['owner_key' => self::OWNER, 'total_chunks' => 2]);

        $this->postChunk($recording, index: 0)->assertOk();
        $this->postChunk($recording, index: 0)
            ->assertOk()
            ->assertJsonPath('recording.transcript', 'Versi baru.')
            ->assertJsonPath('recording.completed_chunks', 1);
    }

    public function test_it_passes_the_rate_limit_back_to_the_browser(): void
    {
        Http::fake(['api.groq.com/*' => Http::response('slow down', 429, ['Retry-After' => '30'])]);
        $recording = Recording::factory()->create(['owner_key' => self::OWNER]);

        $this->postChunk($recording, index: 0)
            ->assertStatus(429)
            ->assertJsonPath('retry_after', 32)
            ->assertHeader('Retry-After', '32');
    }

    public function test_it_reports_a_missing_api_key(): void
    {
        config()->set('notulensi.transcription.api_key', null);
        Http::fake();
        $recording = Recording::factory()->create(['owner_key' => self::OWNER]);

        $this->postChunk($recording, index: 0)
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'Groq API Key'));

        Http::assertNothingSent();
    }

    public function test_it_surfaces_an_upstream_failure(): void
    {
        Http::fake(['api.groq.com/*' => Http::response(
            json_encode(['error' => ['message' => 'model tidak tersedia']]),
            503,
        )]);
        $recording = Recording::factory()->create(['owner_key' => self::OWNER]);

        $this->postChunk($recording, index: 0)
            ->assertStatus(502)
            ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'model tidak tersedia'));
    }

    public function test_it_rejects_a_chunk_that_is_not_audio(): void
    {
        Http::fake();
        $recording = Recording::factory()->create(['owner_key' => self::OWNER]);

        $this->withSession(['notulensi.owner' => self::OWNER])
            ->postJson("/api/recordings/{$recording->id}/chunks", [
                'audio' => UploadedFile::fake()->create('catatan.txt', 4, 'text/plain'),
                'index' => 0,
                'start_seconds' => 0,
                'end_seconds' => 120,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('audio');
    }

    public function test_it_refuses_chunks_for_a_cancelled_recording(): void
    {
        Http::fake();
        $recording = Recording::factory()->create([
            'owner_key' => self::OWNER,
            'status' => 'cancelled',
        ]);

        $this->postChunk($recording, index: 0)->assertStatus(409);
        Http::assertNothingSent();
    }

    private function postChunk(Recording $recording, int $index, float $start = 0, float $end = 120)
    {
        return $this->withSession(['notulensi.owner' => self::OWNER])
            ->postJson("/api/recordings/{$recording->id}/chunks", [
                'audio' => UploadedFile::fake()->create("segment-{$index}.wav", 64, 'audio/wav'),
                'index' => $index,
                'start_seconds' => $start,
                'end_seconds' => $end,
            ]);
    }
}
