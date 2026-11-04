<?php

declare(strict_types=1);

namespace App\Services\Ai;

interface HttpTransport
{
    /**
     * @param array<string, string> $headers
     *
     * @return array{status: int, body: string}
     *
     * @throws AiException on network failure
     */
    public function postJson(string $url, array $headers, string $body, int $timeoutSeconds): array;
}
