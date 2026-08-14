<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Recording;
use App\Services\Minutes\MeetingMinutesPrompt;
use Tests\TestCase;

class MeetingMinutesPromptTest extends TestCase
{
    public function test_it_includes_meeting_metadata_when_available(): void
    {
        $recording = Recording::factory()->make([
            'name' => 'rapat-koordinasi.mp3',
            'meeting_title' => 'Rapat Koordinasi',
            'meeting_date' => '2026-08-14',
            'meeting_attendees' => 'Budi, Sari',
            'meeting_context' => 'Evaluasi kuartal II',
        ]);

        $prompt = (new MeetingMinutesPrompt)->user($recording, 'Transkrip contoh.');

        $this->assertStringContainsString('Judul rapat: Rapat Koordinasi', $prompt);
        $this->assertStringContainsString('Peserta: Budi, Sari', $prompt);
        $this->assertStringContainsString('Konteks tambahan: Evaluasi kuartal II', $prompt);
        $this->assertStringContainsString('Berkas sumber: rapat-koordinasi.mp3', $prompt);
        $this->assertStringContainsString('<transkrip>', $prompt);
        $this->assertStringContainsString('Transkrip contoh.', $prompt);
    }

    public function test_it_omits_empty_metadata_lines(): void
    {
        $recording = Recording::factory()->make([
            'name' => 'rapat.mp3',
            'meeting_title' => null,
            'meeting_date' => null,
            'meeting_attendees' => null,
            'meeting_context' => null,
        ]);

        $prompt = (new MeetingMinutesPrompt)->user($recording, 'Transkrip contoh.');

        $this->assertStringNotContainsString('Judul rapat:', $prompt);
        $this->assertStringNotContainsString('Peserta:', $prompt);
        $this->assertStringContainsString('Transkrip contoh.', $prompt);
    }

    public function test_the_system_prompt_pins_the_output_structure(): void
    {
        $system = (new MeetingMinutesPrompt)->system();

        foreach (['## Agenda', '## Keputusan', '## Tindak Lanjut', '[tidak jelas]'] as $needle) {
            $this->assertStringContainsString($needle, $system);
        }
    }
}
