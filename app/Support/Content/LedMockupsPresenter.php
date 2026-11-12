<?php

namespace App\Support\Content;

use App\Enums\LedPhotoKind;
use App\Models\Club;
use App\Models\LedMockupSetting;
use App\Models\User;
use App\Support\Text;

/**
 * Props of "Contenuti › Simulazioni LED" (D-398; DM-06): the generic configuration of Infront and the
 * active Infront clubs, three kinds of photo each, and the editor of one configuration.
 */
final class LedMockupsPresenter
{
    public const GENERIC = 'infront';

    /**
     * @return list<array<string, mixed>>
     */
    public static function rows(): array
    {
        $settings = LedMockupSetting::query()->get()->keyBy(fn (LedMockupSetting $setting): string => $setting->club_slot.'-'.$setting->photo_kind->value);
        $rows = [self::row(self::GENERIC, Text::get('content.led_mockups.generic'), 0, $settings->all())];

        foreach (Club::query()->where('is_infront', true)->where('is_active', true)->orderBy('name')->get() as $club) {
            $rows[] = self::row((string) $club->id, $club->name, $club->id, $settings->all());
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public static function editor(?Club $club, LedPhotoKind $kind, ?LedMockupSetting $setting, User $viewer): array
    {
        $scope = $club !== null ? (string) $club->id : self::GENERIC;
        $photo = $setting?->photo;

        return [
            'name' => $club !== null ? $club->name : Text::get('content.led_mockups.generic'),
            'kind' => $kind->value,
            'kindLabel' => $kind->label(),
            'areaCount' => $kind->areaCount(),
            'photo' => $photo !== null ? [
                'url' => route('files.download', $photo),
                'width' => (int) $photo->width,
                'height' => (int) $photo->height,
            ] : null,
            'areas' => $setting !== null ? $setting->areas : [],
            'logoHeightPercent' => $setting !== null ? $setting->logo_height_percent : LedMockupSetting::DEFAULT_LOGO_HEIGHT_PERCENT,
            'spacing' => $setting !== null ? $setting->spacing : LedMockupSetting::DEFAULT_SPACING,
            'formats' => $kind === LedPhotoKind::BothRows
                ? [['value' => 'JUMBO', 'label' => 'Jumbo'], ['value' => 'DOMINATION', 'label' => 'Domination']]
                : [['value' => $kind === LedPhotoKind::Row1 ? 'FILA_1' : 'FILA_2', 'label' => $kind->label()]],
            'canEdit' => $viewer->can('update', LedMockupSetting::class),
            'saveUrl' => route('content.led-mockups.update', ['scope' => $scope, 'kind' => $kind->value]),
            'previewUrl' => $setting !== null ? route('content.led-mockups.preview', ['scope' => $scope, 'kind' => $kind->value]) : null,
            'indexUrl' => route('pages.content_led_mockups'),
        ];
    }

    /**
     * @param  array<string, LedMockupSetting>  $settings
     * @return array<string, mixed>
     */
    private static function row(string $scope, string $name, int $slot, array $settings): array
    {
        return [
            'scope' => $scope,
            'name' => $name,
            'generic' => $scope === self::GENERIC,
            'kinds' => array_map(function (LedPhotoKind $kind) use ($scope, $slot, $settings): array {
                $setting = $settings[$slot.'-'.$kind->value] ?? null;

                return [
                    'kind' => $kind->value,
                    'label' => $kind->label(),
                    'configured' => $setting !== null,
                    'thumbnailUrl' => $setting !== null ? route('files.thumbnail', $setting->photo_file_id) : null,
                    'editUrl' => route('content.led-mockups.edit', ['scope' => $scope, 'kind' => $kind->value]),
                ];
            }, LedPhotoKind::cases()),
        ];
    }
}
