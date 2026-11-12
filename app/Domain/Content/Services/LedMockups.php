<?php

namespace App\Domain\Content\Services;

use App\Enums\LedPhotoKind;
use App\Models\LedFormat;
use App\Models\LedMockupSetting;
use App\Support\Text;

/**
 * Which configuration of the simulation of the logo on the LED a club and a format use (D-398,
 * D-427; DM-06):
 * - the kind of photo comes from the pools of the format (1ª fila, 2ª fila, both rows: Jumbo and
 *   Domination);
 * - the configuration of the club for that kind, otherwise the generic one of Infront; null when
 *   neither exists (the page is skipped and who approves is warned, D-427);
 * - the Domination draws one large logo over both rows (style "domination"), every other format
 *   repeats the logo on each of its rows.
 *
 * Formats and configurations are read once per instance.
 */
final class LedMockups
{
    /** Code of the Domination (CatalogSystemDataSeeder::LED_FORMATS). */
    public const DOMINATION = 'DOMINATION';

    /** @var array<string, LedPhotoKind|null>|null */
    private ?array $kinds = null;

    /** @var array<string, LedMockupSetting|null> */
    private array $settings = [];

    public function kindForFormat(string $formatCode): ?LedPhotoKind
    {
        if ($this->kinds === null) {
            $this->kinds = [];

            foreach (LedFormat::query()->with('pools')->get() as $format) {
                /** @var list<string> $pools */
                $pools = $format->pools->pluck('code')->filter(fn (mixed $code): bool => is_string($code))->values()->all();
                $this->kinds[$format->code] = LedPhotoKind::forPools($pools);
            }
        }

        return $this->kinds[$formatCode] ?? null;
    }

    public static function styleForFormat(string $formatCode): string
    {
        return $formatCode === self::DOMINATION ? 'domination' : 'rows';
    }

    /**
     * The configuration for the club and the format: the club's, otherwise the generic one.
     */
    public function settingFor(int $clubId, string $formatCode): ?LedMockupSetting
    {
        $kind = $this->kindForFormat($formatCode);

        if ($kind === null) {
            return null;
        }

        $key = $clubId.'-'.$kind->value;

        if (! array_key_exists($key, $this->settings)) {
            $this->settings[$key] = LedMockupSetting::query()
                ->with('photo')
                ->where('photo_kind', $kind->value)
                ->whereRaw('(club_id = ? OR club_id IS NULL)', [$clubId])
                ->orderByRaw('club_id IS NULL')
                ->first();
        }

        return $this->settings[$key];
    }

    /**
     * D-427: the warning for a club and a format without configuration.
     */
    public function missingWarning(string $clubName, string $formatCode): string
    {
        $kind = $this->kindForFormat($formatCode);

        return Text::get('content.composition.missing_led_mockup', [
            'club' => $clubName,
            'photo' => $kind?->label() ?? $formatCode,
        ]);
    }
}
