<?php

namespace App\Domain\Content\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Files\Services\FileStorage;
use App\Enums\AuditAction;
use App\Enums\FilePurpose;
use App\Enums\LedPhotoKind;
use App\Models\Club;
use App\Models\LedMockupSetting;
use App\Models\StoredFile;
use App\Models\User;
use App\Support\Text;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Configuration of the simulation of the logo on the LED for a club, or the generic one of Infront
 * (club null), and a kind of photo (D-398; DM-06): admin only (LedMockupSettingPolicy).
 *
 * - The photo of the field is checked and re-encoded by FileStorage inside the transaction (a new
 *   photo is a new file, D-288); the first save of a configuration needs it.
 * - The areas of the LED: as many as the kind of photo wants (one for a row, two for Jumbo and
 *   Domination), four points each inside the photo (pixels, from the top left clockwise).
 * - Nothing changes: no write, no event. Otherwise one audit event led_mockup.saved with the changed
 *   fields.
 */
final class SaveLedMockupSetting
{
    public function __construct(
        private readonly AuditLog $audit,
        private readonly FileStorage $files,
    ) {}

    /**
     * @param  list<list<array{int, int}>>  $areas
     */
    public function handle(?Club $club, LedPhotoKind $kind, ?UploadedFile $photo, array $areas, int $logoHeightPercent, int $spacing, User $actor): LedMockupSetting
    {
        $name = $club !== null ? $club->name : Text::get('content.led_mockups.generic');

        return DB::transaction(function () use ($club, $name, $kind, $photo, $areas, $logoHeightPercent, $spacing, $actor): LedMockupSetting {
            $setting = LedMockupSetting::query()
                ->where('club_slot', $club !== null ? $club->id : 0)
                ->where('photo_kind', $kind->value)
                ->lockForUpdate()
                ->first();

            $file = $photo !== null
                ? $this->files->storeUpload($photo, FilePurpose::FieldPhoto, $actor)
                : ($setting !== null ? StoredFile::query()->find($setting->photo_file_id) : null);

            if ($file === null) {
                throw ValidationException::withMessages(['photo' => Text::get('content.led_mockups.validation.photo_required')]);
            }

            self::checkAreas($kind, $areas, (int) $file->width, (int) $file->height);

            $before = $setting !== null ? self::values($setting) : [];
            $setting ??= new LedMockupSetting(['club_id' => $club !== null ? $club->id : null, 'photo_kind' => $kind]);
            $setting->fill([
                'photo_file_id' => $file->id,
                'areas' => $areas,
                'logo_height_percent' => $logoHeightPercent,
                'spacing' => $spacing,
            ]);

            if ($setting->exists && ! $setting->isDirty()) {
                return $setting;
            }

            $setting->updated_by = $actor->id;
            $setting->save();

            [$old, $new] = AuditLog::changes($before, self::values($setting));

            $this->audit->record(
                AuditAction::LedMockupSaved,
                $actor,
                new AuditSubject('led_mockup', $setting->id, $name.' · '.$kind->label()),
                $old,
                ['club_name' => $name, 'photo_kind' => $kind->value, ...$new],
            );

            return $setting;
        });
    }

    /**
     * @param  list<list<array{int, int}>>  $areas
     */
    private static function checkAreas(LedPhotoKind $kind, array $areas, int $width, int $height): void
    {
        if (count($areas) !== $kind->areaCount()) {
            throw ValidationException::withMessages(['areas' => Text::get($kind->areaCount() === 1 ? 'content.led_mockups.validation.areas_count_one' : 'content.led_mockups.validation.areas_count_two')]);
        }

        foreach ($areas as $area) {
            foreach ($area as [$x, $y]) {
                if ($x < 0 || $y < 0 || $x > $width || $y > $height) {
                    throw ValidationException::withMessages(['areas' => Text::get('content.led_mockups.validation.areas_outside')]);
                }
            }
        }
    }

    /**
     * @return array<string, int|string>
     */
    private static function values(LedMockupSetting $setting): array
    {
        return [
            'photo_file_id' => $setting->photo_file_id,
            'areas' => (string) json_encode($setting->areas),
            'logo_height_percent' => $setting->logo_height_percent,
            'spacing' => $setting->spacing,
        ];
    }
}
