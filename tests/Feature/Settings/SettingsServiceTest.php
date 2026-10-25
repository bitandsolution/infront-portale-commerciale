<?php

use App\Domain\Settings\Services\Settings;
use App\Enums\AvailabilityMethod;
use App\Enums\SettingKey;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/*
| Reading of the system settings (D-191, D-286; PR-07): defaults when a row is missing, allowed
| ranges, values kept for the request and dropped at every write.
*/

it('D-191: without rows returns the defaults of the code', function () {
    $settings = app(Settings::class);

    expect(Setting::query()->count())->toBe(0)
        ->and($settings->availabilityMethod())->toBe(AvailabilityMethod::Seasonal)
        ->and($settings->ledStepSeconds())->toBe(30)
        ->and($settings->hospitalityMaxSeats())->toBe(6)
        ->and($settings->forcedReasonMinLength())->toBe(10)
        ->and($settings->auditIpRetentionDays())->toBe(1826);

    foreach (SettingKey::cases() as $key) {
        expect($settings->isStored($key))->toBeFalse();
    }
});

it('D-286: without a row the IP retention is the value of config/audit.php, kept in range', function () {
    config(['audit.ip_retention_days' => 90]);
    expect(app(Settings::class)->auditIpRetentionDays())->toBe(90);

    config(['audit.ip_retention_days' => 5000]);
    expect(app(Settings::class)->auditIpRetentionDays())->toBe(1826);

    config(['audit.ip_retention_days' => 0]);
    expect(app(Settings::class)->auditIpRetentionDays())->toBe(1);
});

it('D-191: returns the stored values', function () {
    Setting::query()->create(['key' => 'hospitality_max_seats', 'value' => 4]);
    Setting::query()->create(['key' => 'forced_reason_min_length', 'value' => 25]);
    Setting::query()->create(['key' => 'audit_ip_retention_days', 'value' => 365]);
    $settings = app(Settings::class);

    expect($settings->hospitalityMaxSeats())->toBe(4)
        ->and($settings->forcedReasonMinLength())->toBe(25)
        ->and($settings->auditIpRetentionDays())->toBe(365)
        ->and($settings->isStored(SettingKey::HospitalityMaxSeats))->toBeTrue()
        ->and($settings->isStored(SettingKey::LedStepSeconds))->toBeFalse();
});

it('D-063, D-147: a value outside its range written by hand gives the default and a warning', function () {
    Log::spy();
    foreach ([
        'led_step_seconds' => '60',
        'hospitality_max_seats' => '7',
        'forced_reason_min_length' => '"dieci"',
        'audit_ip_retention_days' => '0',
        'availability_method' => '"monthly"',
    ] as $key => $value) {
        DB::table('settings')->insert(['key' => $key, 'value' => $value, 'created_at' => now(), 'updated_at' => now()]);
    }
    $settings = app(Settings::class);

    expect($settings->ledStepSeconds())->toBe(30)
        ->and($settings->hospitalityMaxSeats())->toBe(6)
        ->and($settings->forcedReasonMinLength())->toBe(10)
        ->and($settings->auditIpRetentionDays())->toBe(1826)
        ->and($settings->availabilityMethod())->toBe(AvailabilityMethod::Seasonal)
        ->and($settings->isStored(SettingKey::HospitalityMaxSeats))->toBeFalse();

    Log::shouldHaveReceived('warning')->with('Setting outside its allowed range: the default is used.', ['key' => 'hospitality_max_seats']);
});

it('D-062: "per partita" is a valid value of the availability method, though not editable', function () {
    Setting::query()->create(['key' => 'availability_method', 'value' => 'per_match']);

    expect(app(Settings::class)->availabilityMethod())->toBe(AvailabilityMethod::PerMatch)
        ->and(SettingKey::AvailabilityMethod->isEditable())->toBeFalse();
});

it('reads the table once per request and sees the writes made through the model', function () {
    $settings = app(Settings::class);
    expect($settings->hospitalityMaxSeats())->toBe(6);

    DB::enableQueryLog();
    $settings->hospitalityMaxSeats();
    $settings->ledStepSeconds();
    expect(DB::getQueryLog())->toBe([]);

    Setting::query()->create(['key' => 'hospitality_max_seats', 'value' => 3]);
    expect($settings->hospitalityMaxSeats())->toBe(3);

    Setting::query()->where('key', 'hospitality_max_seats')->firstOrFail()->update(['value' => 5]);
    expect($settings->hospitalityMaxSeats())->toBe(5);
});

it('is the same instance within a request (scoped binding)', function () {
    expect(app(Settings::class))->toBe(app(Settings::class));
});

it('D-191: every key has its allowed range, its label and the right editability', function () {
    expect(array_map(fn (SettingKey $key): string => $key->value, SettingKey::editable()))
        ->toBe(['hospitality_max_seats', 'forced_reason_min_length', 'audit_ip_retention_days']);

    $ranges = [];
    foreach (SettingKey::cases() as $key) {
        $ranges[$key->value] = $key->range();
        expect($key->label())->not->toBe($key->value)
            ->and($key->help())->not->toBe($key->value)
            ->and($key->accepts($key->defaultValue()))->toBeTrue();
    }

    expect($ranges)->toBe([
        'availability_method' => null,
        'led_step_seconds' => [30, 30],
        'hospitality_max_seats' => [1, 6],
        'forced_reason_min_length' => [1, 200],
        'audit_ip_retention_days' => [1, 1826],
    ]);

    expect(SettingKey::HospitalityMaxSeats->accepts(6))->toBeTrue()
        ->and(SettingKey::HospitalityMaxSeats->accepts(7))->toBeFalse()
        ->and(SettingKey::HospitalityMaxSeats->accepts(0))->toBeFalse()
        ->and(SettingKey::HospitalityMaxSeats->accepts('6'))->toBeFalse()
        ->and(SettingKey::ForcedReasonMinLength->accepts(200))->toBeTrue()
        ->and(SettingKey::ForcedReasonMinLength->accepts(201))->toBeFalse()
        ->and(SettingKey::AuditIpRetentionDays->accepts(1827))->toBeFalse()
        ->and(SettingKey::LedStepSeconds->accepts(60))->toBeFalse()
        ->and(SettingKey::AvailabilityMethod->accepts('seasonal'))->toBeTrue()
        ->and(SettingKey::AvailabilityMethod->accepts('monthly'))->toBeFalse();
});
