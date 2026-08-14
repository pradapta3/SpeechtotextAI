<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Recording;
use App\Services\Minutes\MinutesGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MinutesTest extends TestCase
{
    use RefreshDatabase;

    private const OWNER = 'owner-key-1';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('notulensi.minutes.api_key', 'sk-ant-test');
    }

    public function test_it_stores_the_minutes_and_returns_rendered_html(): void
    {
        $this->fakeGenerator("# Notulensi Rapat\n\n## Agenda\n\n- Anggaran kuartal III");

        $recording = Recording::factory()
            ->transcribed(str_repeat('Kita membahas anggaran kuartal ketiga. ', 10))
            ->create(['owner_key' => self::OWNER]);

        $response = $this->withSession(['notulensi.owner' => self::OWNER])
            ->postJson("/api/recordings/{$recording->id}/minutes", [
                'meeting_title' => 'Rapat Anggaran',
                'meeting_date' => '2026-08-14',
            ]);

        $response->assertOk()
            ->assertJsonPath('recording.meeting.title', 'Rapat Anggaran')
            ->assertJsonPath('minutes_html', fn (string $html): bool => str_contains($html, '<h1>Notulensi Rapat</h1>')
                && str_contains($html, '<li>Anggaran kuartal III</li>'));

        $this->assertStringContainsString('# Notulensi Rapat', (string) $recording->fresh()->minutes);
    }

    public function test_it_strips_raw_html_from_the_model_output(): void
    {
        $this->fakeGenerator("# Notulensi\n\n<script>alert('x')</script>\n\nIsi rapat.");

        $recording = Recording::factory()
            ->transcribed(str_repeat('Rapat berjalan lancar. ', 20))
            ->create(['owner_key' => self::OWNER]);

        $this->withSession(['notulensi.owner' => self::OWNER])
            ->postJson("/api/recordings/{$recording->id}/minutes")
            ->assertOk()
            ->assertJsonPath('minutes_html', fn (string $html): bool => ! str_contains($html, '<script>')
                && str_contains($html, '<h1>Notulensi</h1>'));
    }

    public function test_it_refuses_a_transcript_that_is_too_short(): void
    {
        $this->fakeGenerator('tidak dipakai');
        $recording = Recording::factory()->transcribed('Halo.')->create(['owner_key' => self::OWNER]);

        $this->withSession(['notulensi.owner' => self::OWNER])
            ->postJson("/api/recordings/{$recording->id}/minutes")
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'terlalu pendek'));
    }

    public function test_it_reports_a_missing_api_key(): void
    {
        config()->set('notulensi.minutes.api_key', null);
        $this->fakeGenerator('tidak dipakai');

        $recording = Recording::factory()
            ->transcribed(str_repeat('Rapat berjalan lancar. ', 20))
            ->create(['owner_key' => self::OWNER]);

        $this->withSession(['notulensi.owner' => self::OWNER])
            ->postJson("/api/recordings/{$recording->id}/minutes")
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'Anthropic API Key'));
    }

    private function fakeGenerator(string $markdown): void
    {
        $this->app->bind(MinutesGenerator::class, fn (): MinutesGenerator => new class($markdown) implements MinutesGenerator
        {
            public function __construct(private readonly string $markdown) {}

            public function generate(Recording $recording, string $transcript, string $apiKey): string
            {
                return $this->markdown;
            }
        });
    }
}
