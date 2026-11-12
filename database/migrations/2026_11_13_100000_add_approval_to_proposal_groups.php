<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Approval of a group and choice of the client (D-395, D-396, D-408…D-410; DM-03b):
 *
 * - proposals.status accepts "not_chosen": the variants the client did not choose (D-396, D-410);
 * - proposal_events.type accepts "client_chosen";
 * - inventory_commitments.release_reason accepts "not_chosen": the commitments of a variant not chosen
 *   are released with this reason (D-410);
 * - the foreign keys of inventory_commitments towards the proposal, its version and its club row
 *   (D-334), RESTRICT (C3): a commitment always points to a frozen version, which is never deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE proposals DROP CONSTRAINT proposals_status_valid');
        DB::statement("ALTER TABLE proposals ADD CONSTRAINT proposals_status_valid CHECK (status IN ('draft', 'submitted', 'approved', 'rejected', 'cancelled', 'not_chosen'))");

        DB::statement('ALTER TABLE proposal_events DROP CONSTRAINT proposal_events_type_valid');
        DB::statement("ALTER TABLE proposal_events ADD CONSTRAINT proposal_events_type_valid CHECK (type IN (
            'created', 'submitted', 'withdrawn', 'edited_in_review', 'discount_set', 'approved', 'forced', 'rejected',
            'resubmitted', 'new_version', 'cancelled', 'duplicated', 'variant_added', 'client_chosen'
        ))");

        DB::statement('ALTER TABLE inventory_commitments DROP CONSTRAINT inventory_commitments_release_coherent');
        DB::statement("ALTER TABLE inventory_commitments ADD CONSTRAINT inventory_commitments_release_coherent CHECK (
            (released_at IS NULL AND released_by IS NULL AND release_reason IS NULL)
            OR (released_at IS NOT NULL AND release_reason IS NOT NULL AND release_reason IN ('superseded', 'cancelled', 'not_chosen'))
        )");

        Schema::table('inventory_commitments', function (Blueprint $table): void {
            $table->foreign('proposal_id')->references('id')->on('proposals')->restrictOnDelete();
            $table->foreign('proposal_version_id')->references('id')->on('proposal_versions')->restrictOnDelete();
            $table->foreign('proposal_version_club_id')->references('id')->on('proposal_version_clubs')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_commitments', function (Blueprint $table): void {
            $table->dropForeign(['proposal_id']);
            $table->dropForeign(['proposal_version_id']);
            $table->dropForeign(['proposal_version_club_id']);
        });

        DB::statement('ALTER TABLE inventory_commitments DROP CONSTRAINT inventory_commitments_release_coherent');
        DB::statement("ALTER TABLE inventory_commitments ADD CONSTRAINT inventory_commitments_release_coherent CHECK (
            (released_at IS NULL AND released_by IS NULL AND release_reason IS NULL)
            OR (released_at IS NOT NULL AND release_reason IS NOT NULL AND release_reason IN ('superseded', 'cancelled'))
        )");

        DB::statement('ALTER TABLE proposal_events DROP CONSTRAINT proposal_events_type_valid');
        DB::statement("ALTER TABLE proposal_events ADD CONSTRAINT proposal_events_type_valid CHECK (type IN (
            'created', 'submitted', 'withdrawn', 'edited_in_review', 'discount_set', 'approved', 'forced', 'rejected',
            'resubmitted', 'new_version', 'cancelled', 'duplicated', 'variant_added'
        ))");

        DB::statement('ALTER TABLE proposals DROP CONSTRAINT proposals_status_valid');
        DB::statement("ALTER TABLE proposals ADD CONSTRAINT proposals_status_valid CHECK (status IN ('draft', 'submitted', 'approved', 'rejected', 'cancelled'))");
    }
};
