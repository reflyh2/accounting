<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSetting extends Model
{
    protected $fillable = [
        'user_global_id',
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_global_id', 'global_id');
    }

    /**
     * Get a setting value for a user
     */
    public static function getValue(string $userGlobalId, string $key, $default = null)
    {
        $setting = self::where('user_global_id', $userGlobalId)
            ->where('key', $key)
            ->first();

        return $setting ? $setting->value : $default;
    }

    /**
     * Set a setting value for a user
     */
    public static function setValue(string $userGlobalId, string $key, $value): self
    {
        return self::updateOrCreate(
            ['user_global_id' => $userGlobalId, 'key' => $key],
            ['value' => $value]
        );
    }

    /**
     * Get all settings for a user as key-value array
     */
    public static function getAllForUser(string $userGlobalId): array
    {
        return self::where('user_global_id', $userGlobalId)
            ->pluck('value', 'key')
            ->toArray();
    }
}
