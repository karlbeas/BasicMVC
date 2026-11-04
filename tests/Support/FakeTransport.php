<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\Ai\AiException;
use App\Services\Ai\HttpTransport;

/** Scripted AI API: each call pops the next response (or throws when it is an AiException). */
final class FakeTransport implements HttpTransport
{
    /** @var list<array{url: string, body: array<string, mixed>}> */
    public array $requests = [];

    /** @param list<array{status: int, body: string}|AiException> $responses */
    public function __construct(public array $responses = []) {}

    /**
     * OpenAI-compatible success payload.
     *
     * @return array{status: int, body: string}
     */
    public static function ok(string $content, int $in = 1000, int $out = 2000): array
    {
        return ['status' => 200, 'body' => (string) json_encode([
            'choices' => [['message' => ['role' => 'assistant', 'content' => $content]]],
            'usage' => ['prompt_tokens' => $in, 'completion_tokens' => $out],
        ])];
    }

    public function postJson(string $url, array $headers, string $body, int $timeoutSeconds): array
    {
        $this->requests[] = ['url' => $url, 'body' => (array) json_decode($body, true)];
        $next = array_shift($this->responses);
        if ($next === null) {
            throw new AiException('network', 'No scripted response left');
        }
        if ($next instanceof AiException) {
            throw $next;
        }

        return $next;
    }
}
