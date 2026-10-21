<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Retrieve a setting's raw value, or return default if not found.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->find($key);
        return $setting !== null ? $setting->value : $default;
    }

    /**
     * Retrieve a boolean setting value.
     */
    public static function getBool(string $key, bool $default = false): bool
    {
        $val = static::get($key, null);
        if ($val === null) {
            return $default;
        }

        return filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Set or update a setting value.
     */
    public static function set(string $key, mixed $value): void
    {
        $storeValue = is_bool($value) ? ($value ? '1' : '0') : (string) $value;

        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $storeValue]
        );
    }
}
