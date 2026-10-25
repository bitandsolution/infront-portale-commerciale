<?php

namespace App\Models;

use App\Domain\Settings\Services\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A system setting (D-191; PR-07): one row per App\Enums\SettingKey.
 *
 * Read through App\Domain\Settings\Services\Settings, which applies defaults and allowed ranges;
 * written only by the system data seeder (missing keys) and by the UpdateSettings Action (admin,
 * with audit).
 *
 * @property int $id
 * @property string $key
 * @property int|string $value
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User|null $updater
 */
final class Setting extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        // The values read during a request or a job are kept by the Settings service: any write
        // drops them, so the next reading sees the new value.
        $forget = static function (): void {
            if (app()->resolved(Settings::class)) {
                app(Settings::class)->forget();
            }
        };

        self::saved($forget);
        self::deleted($forget);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
