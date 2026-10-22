<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class SiteSettings
{
    public static function get(string $key, ?string $default = null): ?string
    {
        return DB::table('site_settings')->where('key', $key)->value('value') ?? $default;
    }

    public static function int(string $key, int $default): int
    {
        return (int) self::get($key, (string) $default);
    }

    /** @return list<string> */
    public static function enabledLanguages(): array
    {
        $enabled = array_values(array_filter(['en', 'bn', 'ar'], fn ($code) => self::get("lang_{$code}", '1') === '1'));

        return $enabled === [] ? ['en'] : $enabled;
    }
}
