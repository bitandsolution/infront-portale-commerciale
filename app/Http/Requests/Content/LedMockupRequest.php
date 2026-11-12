<?php

namespace App\Http\Requests\Content;

use App\Domain\Files\Rules\AcceptableFile;
use App\Enums\FilePurpose;
use App\Models\LedMockupSetting;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Configuration of the simulation of the logo on the LED (D-398; DM-06): the photo (only to replace
 * it; the first save needs it, SaveLedMockupSetting), the areas of the LED as four points each in
 * pixels of the photo, the height of the logo in % of the LED and the spacing in pixels. Admin only
 * (LedMockupSettingPolicy::update). Multipart request: the areas arrive as JSON text.
 */
class LedMockupRequest extends FormRequest
{
    public const MIN_HEIGHT = 10;

    public const MAX_HEIGHT = 100;

    public const MAX_SPACING = 2000;

    public function authorize(): bool
    {
        return $this->user()?->can('update', LedMockupSetting::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'photo' => ['nullable', 'file', new AcceptableFile(FilePurpose::FieldPhoto)],
            'areas' => ['required', 'string', 'json', 'max:2000'],
            'logo_height_percent' => ['required', 'integer', 'min:'.self::MIN_HEIGHT, 'max:'.self::MAX_HEIGHT],
            'spacing' => ['required', 'integer', 'min:0', 'max:'.self::MAX_SPACING],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'photo' => 'foto del campo',
            'areas' => 'area del LED',
            'logo_height_percent' => 'altezza del logo',
            'spacing' => 'spaziatura',
        ];
    }

    /**
     * The areas as integers: a list of areas of four [x, y] points; anything else is an empty list
     * (refused by SaveLedMockupSetting with the number of areas wanted).
     *
     * @return list<list<array{int, int}>>
     */
    public function areas(): array
    {
        $decoded = json_decode($this->string('areas')->toString(), true);
        $areas = [];

        foreach (is_array($decoded) ? $decoded : [] as $area) {
            if (! is_array($area) || count($area) !== 4) {
                return [];
            }

            $points = [];

            foreach (array_values($area) as $point) {
                if (! is_array($point) || ! is_numeric($point[0] ?? null) || ! is_numeric($point[1] ?? null)) {
                    return [];
                }

                $points[] = [(int) round((float) $point[0]), (int) round((float) $point[1])];
            }

            $areas[] = $points;
        }

        return $areas;
    }

    public function actor(): User
    {
        /** @var User $user */
        $user = $this->user();

        return $user;
    }
}
