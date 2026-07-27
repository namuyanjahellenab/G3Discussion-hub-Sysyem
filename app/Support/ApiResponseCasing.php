<?php

namespace App\Support;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Adds a PascalCase-keyed duplicate alongside every existing snake_case key
 * in an API response, recursively - never removes or alters an existing
 * key. The desktop client (outside this repo) depends on today's snake_case
 * key names by exact string match; this lets the response gain a
 * PascalCase-consistent shape without any risk of breaking that client,
 * since every key it currently reads stays present and unchanged.
 */
class ApiResponseCasing
{
    public static function withPascalAliases(mixed $data): mixed
    {
        if ($data instanceof Arrayable) {
            $data = $data->toArray();
        }

        if (!is_array($data)) {
            return $data;
        }

        $result = [];

        foreach ($data as $key => $value) {
            $value = static::withPascalAliases($value);
            $result[$key] = $value;

            if (is_string($key) && str_contains($key, '_')) {
                // 'id' segments become 'ID' (not 'Id') to match this app's
                // own column convention (UserID, GroupID, MessageID, ...).
                $pascalKey = implode('', array_map(
                    fn ($word) => strtolower($word) === 'id' ? 'ID' : ucfirst($word),
                    explode('_', $key)
                ));

                // Never overwrite a key that's already explicitly present
                // (e.g. the response already sets both 'body' and a real
                // 'Body') - the original always wins.
                if (!array_key_exists($pascalKey, $data)) {
                    $result[$pascalKey] = $value;
                }
            }
        }

        return $result;
    }
}
