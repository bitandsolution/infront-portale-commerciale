<?php

namespace App\Domain\Settings\Services;

use App\Enums\AvailabilityMethod;
use App\Enums\SettingKey;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * Reading of the system settings (D-191, D-286; PR-07), for every slice that needs them: LED step
 * (configurator, F4), maximum hospitality seats per club (F4), minimum length of the forcing reason
 * (approval, F5), availability method (F4, F5), IP retention of the audit (audit:prune).
 *
 * - A missing row returns the default of SettingKey: the portal works also before the system data
 *   seeder has run, and the tests need no seeding.
 * - A stored value outside the allowed range (written by hand in SQL) returns the default and logs
 *   a warning, without the value.
 * - All rows are read with one query and kept for the request or the job (scoped binding,
 *   AppServiceProvider); any write of a Setting drops them (App\Models\Setting::booted).
 *
 * Reads on the default connection: the MariaDB maintenance user of audit:prune has no privilege on
 * this table, and needs none (docs/dev/database-utenti.md).
 */
final class Settings
{
    /** @var array<string, mixed>|null stored values by key, null until the first reading */
    private ?array $stored = null;

    public function get(SettingKey $key): int|string
    {
        $stored = $this->stored();

        if (! array_key_exists($key->value, $stored)) {
            return $key->defaultValue();
        }

        $value = $stored[$key->value];

        if (! $key->accepts($value)) {
            Log::warning('Setting outside its allowed range: the default is used.', ['key' => $key->value]);

            return $key->defaultValue();
        }

        /** @var int|string $value accepted above */
        return $value;
    }

    /**
     * Whether the key has a valid row; false means the page shows the default of the code.
     */
    public function isStored(SettingKey $key): bool
    {
        $stored = $this->stored();

        return array_key_exists($key->value, $stored) && $key->accepts($stored[$key->value]);
    }

    public function availabilityMethod(): AvailabilityMethod
    {
        return AvailabilityMethod::from($this->string(SettingKey::AvailabilityMethod));
    }

    public function ledStepSeconds(): int
    {
        return $this->integer(SettingKey::LedStepSeconds);
    }

    public function hospitalityMaxSeats(): int
    {
        return $this->integer(SettingKey::HospitalityMaxSeats);
    }

    public function forcedReasonMinLength(): int
    {
        return $this->integer(SettingKey::ForcedReasonMinLength);
    }

    public function auditIpRetentionDays(): int
    {
        return $this->integer(SettingKey::AuditIpRetentionDays);
    }

    /**
     * Drops the values read so far: the next reading queries the table again.
     */
    public function forget(): void
    {
        $this->stored = null;
    }

    private function integer(SettingKey $key): int
    {
        $value = $this->get($key);

        if (! is_int($value)) {
            throw new LogicException("Setting {$key->value} is not an integer.");
        }

        return $value;
    }

    private function string(SettingKey $key): string
    {
        $value = $this->get($key);

        if (! is_string($value)) {
            throw new LogicException("Setting {$key->value} is not a string.");
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function stored(): array
    {
        if ($this->stored === null) {
            $this->stored = [];

            foreach (Setting::query()->get(['key', 'value']) as $setting) {
                $this->stored[$setting->key] = $setting->value;
            }
        }

        return $this->stored;
    }
}
