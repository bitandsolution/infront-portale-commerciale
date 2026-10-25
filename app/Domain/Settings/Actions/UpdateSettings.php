<?php

namespace App\Domain\Settings\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Settings\Services\Settings;
use App\Enums\AuditAction;
use App\Enums\SettingKey;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Change of the system settings by the admin (Settings page; D-191, D-286; PR-07).
 *
 * - Only the editable keys are considered (SettingKey::isEditable): a fixed key passed by mistake
 *   is ignored, a value outside the allowed range is refused.
 * - A missing row is created; the "before" value of the audit is then the default of the code.
 * - Audit (D-127): one setting.updated event per changed key, with the value before and after,
 *   in the same transaction; no event and no write for the keys whose value does not change.
 *
 * Rows are locked in the transaction, so two admins saving at the same time leave a consistent
 * trail (each event has the real value before).
 */
final class UpdateSettings
{
    public function __construct(
        private readonly AuditLog $audit,
        private readonly Settings $settings,
    ) {}

    /**
     * @param  array<string, int|string>  $values  new values by key (SettingKey value)
     * @return list<SettingKey> the keys actually changed
     */
    public function handle(array $values, User $actor): array
    {
        /** @var list<SettingKey> $changed */
        $changed = DB::transaction(function () use ($values, $actor): array {
            $changed = [];

            foreach (SettingKey::editable() as $key) {
                if (! array_key_exists($key->value, $values)) {
                    continue;
                }

                $new = $values[$key->value];

                if (! $key->accepts($new)) {
                    throw new InvalidArgumentException("Value outside the allowed range for the setting {$key->value}.");
                }

                $setting = Setting::query()->where('key', $key->value)->lockForUpdate()->first();
                $old = $setting !== null ? $setting->value : $key->defaultValue();

                if ($old === $new) {
                    continue;
                }

                $setting ??= new Setting(['key' => $key->value]);
                $setting->fill(['value' => $new, 'updated_by' => $actor->id])->save();

                $this->audit->record(
                    AuditAction::SettingUpdated,
                    $actor,
                    AuditSubject::setting($key, $setting),
                    [$key->value => $old],
                    [$key->value => $new],
                );

                $changed[] = $key;
            }

            return $changed;
        });

        $this->settings->forget();

        return $changed;
    }
}
