<?php

use App\Domain\Settings\Actions\UpdateSettings;
use App\Domain\Settings\Services\Settings;
use App\Models\AuditEvent;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SystemDataSeeder;

/*
| System data seeder (progettazione-tecnica §5.6.1 A1; D-191, D-286; PR-07): initial values of the
| settings, run again at every release without losing the changes of the admin.
*/

/**
 * @return array<string, mixed>
 */
function settingsStoredValues(): array
{
    return Setting::query()->orderBy('key')->pluck('value', 'key')->all();
}

it('D-191: writes the initial value of every setting, by the system', function () {
    $this->seed(SystemDataSeeder::class);

    expect(settingsStoredValues())->toBe([
        'audit_ip_retention_days' => 1826,
        'availability_method' => 'seasonal',
        'forced_reason_min_length' => 10,
        'hospitality_max_seats' => 6,
        'led_step_seconds' => 30,
    ])
        ->and(Setting::query()->whereNotNull('updated_by')->exists())->toBeFalse()
        ->and(AuditEvent::query()->count())->toBe(0);
});

it('D-286: copies the IP retention of config/audit.php the first time it runs', function () {
    config(['audit.ip_retention_days' => 400]);

    $this->seed(SystemDataSeeder::class);

    expect(settingsStoredValues()['audit_ip_retention_days'])->toBe(400);
});

it('A1: a second run keeps the values changed by the admin and adds only the missing keys', function () {
    $admin = User::factory()->admin()->create();
    $this->seed(SystemDataSeeder::class);
    app(UpdateSettings::class)->handle(['hospitality_max_seats' => 4, 'audit_ip_retention_days' => 365], $admin);
    Setting::query()->where('key', 'led_step_seconds')->delete();

    $this->seed(SystemDataSeeder::class);

    expect(settingsStoredValues())->toBe([
        'audit_ip_retention_days' => 365,
        'availability_method' => 'seasonal',
        'forced_reason_min_length' => 10,
        'hospitality_max_seats' => 4,
        'led_step_seconds' => 30,
    ])
        ->and(Setting::query()->where('key', 'hospitality_max_seats')->value('updated_by'))->toBe($admin->id)
        ->and(app(Settings::class)->hospitalityMaxSeats())->toBe(4);
});

it('A1: DatabaseSeeder runs the system data in every environment, the test users only in local', function () {
    // Called directly: in production "db:seed" asks for a confirmation (--force in the deploy).
    app()->detectEnvironment(fn (): string => 'production');

    (new DatabaseSeeder)->setContainer(app())->__invoke();

    expect(Setting::query()->count())->toBe(5)
        ->and(User::query()->count())->toBe(0);
});
