<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Identifier of a portal entity in another system (D-186): the old proposal system ("idoc") for the
 * clubs brought by F1-06 (D-318), later the accreditation system ("accrediti") reading the API of
 * release 1.1 (D-217). More than one external id may point to the same entity. Polymorphic, so no
 * foreign key: the code keeps it consistent.
 *
 * @property int $id
 * @property string $platform
 * @property string $entity_type
 * @property int $entity_id
 * @property string $external_id
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class ExternalReference extends Model
{
    /** Old proposal system (D-229, D-318). */
    public const PLATFORM_IDOC = 'idoc';

    /** Entity types of the references (stable names, as in the audit). */
    public const ENTITY_CLUB = 'club';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entity_id' => 'integer',
        ];
    }
}
