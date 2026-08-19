<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class TranscriptionFailed extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 502,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message);
    }

    public static function missingApiKey(): self
    {
        return new self(
            'Groq API Key belum diatur. Isi di tab Pengaturan atau set GROQ_API_KEY di .env.',
            status: 422,
        );
    }

    public static function rateLimited(int $retryAfter): self
    {
        return new self(
            "Kena rate limit Groq. Menunggu {$retryAfter} detik lalu melanjutkan otomatis.",
            status: 429,
            retryAfter: $retryAfter,
        );
    }

    public static function chunkTooLarge(int $maxKilobytes): self
    {
        return new self(
            sprintf(
                'Segmen audio melebihi %d MB. Perkecil "Durasi Segmen" di Pengaturan lalu ulangi.',
                (int) round($maxKilobytes / 1024),
            ),
            status: 422,
        );
    }

    public static function upstream(int $status, string $body): self
    {
        return new self(
            sprintf('Groq menolak permintaan [%d]: %s', $status, self::summarize($body)),
            status: $status === 401 || $status === 403 ? 422 : 502,
        );
    }

    public static function unreachable(string $reason): self
    {
        return new self(
            'Tidak bisa menghubungi Groq: '.$reason,
            status: 504,
        );
    }

    public function render(Request $request): JsonResponse
    {
        $response = new JsonResponse([
            'message' => $this->getMessage(),
            'retry_after' => $this->retryAfter,
        ], $this->status);

        if ($this->retryAfter !== null) {
            $response->headers->set('Retry-After', (string) $this->retryAfter);
        }

        return $response;
    }

    private static function summarize(string $body): string
    {
        $decoded = json_decode($body, true);

        if (is_array($decoded) && isset($decoded['error']['message']) && is_string($decoded['error']['message'])) {
            return $decoded['error']['message'];
        }

        return str($body)->stripTags()->squish()->limit(300)->value();
    }
}
