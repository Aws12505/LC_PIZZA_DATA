<?php

namespace App\Services\DataEntry;

use App\Models\EnteredKey;
use Illuminate\Validation\ValidationException;

class ValueTypeService
{
    public function assertMatchesKeyType(EnteredKey $key, array $payload): void
    {
        $present = array_filter([
            'value_text' => array_key_exists('value_text', $payload) && !is_null($payload['value_text']),
            'value_number' => array_key_exists('value_number', $payload) && !is_null($payload['value_number']),
            'value_boolean' => array_key_exists('value_boolean', $payload) && !is_null($payload['value_boolean']),
            'value_json' => array_key_exists('value_json', $payload) && !is_null($payload['value_json']),
        ]);

        $hasValue = count($present) === 1;
        $hasNote = !empty($payload['note']);

        if (!$hasValue && !$hasNote) {
            throw ValidationException::withMessages([
                'value' => 'You must provide a value or a note.',
            ]);
        }

        if (!$hasValue) {
            return; // note-only allowed
        }

        $map = [
            'text' => 'value_text',
            'number' => 'value_number',
            'decimal' => 'value_number',
            'boolean' => 'value_boolean',
            'json' => 'value_json',
        ];

        $expected = $map[$key->data_type] ?? null;
        $actual = array_key_first($present);

        if ($expected !== $actual) {
            throw ValidationException::withMessages([
                $actual => "Invalid value field for this key type. Expected {$expected} for data_type={$key->data_type}.",
            ]);
        }
    }
}