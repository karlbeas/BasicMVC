<?php

declare(strict_types=1);

namespace App\Services\Ai;

/** Outbound HTTPS with cURL (available on shared hosting; no exec, no daemon). */
final class CurlTransport implements HttpTransport
{
    public function postJson(string $url, array $headers, string $body, int $timeoutSeconds): array
    {
        if (!function_exists('curl_init')) {
            throw new AiException('curl_missing', 'PHP cURL extension is required');
        }
        $lines = ['Content-Type: application/json', 'Accept: application/json'];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . str_replace(["\r", "\n"], '', $value);
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);
        if ($response === false || !is_string($response)) {
            throw new AiException($errno === CURLE_OPERATION_TIMEDOUT ? 'timeout' : 'network', 'AI API ' . ($errno === CURLE_OPERATION_TIMEDOUT ? 'timeout after ' . $timeoutSeconds . ' s' : 'unreachable: ' . $error));
        }

        return ['status' => $status, 'body' => $response];
    }
}
