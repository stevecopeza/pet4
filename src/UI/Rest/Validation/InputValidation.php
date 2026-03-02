<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Validation;

/**
 * Shared sanitization and validation callbacks for WP REST API route definitions.
 */
class InputValidation
{
    // ── Sanitizers ──

    public static function sanitizeString($value): string
    {
        return sanitize_text_field((string) $value);
    }

    public static function sanitizeTextarea($value): string
    {
        return sanitize_textarea_field((string) $value);
    }

    public static function sanitizeInt($value): int
    {
        return (int) $value;
    }

    public static function sanitizeBool($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function sanitizeDatetime($value): string
    {
        return sanitize_text_field((string) $value);
    }

    // ── Validators ──

    public static function validateRequiredString($value, $request, $param): bool|\WP_Error
    {
        if (!is_string($value) || trim($value) === '') {
            return new \WP_Error('rest_invalid_param', sprintf('%s is required and must be a non-empty string.', $param));
        }
        return true;
    }

    public static function validateOptionalString($value, $request, $param): bool|\WP_Error
    {
        if ($value !== null && !is_string($value)) {
            return new \WP_Error('rest_invalid_param', sprintf('%s must be a string.', $param));
        }
        return true;
    }

    public static function validatePositiveInt($value, $request, $param): bool|\WP_Error
    {
        if (!is_numeric($value) || (int) $value < 1) {
            return new \WP_Error('rest_invalid_param', sprintf('%s must be a positive integer.', $param));
        }
        return true;
    }

    public static function validateOptionalPositiveInt($value, $request, $param): bool|\WP_Error
    {
        if ($value !== null && $value !== '' && (!is_numeric($value) || (int) $value < 1)) {
            return new \WP_Error('rest_invalid_param', sprintf('%s must be a positive integer when provided.', $param));
        }
        return true;
    }

    public static function validateBool($value, $request, $param): bool|\WP_Error
    {
        if ($value === null) {
            return new \WP_Error('rest_invalid_param', sprintf('%s is required.', $param));
        }
        return true;
    }

    public static function validateDatetime($value, $request, $param): bool|\WP_Error
    {
        if (!is_string($value) || trim($value) === '') {
            return new \WP_Error('rest_invalid_param', sprintf('%s is required and must be a datetime string.', $param));
        }
        try {
            new \DateTimeImmutable($value);
        } catch (\Exception $e) {
            return new \WP_Error('rest_invalid_param', sprintf('%s must be a valid datetime string.', $param));
        }
        return true;
    }

    public static function validatePriority($value, $request, $param): bool|\WP_Error
    {
        $allowed = ['low', 'medium', 'high', 'critical'];
        if (!in_array($value, $allowed, true)) {
            return new \WP_Error('rest_invalid_param', sprintf(
                '%s must be one of: %s',
                $param,
                implode(', ', $allowed)
            ));
        }
        return true;
    }

    public static function validateIntakeSource($value, $request, $param): bool|\WP_Error
    {
        $allowed = ['portal', 'email', 'phone', 'api', 'monitoring'];
        if (!in_array($value, $allowed, true)) {
            return new \WP_Error('rest_invalid_param', sprintf(
                '%s must be one of: %s',
                $param,
                implode(', ', $allowed)
            ));
        }
        return true;
    }

    // ── Arg definitions (reusable) ──

    public static function requiredStringArg(): array
    {
        return [
            'required' => true,
            'sanitize_callback' => [self::class, 'sanitizeString'],
            'validate_callback' => [self::class, 'validateRequiredString'],
        ];
    }

    public static function requiredTextareaArg(): array
    {
        return [
            'required' => true,
            'sanitize_callback' => [self::class, 'sanitizeTextarea'],
            'validate_callback' => [self::class, 'validateRequiredString'],
        ];
    }

    public static function requiredIntArg(): array
    {
        return [
            'required' => true,
            'sanitize_callback' => [self::class, 'sanitizeInt'],
            'validate_callback' => [self::class, 'validatePositiveInt'],
        ];
    }

    public static function optionalIntArg(): array
    {
        return [
            'required' => false,
            'sanitize_callback' => [self::class, 'sanitizeInt'],
            'validate_callback' => [self::class, 'validateOptionalPositiveInt'],
        ];
    }

    public static function requiredDatetimeArg(): array
    {
        return [
            'required' => true,
            'sanitize_callback' => [self::class, 'sanitizeDatetime'],
            'validate_callback' => [self::class, 'validateDatetime'],
        ];
    }

    public static function requiredBoolArg(): array
    {
        return [
            'required' => true,
            'sanitize_callback' => [self::class, 'sanitizeBool'],
            'validate_callback' => [self::class, 'validateBool'],
        ];
    }
}
