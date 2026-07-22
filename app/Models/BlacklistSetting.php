<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlacklistSetting extends Model
{
    protected $table = 'blacklist_settings';
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $fillable = [
        'InactivityValue',
        'InactivityUnit',
        'AutoBlacklistEnabled',
        'FirstWarningValue',
        'FirstWarningUnit',
        'SecondWarningValue',
        'SecondWarningUnit',
        'BlacklistAfterSecondWarningValue',
        'BlacklistAfterSecondWarningUnit',
        'BlacklistDurationValue',
        'BlacklistDurationUnit',
    ];

    protected $casts = [
        'AutoBlacklistEnabled' => 'boolean',
    ];

    const UNITS = [
        'seconds' => 1,
        'minutes' => 60,
        'hours' => 3600,
        'days' => 86400,
    ];

    /**
     * The settings form is single-row - there is only ever one admin-wide
     * configuration, not one per something else.
     */
    public static function current(): self
    {
        return self::firstOrCreate([], [
            'InactivityValue' => 7,
            'InactivityUnit' => 'days',
            'AutoBlacklistEnabled' => false,
        ]);
    }

    public static function toSeconds(?int $value, ?string $unit): int
    {
        return (int) $value * (self::UNITS[$unit] ?? 1);
    }

    /**
     * Render a second count back into the largest whole unit it divides into
     * evenly, e.g. 259200 -> "3 days", falling back to seconds otherwise.
     */
    public static function formatDuration(int $seconds): string
    {
        $units = ['day' => 86400, 'hour' => 3600, 'minute' => 60, 'second' => 1];

        foreach ($units as $name => $unitSeconds) {
            if ($seconds % $unitSeconds === 0) {
                $value = intdiv($seconds, $unitSeconds);

                return $value . ' ' . \Illuminate\Support\Str::plural($name, $value);
            }
        }

        return $seconds . ' ' . \Illuminate\Support\Str::plural('second', $seconds);
    }
}
