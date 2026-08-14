<?php

declare(strict_types=1);

namespace App\Services\Minutes;

use Anthropic\Beta\Messages\BetaMessage;
use Anthropic\Beta\Messages\BetaTextBlock;
use Anthropic\Client;
use Anthropic\Core\Exceptions\AnthropicException;
use App\Exceptions\MinutesGenerationFailed;
use App\Models\Recording;
use Closure;

class ClaudeMinutesGenerator implements MinutesGenerator
{
    /**
     * @param  Closure(string): Client  $clientFactory
     */
    public function __construct(
        private readonly MeetingMinutesPrompt $prompt,
        private readonly Closure $clientFactory,
    ) {}

    public function generate(Recording $recording, string $transcript, string $apiKey): string
    {
        $client = ($this->clientFactory)($apiKey);

        try {
            $message = $client->beta->messages->create(
                maxTokens: (int) config('notulensi.minutes.max_tokens'),
                messages: [['role' => 'user', 'content' => $this->prompt->user($recording, $transcript)]],
                model: (string) config('notulensi.minutes.model'),
                outputConfig: ['effort' => (string) config('notulensi.minutes.effort')],
                system: $this->prompt->system(),
                // Jika classifier Anthropic menolak permintaan, model cadangan
                // yang direkomendasikan akan menjalankannya di sisi server.
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
                requestOptions: ['timeout' => (float) config('notulensi.minutes.timeout')],
            );
        } catch (AnthropicException $exception) {
            throw MinutesGenerationFailed::upstream($exception->getMessage());
        }

        return $this->textFrom($message);
    }

    private function textFrom(BetaMessage $message): string
    {
        // stop_reason harus diperiksa sebelum membaca content: pada penolakan,
        // content bisa kosong atau hanya berisi sebagian jawaban.
        if ($message->stopReason === 'refusal') {
            throw MinutesGenerationFailed::refused($message->stopDetails?->category);
        }

        $text = trim(collect($message->content)
            ->filter(fn (mixed $block): bool => $block instanceof BetaTextBlock)
            ->map(fn (BetaTextBlock $block): string => $block->text)
            ->implode(''));

        if ($text === '') {
            throw MinutesGenerationFailed::emptyResponse();
        }

        return $text;
    }
}
