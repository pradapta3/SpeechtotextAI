<?php

declare(strict_types=1);

namespace Tests\Support;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Transport PSR-18 palsu untuk SDK Anthropic sehingga integrasi Messages API
 * bisa diuji tanpa jaringan.
 */
class FakeTransporter implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @param list<ResponseInterface> $responses */
    public function __construct(private array $responses = []) {}

    /** @param array<string, mixed> $payload */
    public static function respondingWith(array $payload, int $status = 200): self
    {
        return new self([
            new Response($status, ['Content-Type' => 'application/json'], json_encode($payload, JSON_THROW_ON_ERROR)),
        ]);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        return array_shift($this->responses)
            ?? throw new RuntimeException('Tidak ada respons palsu yang tersisa.');
    }

    /** @return array<string, mixed> */
    public function lastBody(): array
    {
        $request = end($this->requests) ?: throw new RuntimeException('Belum ada permintaan yang dikirim.');

        return json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }
}
