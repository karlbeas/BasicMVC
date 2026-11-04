<?php

declare(strict_types=1);

namespace App\Services;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Validator;

/**
 * Validates a form against docs/schema/f2s-schema.v1.json (structure), then runs
 * semantic checks JSON Schema cannot express (unique names, references, labels…).
 * A form with errors must never be stored.
 */
final class FormValidator
{
    public const SCHEMA_ID = 'https://f2s.pro/schema/f2s-schema.v1.json';
    public const MAX_JSON_BYTES = 2_000_000;

    private Validator $validator;

    public function __construct(string $schemaPath)
    {
        $this->validator = new Validator();
        $this->validator->setMaxErrors(20);
        $this->validator->resolver()?->registerFile(self::SCHEMA_ID, $schemaPath);
    }

    public static function default(): self
    {
        return new self(dirname(__DIR__, 2) . '/docs/schema/f2s-schema.v1.json');
    }

    public function validateJson(string $json): FormValidationResult
    {
        if (strlen($json) > self::MAX_JSON_BYTES) {
            $result = new FormValidationResult();
            $result->addError('/', 'too_large', 'The form JSON exceeds ' . self::MAX_JSON_BYTES . ' bytes');

            return $result;
        }
        try {
            $data = json_decode($json, false, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $result = new FormValidationResult();
            $result->addError('/', 'invalid_json', $e->getMessage());

            return $result;
        }

        return $this->validateDecoded($data);
    }

    /** @param array<mixed> $form associative array (e.g. built in PHP) */
    public function validateArray(array $form): FormValidationResult
    {
        return $this->validateJson(json_encode($form, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function validateDecoded(mixed $data): FormValidationResult
    {
        $assoc = json_decode(json_encode($data, JSON_THROW_ON_ERROR), true, 64, JSON_THROW_ON_ERROR);
        $result = new FormValidationResult(is_array($assoc) ? $assoc : null);

        $structural = $this->validator->validate($data, self::SCHEMA_ID);
        $error = $structural->error();
        if ($error !== null) {
            $this->collectStructuralErrors($error, $result);

            return $result;
        }
        if (is_array($assoc)) {
            (new FormSemanticChecker($assoc, $result))->run();
        }

        return $result;
    }

    private function collectStructuralErrors(ValidationError $error, FormValidationResult $result): void
    {
        $formatter = new ErrorFormatter();
        foreach ($formatter->format($error, true) as $path => $messages) {
            foreach ((array) $messages as $message) {
                $result->addError($path === '' ? '/' : (string) $path, 'schema', (string) $message);
            }
        }
    }
}
