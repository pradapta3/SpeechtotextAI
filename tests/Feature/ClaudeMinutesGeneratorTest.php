<?php

declare(strict_types=1);

namespace Tests\Feature;

use Anthropic\Client;
use Anthropic\RequestOptions;
use App\Exceptions\MinutesGenerationFailed;
use App\Models\Recording;
use App\Services\Minutes\ClaudeMinutesGenerator;
use App\Services\Minutes\MeetingMinutesPrompt;
use Tests\Support\FakeTransporter;
use Tests\TestCase;

class ClaudeMinutesGeneratorTest extends TestCase
{
    public function test_it_sends_the_configured_model_and_returns_the_markdown(): void
    {
        config()->set('notulensi.minutes.model', 'claude-opus-5');
        config()->set('notulensi.minutes.effort', 'medium');

        $transporter = FakeTransporter::respondingWith($this->message([
            ['type' => 'text', 'text' => "# Notulensi Rapat\n\n## Agenda\n- Anggaran"],
        ]));

        $minutes = $this->generator($transporter)->generate(
            $this->recording(),
            'Kita bahas anggaran kuartal ini.',
            'sk-ant-test',
        );

        $this->assertSame("# Notulensi Rapat\n\n## Agenda\n- Anggaran", $minutes);

        $body = $transporter->lastBody();
        $this->assertSame('claude-opus-5', $body['model']);
        $this->assertSame('medium', $body['output_config']['effort']);
        $this->assertSame('default', $body['fallbacks']);
        $this->assertStringContainsString('sekretaris rapat profesional', $body['system']);
        $this->assertStringContainsString('Kita bahas anggaran kuartal ini.', $body['messages'][0]['content']);
        $this->assertSame('sk-ant-test', $transporter->requests[0]->getHeaderLine('x-api-key'));
    }

    public function test_it_reports_a_refusal_instead_of_returning_empty_minutes(): void
    {
        $transporter = FakeTransporter::respondingWith($this->message(
            content: [],
            overrides: [
                'stop_reason' => 'refusal',
                'stop_details' => ['type' => 'refusal', 'category' => 'cyber'],
            ],
        ));

        $this->expectException(MinutesGenerationFailed::class);
        $this->expectExceptionMessage('menolak');

        $this->generator($transporter)->generate($this->recording(), 'Transkrip panjang.', 'sk-ant-test');
    }

    public function test_it_reports_an_empty_response(): void
    {
        $transporter = FakeTransporter::respondingWith($this->message([]));

        $this->expectException(MinutesGenerationFailed::class);

        $this->generator($transporter)->generate($this->recording(), 'Transkrip panjang.', 'sk-ant-test');
    }

    private function generator(FakeTransporter $transporter): ClaudeMinutesGenerator
    {
        return new ClaudeMinutesGenerator(
            new MeetingMinutesPrompt,
            fn (string $apiKey): Client => new Client(
                apiKey: $apiKey,
                requestOptions: RequestOptions::with(maxRetries: 0, transporter: $transporter),
            ),
        );
    }

    private function recording(): Recording
    {
        return Recording::factory()->make([
            'name' => 'rapat.mp3',
            'meeting_title' => 'Rapat Anggaran',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $content
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function message(array $content, array $overrides = []): array
    {
        return array_merge([
            'id' => 'msg_test',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-opus-5',
            'content' => $content,
            'stop_reason' => 'end_turn',
            'stop_sequence' => null,
            'usage' => [
                'input_tokens' => 120,
                'output_tokens' => 400,
                'cache_creation_input_tokens' => 0,
                'cache_read_input_tokens' => 0,
            ],
        ], $overrides);
    }
}
