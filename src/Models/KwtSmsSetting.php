<?php

namespace KwtSMS\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property bool $is_encrypted
 */
class KwtSmsSetting extends Model
{
    protected $table = 'kwtsms_settings';

    protected $fillable = [
        'key',
        'value',
        'is_encrypted',
    ];

    public function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
        ];
    }

    /**
     * Get a setting value by key.
     *
     * Returns $default if the key does not exist or its value is null.
     * If the key is encrypted, logs a warning and returns $default: use getDecrypted() instead.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();

        if ($setting === null) {
            return $default;
        }

        if ($setting->is_encrypted) {
            Log::warning("KwtSMS: KwtSmsSetting::get() called on encrypted key '{$key}'. Use getDecrypted() instead.");

            return $default;
        }

        $value = $setting->value;

        if ($value === null) {
            return $default;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    /**
     * Set a setting value by key.
     *
     * Always stores as JSON so get() round-trips correctly for all types.
     * Logs a warning if the key was previously stored as encrypted.
     */
    public static function set(string $key, mixed $value): void
    {
        $existing = static::query()->where('key', $key)->first();

        if ($existing?->is_encrypted) {
            Log::warning("KwtSMS: KwtSmsSetting::set() is overwriting encrypted key '{$key}' with a plain value.");
        }

        $encoded = json_encode($value);

        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $encoded, 'is_encrypted' => false]
        );
    }

    /**
     * Set a setting value encrypted by key.
     */
    public static function setEncrypted(string $key, string $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => Crypt::encryptString($value), 'is_encrypted' => true]
        );
    }

    /**
     * Get a decrypted setting value by key.
     */
    public static function getDecrypted(string $key, ?string $default = null): ?string
    {
        $setting = static::query()->where('key', $key)->first();

        if ($setting === null || $setting->value === null) {
            return $default;
        }

        if (! $setting->is_encrypted) {
            return $setting->value;
        }

        return Crypt::decryptString($setting->value);
    }
}
