<?php

use App\Domain\Settings\Actions\UpdateSettings;
use App\Domain\Settings\Services\Settings;
use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\Setting;
use App\Models\User;
use App\Support\Navigation\PortalPages;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Settings page (ux-architettura "Navigazione per ruolo": admin only; D-191, D-286; PR-07). The
| permissions of both routes are in the route × role matrix (RouteMatrixTest).
*/

/**
 * Editable values of the form, overridden by the test.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function settingsForm(array $overrides = []): array
{
    return array_merge([
        'hospitality_max_seats' => 6,
        'forced_reason_min_length' => 10,
        'audit_ip_retention_days' => 1826,
    ], $overrides);
}

it('D-191: the admin sees every setting, the fixed ones as read-only with the reason', function () {
    $admin = User::factory()->admin()->create(['first_name' => 'Anna', 'last_name' => 'Bianchi']);
    $this->seed(SystemDataSeeder::class);
    app(UpdateSettings::class)->handle(settingsForm(['hospitality_max_seats' => 4]), $admin);

    $this->actingAs($admin)
        ->get(PortalPages::url('settings'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Index')
            ->where('updateUrl', route('settings.update'))
            ->where('layout.title', 'Impostazioni')
            ->has('groups', 2)
            ->where('groups.0.title', 'Magazzino e configuratore')
            ->where('groups.0.items.0.key', 'availability_method')
            ->where('groups.0.items.0.editable', false)
            ->where('groups.0.items.0.display', 'Stagionale')
            ->where('groups.0.items.0.fixedNote', 'Il calcolo «per partita» non è ancora disponibile: il valore non si modifica.')
            ->where('groups.0.items.1.key', 'led_step_seconds')
            ->where('groups.0.items.1.editable', false)
            ->where('groups.0.items.1.display', '30 secondi')
            ->where('groups.0.items.2.key', 'hospitality_max_seats')
            ->where('groups.0.items.2.editable', true)
            ->where('groups.0.items.2.value', 4)
            ->where('groups.0.items.2.min', 1)
            ->where('groups.0.items.2.max', 6)
            ->where('groups.0.items.2.unit', 'posti')
            ->where('groups.0.items.2.isDefault', false)
            ->where('groups.0.items.2.lastChange', fn (string $text): bool => str_contains($text, 'da Anna Bianchi'))
            ->where('groups.0.items.3.key', 'forced_reason_min_length')
            ->where('groups.0.items.3.value', 10)
            ->where('groups.0.items.3.lastChange', null)
            ->where('groups.1.title', 'Audit')
            ->where('groups.1.items.0.key', 'audit_ip_retention_days')
            ->where('groups.1.items.0.value', 1826)
            ->where('groups.1.items.0.max', 1826));
});

it('D-191: without the seeder the page shows the defaults, marked as such', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(PortalPages::url('settings'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('groups.0.items.2.value', 6)
            ->where('groups.0.items.2.isDefault', true)
            ->where('groups.1.items.0.isDefault', true));
});

it('D-127: saving writes one audit event per changed key, with the value before and after', function () {
    $admin = User::factory()->admin()->create();
    $this->seed(SystemDataSeeder::class);

    $this->actingAs($admin)
        ->put(route('settings.update'), settingsForm(['hospitality_max_seats' => '4', 'audit_ip_retention_days' => '365']))
        ->assertRedirect()
        ->assertSessionHas('toast.message', 'Impostazioni salvate.');

    expect(app(Settings::class)->hospitalityMaxSeats())->toBe(4)
        ->and(app(Settings::class)->auditIpRetentionDays())->toBe(365)
        ->and(app(Settings::class)->forcedReasonMinLength())->toBe(10)
        ->and(Setting::query()->where('key', 'hospitality_max_seats')->value('updated_by'))->toBe($admin->id)
        ->and(Setting::query()->where('key', 'forced_reason_min_length')->value('updated_by'))->toBeNull();

    $events = auditEventsOf(AuditAction::SettingUpdated);
    expect($events)->toHaveCount(2);

    $seats = $events->firstWhere('object_label', 'Massimo posti hospitality per club (hospitality_max_seats)');
    expect($seats)->not->toBeNull()
        ->and($seats?->area)->toBe('settings')
        ->and($seats?->object_type)->toBe('setting')
        ->and($seats?->object_id)->toBe(Setting::query()->where('key', 'hospitality_max_seats')->value('id'))
        ->and($seats?->user_id)->toBe($admin->id)
        ->and($seats?->user_role)->toBe('admin')
        ->and($seats?->old_values)->toBe(['hospitality_max_seats' => 6])
        ->and($seats?->new_values)->toBe(['hospitality_max_seats' => 4])
        ->and($seats?->ip_address)->toBeNull();

    $ip = $events->firstWhere('object_label', "Conservazione dell'indirizzo IP degli accessi (audit_ip_retention_days)");
    expect($ip?->old_values)->toBe(['audit_ip_retention_days' => 1826])
        ->and($ip?->new_values)->toBe(['audit_ip_retention_days' => 365]);
});

it('D-127: saving the same values writes nothing and says so', function () {
    $admin = User::factory()->admin()->create();
    $this->seed(SystemDataSeeder::class);

    $this->actingAs($admin)
        ->put(route('settings.update'), settingsForm())
        ->assertRedirect()
        ->assertSessionHas('toast.message', 'Nessuna modifica da salvare.');

    expect(AuditEvent::query()->count())->toBe(0)
        ->and(Setting::query()->whereNotNull('updated_by')->exists())->toBeFalse();
});

it('D-191: a missing row is created at the first change, with the default as value before', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('settings.update'), settingsForm(['forced_reason_min_length' => 20]))
        ->assertSessionHas('toast.message', 'Impostazioni salvate.');

    expect(Setting::query()->pluck('value', 'key')->all())->toBe(['forced_reason_min_length' => 20])
        ->and(soleAuditEvent(AuditAction::SettingUpdated)->old_values)->toBe(['forced_reason_min_length' => 10]);
});

it('D-147, M04 §9, D-211: refuses values outside the allowed ranges, saving nothing', function (array $values, string $field, string $message) {
    $admin = User::factory()->admin()->create();
    $this->seed(SystemDataSeeder::class);

    $this->actingAs($admin)
        ->from(PortalPages::url('settings'))
        ->put(route('settings.update'), settingsForm($values))
        ->assertRedirect(PortalPages::url('settings'))
        ->assertSessionHasErrors([$field => $message]);

    expect(AuditEvent::query()->count())->toBe(0)
        ->and(Setting::query()->whereNotNull('updated_by')->exists())->toBeFalse();
})->with([
    'hospitality over 6' => [['hospitality_max_seats' => 7], 'hospitality_max_seats', 'Il campo Massimo posti hospitality per club deve essere compreso tra 1 e 6.'],
    'hospitality 0' => [['hospitality_max_seats' => 0], 'hospitality_max_seats', 'Il campo Massimo posti hospitality per club deve essere compreso tra 1 e 6.'],
    'reason over 200' => [['forced_reason_min_length' => 201], 'forced_reason_min_length', 'Il campo Lunghezza minima della motivazione di forzatura deve essere compreso tra 1 e 200.'],
    'IP over 5 years' => [['audit_ip_retention_days' => 1827], 'audit_ip_retention_days', "Il campo Conservazione dell'indirizzo IP degli accessi deve essere compreso tra 1 e 1826."],
    'not a whole number' => [['hospitality_max_seats' => '4,5'], 'hospitality_max_seats', 'Il campo Massimo posti hospitality per club deve essere un numero intero.'],
    'empty' => [['audit_ip_retention_days' => ''], 'audit_ip_retention_days', "Il campo Conservazione dell'indirizzo IP degli accessi è obbligatorio."],
]);

it('D-062, D-063: the fixed settings cannot be changed, even when sent', function () {
    $admin = User::factory()->admin()->create();
    $this->seed(SystemDataSeeder::class);

    $this->actingAs($admin)
        ->put(route('settings.update'), settingsForm(['led_step_seconds' => 60, 'availability_method' => 'per_match']))
        ->assertSessionHas('toast.message', 'Nessuna modifica da salvare.');

    app(UpdateSettings::class)->handle(['led_step_seconds' => 60, 'availability_method' => 'per_match'], $admin);

    expect(Setting::query()->where('key', 'led_step_seconds')->value('value'))->toBe(30)
        ->and(Setting::query()->where('key', 'availability_method')->value('value'))->toBe('seasonal')
        ->and(AuditEvent::query()->count())->toBe(0);
});

it('D-284: an audit event that cannot be written cancels the change (fail-closed)', function () {
    $admin = User::factory()->admin()->create();
    $this->seed(SystemDataSeeder::class);
    AuditEvent::creating(fn () => throw new RuntimeException('audit non scrivibile'));

    expect(fn () => app(UpdateSettings::class)->handle(settingsForm(['hospitality_max_seats' => 3]), $admin))
        ->toThrow(RuntimeException::class);

    expect(DB::table('settings')->where('key', 'hospitality_max_seats')->value('value'))->toBe('6')
        ->and(app(Settings::class)->hospitalityMaxSeats())->toBe(6);
});

it('refuses a value outside the range also when the Action is called directly', function () {
    $admin = User::factory()->admin()->create();

    expect(fn () => app(UpdateSettings::class)->handle(['hospitality_max_seats' => 8], $admin))
        ->toThrow(InvalidArgumentException::class);
});
