<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    public static function get(string $key, ?string $default = null): ?string
    {
        return Cache::rememberForever("app_setting:{$key}", function () use ($key, $default) {
            $setting = static::query()->find($key);

            return $setting?->value ?? $default;
        });
    }

    public static function set(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget("app_setting:{$key}");
    }

    public static function getBool(string $key, bool $default = true): bool
    {
        return static::get($key, $default ? '1' : '0') === '1';
    }

    public static function setBool(string $key, bool $value): void
    {
        static::set($key, $value ? '1' : '0');
    }
}
