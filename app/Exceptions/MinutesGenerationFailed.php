<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class MinutesGenerationFailed extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 502)
    {
        parent::__construct($message);
    }

    public static function missingApiKey(): self
    {
        return new self(
            'Anthropic API Key belum diatur. Isi di tab Pengaturan atau set ANTHROPIC_API_KEY di .env.',
            status: 422,
        );
    }

    public static function transcriptTooShort(int $minimum): self
    {
        return new self(
            "Transkrip terlalu pendek untuk dibuat notulensi (minimal {$minimum} karakter).",
            status: 422,
        );
    }

    public static function refused(?string $category): self
    {
        return new self(
            'Claude menolak memproses transkrip ini'.($category !== null ? " (kategori: {$category})" : '').'.',
            status: 422,
        );
    }

    public static function emptyResponse(): self
    {
        return new self('Claude tidak mengembalikan teks notulensi. Coba lagi.', status: 502);
    }

    public static function upstream(string $reason): self
    {
        return new self('Gagal menghubungi Anthropic: '.$reason, status: 502);
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse(['message' => $this->getMessage()], $this->status);
    }
}
