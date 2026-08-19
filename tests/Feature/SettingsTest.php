<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\ApiCredentials;
use App\Support\UploadLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_a_user_key_encrypted_in_the_session(): void
    {
        $chunkSeconds = app(UploadLimits::class)->maxChunkSeconds();

        $response = $this->putJson('/api/settings', [
            'groq_key' => 'gsk_rahasia_sekali',
            'language' => 'en',
            'chunk_seconds' => $chunkSeconds,
        ]);

        $response->assertOk()
            ->assertJsonPath('settings.providers.groq.user_key', true)
            ->assertJsonPath('settings.providers.groq.masked', '••••kali')
            ->assertJsonPath('settings.preferences.language', 'en')
            ->assertJsonPath('settings.preferences.chunk_seconds', $chunkSeconds);

        // Key tidak boleh tersimpan sebagai teks biasa maupun terkirim balik.
        $stored = session('notulensi.keys.groq');
        $this->assertIsString($stored);
        $this->assertStringNotContainsString('gsk_rahasia_sekali', $stored);
        $response->assertDontSee('gsk_rahasia_sekali');

        $this->assertSame('gsk_rahasia_sekali', app(ApiCredentials::class)->transcriptionKey());
    }

    public function test_an_empty_field_keeps_the_stored_key(): void
    {
        $this->putJson('/api/settings', ['groq_key' => 'gsk_pertama'])->assertOk();

        $this->putJson('/api/settings', ['groq_key' => '', 'language' => 'id'])
            ->assertOk()
            ->assertJsonPath('settings.providers.groq.user_key', true);
    }

    public function test_it_forgets_stored_keys(): void
    {
        $this->putJson('/api/settings', ['groq_key' => 'gsk_pertama'])->assertOk();

        $this->putJson('/api/settings', ['forget_keys' => true])
            ->assertOk()
            ->assertJsonPath('settings.providers.groq.user_key', false)
            ->assertJsonPath('settings.providers.groq.masked', null);
    }

    public function test_it_rejects_user_keys_when_the_deployment_disables_them(): void
    {
        config()->set('notulensi.allow_user_keys', false);

        $this->putJson('/api/settings', ['groq_key' => 'gsk_rahasia'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('groq_key');
    }

    public function test_it_rejects_an_out_of_range_chunk_duration(): void
    {
        $this->putJson('/api/settings', ['chunk_seconds' => 5])
            ->assertStatus(422)
            ->assertJsonValidationErrors('chunk_seconds');
    }

    public function test_it_rejects_a_segment_longer_than_the_upload_limit_allows(): void
    {
        $tooLong = app(UploadLimits::class)->maxChunkSeconds() + 15;

        $this->putJson('/api/settings', ['chunk_seconds' => $tooLong])
            ->assertStatus(422)
            ->assertJsonValidationErrors('chunk_seconds');
    }

    public function test_the_server_key_is_reported_without_being_exposed(): void
    {
        config()->set('notulensi.transcription.api_key', 'gsk_dari_env');

        $this->putJson('/api/settings', [])
            ->assertOk()
            ->assertJsonPath('settings.providers.groq.server_key', true)
            ->assertJsonPath('settings.providers.groq.user_key', false)
            ->assertDontSee('gsk_dari_env');
    }
}
