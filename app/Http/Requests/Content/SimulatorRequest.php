<?php

namespace App\Http\Requests\Content;

use App\Domain\Content\Data\SimulatedClub;
use App\Enums\Ability;
use App\Enums\DocumentMode;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Choices of the simulator in the address of the page (M08 F-04; D-494, D-498): nothing is saved, so
 * the link can be shared and the "back" button works.
 *
 *     ?club[0][id]=12&club[0][pacchetto]=TOP&club[0][format]=JUMBO&club[0][hospitality]=1&modalita=summary
 *
 * The page is read only: a value that cannot be read is ignored instead of refused (an address typed
 * by hand never ends in an error), and the service checks clubs and codes against the season.
 */
class SimulatorRequest extends FormRequest
{
    /** Clubs read from the address at most: far above any proposal of the network. */
    public const MAX_CLUBS = 40;

    public function authorize(): bool
    {
        return $this->user()?->can(Ability::ContentManage->value) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * @return list<SimulatedClub>
     */
    public function choices(): array
    {
        $rows = $this->query('club');
        $choices = [];

        foreach (is_array($rows) ? array_slice($rows, 0, self::MAX_CLUBS) : [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = $row['id'] ?? null;

            if (! is_string($id) || ! ctype_digit($id) || isset($choices[(int) $id])) {
                continue;
            }

            $choices[(int) $id] = new SimulatedClub(
                clubId: (int) $id,
                tierCode: self::code($row['pacchetto'] ?? null),
                formatCode: self::code($row['format'] ?? null),
                hospitality: ($row['hospitality'] ?? null) === '1',
            );
        }

        return array_values($choices);
    }

    public function mode(): DocumentMode
    {
        $mode = $this->query('modalita');

        return DocumentMode::tryFrom(is_string($mode) ? $mode : '') ?? DocumentMode::Complete;
    }

    private static function code(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^[A-Z0-9_]{1,32}$/', $value) === 1 ? $value : null;
    }
}
