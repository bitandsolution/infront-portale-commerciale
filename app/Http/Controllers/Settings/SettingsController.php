<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Settings\Actions\UpdateSettings;
use App\Domain\Settings\Services\Settings;
use App\Enums\AvailabilityMethod;
use App\Enums\SettingKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSettingsRequest;
use App\Models\Setting;
use App\Models\User;
use App\Support\Navigation\LayoutProps;
use App\Support\Text;
use App\Support\Toast;
use App\Support\Users\UserPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings page (ux-architettura "Navigazione per ruolo": admin only; D-191, D-286; PR-07): the
 * values in force, the editable ones in a form, the fixed ones as read-only text with the reason.
 * Both routes are behind the settings.manage Gate (routes/web.php, route × role matrix).
 */
class SettingsController extends Controller
{
    public function index(Request $request, Settings $settings): Response
    {
        /** @var array<string, Setting> $rows */
        $rows = Setting::query()->with('updater')->get()->keyBy('key')->all();

        $groups = [];

        foreach (SettingKey::cases() as $key) {
            $groups[$key->group()][] = $this->item($key, $settings, $rows[$key->value] ?? null);
        }

        return Inertia::render('Settings/Index', [
            'layout' => LayoutProps::for($this->actor($request), 'settings'),
            'groups' => array_map(
                fn (string $group, array $items): array => [
                    'key' => $group,
                    'title' => Text::get('settings.groups.'.$group),
                    'items' => $items,
                ],
                array_keys($groups),
                array_values($groups),
            ),
            'updateUrl' => route('settings.update'),
        ]);
    }

    public function update(UpdateSettingsRequest $request, UpdateSettings $updateSettings): RedirectResponse
    {
        $changed = $updateSettings->handle($request->values(), $this->actor($request));

        return back()->with('toast', Toast::success(Text::get(
            $changed === [] ? 'settings.toasts.unchanged' : 'settings.toasts.saved',
        )));
    }

    /**
     * @return array<string, mixed>
     */
    private function item(SettingKey $key, Settings $settings, ?Setting $row): array
    {
        $value = $settings->get($key);
        $range = $key->range();
        $updater = $row?->updater;

        return [
            'key' => $key->value,
            'label' => $key->label(),
            'help' => $key->help(),
            'editable' => $key->isEditable(),
            'fixedNote' => $key->fixedNote(),
            'value' => $value,
            'display' => is_string($value)
                ? (AvailabilityMethod::tryFrom($value)?->label() ?? $value)
                : $value.' '.$key->unit(),
            'unit' => $key->unit(),
            'min' => $range !== null ? $range[0] : null,
            'max' => $range !== null ? $range[1] : null,
            'isDefault' => ! $settings->isStored($key),
            'lastChange' => $row !== null && $updater !== null
                ? Text::get('settings.last_change', [
                    'date' => (string) UserPresenter::dateTime($row->updated_at),
                    'user' => $updater->fullName(),
                ])
                : null,
        ];
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
