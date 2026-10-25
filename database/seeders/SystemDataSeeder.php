<?php

namespace Database\Seeders;

use App\Enums\SettingKey;
use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * System data of the portal (progettazione-tecnica §5.6.1 A1): run in every environment, also in
 * production at the first deploy and at every release (docs/dev/ci.md §2).
 *
 * Idempotent: it inserts only what is missing and never changes a row, so the values changed by the
 * admin survive every run. No audit event: these are the initial data of the system, like the
 * migrations.
 *
 * Settings (D-191, PR-07); sports registry: discipline and competitions (F1-01, D-321). The
 * catalogue arrives with slice F2, in its own seeder called from here.
 */
class SystemDataSeeder extends Seeder
{
    public function run(): void
    {
        $created = 0;

        foreach (SettingKey::cases() as $key) {
            $setting = Setting::query()->firstOrCreate(
                ['key' => $key->value],
                ['value' => $key->defaultValue(), 'updated_by' => null],
            );

            if ($setting->wasRecentlyCreated) {
                $created++;
            }
        }

        $this->command?->line(sprintf(
            'System settings: %d created, %d already present (left unchanged).',
            $created,
            count(SettingKey::cases()) - $created,
        ));

        $this->call(SportsSystemDataSeeder::class);
    }
}
