<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Merge of clients with their proposals (D-359, D-480, D-482; F5-07): proposal_events.type accepts
 * "client_merged", the history entry of every variant moved to the client that stays.
 */
return new class extends Migration
{
    private const TYPES = [
        'created', 'submitted', 'withdrawn', 'edited_in_review', 'discount_set', 'approved', 'forced', 'rejected',
        'resubmitted', 'new_version', 'cancelled', 'duplicated', 'variant_added', 'client_chosen',
    ];

    public function up(): void
    {
        $this->types([...self::TYPES, 'client_merged']);
    }

    public function down(): void
    {
        $this->types(self::TYPES);
    }

    /**
     * @param  list<string>  $types
     */
    private function types(array $types): void
    {
        $list = implode(', ', array_map(fn (string $type): string => "'{$type}'", $types));

        DB::statement('ALTER TABLE proposal_events DROP CONSTRAINT proposal_events_type_valid');
        DB::statement("ALTER TABLE proposal_events ADD CONSTRAINT proposal_events_type_valid CHECK (type IN ({$list}))");
    }
};
