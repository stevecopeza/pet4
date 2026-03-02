<?php

namespace Pet\Domain\Configuration\Service;

use InvalidArgumentException;

class SchemaValidator
{
    private const ALLOWED_TYPES = [
        'text',
        'textarea',
        'number',
        'boolean',
        'date',
        'datetime',
        'select',
        'multiselect',
        'email',
        'url',
    ];

    public function validate(array $schema): void
    {
        if (!isset($schema['fields']) || !is_array($schema['fields'])) {
            throw new InvalidArgumentException("Schema must contain a 'fields' array.");
        }

        $keys = [];

        foreach ($schema['fields'] as $index => $field) {
            $this->validateFieldStructure($field, $index);
            $this->validateKey($field['key'], $keys, $index);
            $this->validateType($field, $index);
            
            $keys[] = $field['key'];
        }
    }

    public function validateData(array $data, array $schema): array
    {
        if (!isset($schema['fields']) || !is_array($schema['fields'])) {
            return ['Invalid schema definition'];
        }

        $errors = [];
        $schemaFields = [];
        
        // Index schema fields by key for easy lookup
        foreach ($schema['fields'] as $field) {
            $schemaFields[$field['key']] = $field;
            
            // Check required fields
            if (!empty($field['required']) && $field['required'] === true) {
                if (!array_key_exists($field['key'], $data) || $data[$field['key']] === '' || $data[$field['key']] === null) {
                    $errors[] = "Field '{$field['label']}' is required";
                }
            }
        }

        // Validate provided data types/constraints
        foreach ($data as $key => $value) {
            if (!isset($schemaFields[$key])) {
                // Allow or disallow extra fields? For now, let's ignore extra fields or strict?
                // Usually we ignore extra fields or strip them.
                continue; 
            }

            $field = $schemaFields[$key];
            
            if ($value === null || $value === '') {
                continue; // Already checked required above
            }

            // Basic Type Validation
            switch ($field['type']) {
                case 'number':
                    if (!is_numeric($value)) {
                        $errors[] = "Field '{$field['label']}' must be a number";
                    }
                    break;
                case 'email':
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = "Field '{$field['label']}' must be a valid email";
                    }
                    break;
                case 'url':
                    if (!filter_var($value, FILTER_VALIDATE_URL)) {
                        $errors[] = "Field '{$field['label']}' must be a valid URL";
                    }
                    break;
                case 'select':
                    if (!in_array($value, $field['options'] ?? [])) {
                        $errors[] = "Field '{$field['label']}' has an invalid value";
                    }
                    break;
                case 'multiselect':
                    if (!is_array($value)) {
                         $errors[] = "Field '{$field['label']}' must be an array";
                    } else {
                        foreach ($value as $item) {
                            if (!in_array($item, $field['options'] ?? [])) {
                                $errors[] = "Field '{$field['label']}' contains an invalid value: $item";
                            }
                        }
                    }
                    break;
            }
        }

        return $errors;
    }

    private function validateFieldStructure(mixed $field, int $index): void
    {
        if (!is_array($field)) {
            throw new InvalidArgumentException("Field at index $index must be an array.");
        }

        $requiredKeys = ['key', 'label', 'type', 'required'];
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $field)) {
                throw new InvalidArgumentException("Field at index $index is missing required key: '$key'.");
            }
        }
    }

    private function validateKey(string $key, array $existingKeys, int $index): void
    {
        if (!preg_match('/^[a-z0-9_]+$/', $key)) {
            throw new InvalidArgumentException("Field key '$key' at index $index is invalid. Must contain only lowercase letters, numbers, and underscores.");
        }

        if (in_array($key, $existingKeys, true)) {
            throw new InvalidArgumentException("Duplicate field key '$key' found at index $index.");
        }
    }

    private function validateType(array $field, int $index): void
    {
        $type = $field['type'];

        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException("Invalid field type '$type' at index $index. Allowed types: " . implode(', ', self::ALLOWED_TYPES));
        }

        if (in_array($type, ['select', 'multiselect'], true)) {
            if (empty($field['options']) || !is_array($field['options'])) {
                throw new InvalidArgumentException("Field of type '$type' at index $index must have a non-empty 'options' array.");
            }
        }
    }
}
