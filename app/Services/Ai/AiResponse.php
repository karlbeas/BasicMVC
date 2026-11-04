<?php

declare(strict_types=1);

namespace App\Services\Ai;

final class AiResponse
{
    public function __construct(
        public readonly string $content,
        public readonly string $model,
        public readonly int $promptTokens,
        public readonly int $completionTokens,
        public readonly int $costMicroUsd,
    ) {}

    /**
     * First JSON object found in the answer (models sometimes wrap it in ``` fences or prose).
     *
     * @return array<string, mixed>|null
     */
    public function json(): ?array
    {
        return self::extractJson($this->content);
    }

    /** @return array<string, mixed>|null */
    public static function extractJson(string $text): ?array
    {
        $text = trim($text);
        if (preg_match('/```(?:json)?\s*(.*?)```/s', $text, $m)) {
            $text = trim($m[1]);
        }
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end < $start) {
            return null;
        }
        try {
            $data = json_decode(substr($text, $start, $end - $start + 1), true, 128, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($data) && !array_is_list($data) ? $data : null;
    }
}
