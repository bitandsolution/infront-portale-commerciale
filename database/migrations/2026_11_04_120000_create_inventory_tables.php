<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory capacities and commitments (M04 F-01…F-03, RB-01, RB-08, RB-12; D-062, D-066, D-191,
 * D-192, D-334, progettazione-tecnica §5.3.3; slice F2-04).
 *
 * - Capacities per season and club with the flag "managed" (is_enabled, D-066, D-191): a disabled
 *   resource is not managed (not sellable), 0 with the flag on is sold out. LED pools in seconds per
 *   match (C6; the 30″ step is a rule of the code, read from settings), partnership tiers in
 *   contracts, hospitality variants in seats. No row = not configured yet.
 * - inventory_commitments: every column of the data model, WITHOUT the foreign keys towards the
 *   proposals, which do not exist yet; F5 adds them (D-334). Exactly one resource, coherent with the
 *   resource type; the contract period on every commitment (M04 §7, D-062).
 * - Commitments are immutable (D-192): two triggers refuse any change except the release (released_at,
 *   released_by, release_reason, set once) and any deletion, for every database user.
 * - Every foreign key is RESTRICT (C3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pool_capacities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('season_id')->constrained('seasons')->restrictOnDelete();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->foreignId('inventory_pool_id')->constrained('inventory_pools')->restrictOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->unsignedSmallInteger('seconds_per_match')->default(0);
            $table->timestamps();

            $table->unique(['season_id', 'club_id', 'inventory_pool_id'], 'pool_capacities_cell_unique');
            $table->index('club_id');
            $table->index('inventory_pool_id');
        });

        Schema::create('partnership_capacities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('season_id')->constrained('seasons')->restrictOnDelete();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->foreignId('partnership_tier_id')->constrained('partnership_tiers')->restrictOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->unsignedSmallInteger('quantity')->default(0);
            $table->timestamps();

            $table->unique(['season_id', 'club_id', 'partnership_tier_id'], 'partnership_capacities_cell_unique');
            $table->index('club_id');
            $table->index('partnership_tier_id');
        });

        Schema::create('hospitality_capacities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('season_id')->constrained('seasons')->restrictOnDelete();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->foreignId('hospitality_variant_id')->constrained('hospitality_variants')->restrictOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->unsignedSmallInteger('seats')->default(0);
            $table->timestamps();

            $table->unique(['season_id', 'club_id', 'hospitality_variant_id'], 'hospitality_capacities_cell_unique');
            $table->index('club_id');
            $table->index('hospitality_variant_id');
        });

        Schema::create('inventory_commitments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('season_id')->constrained('seasons')->restrictOnDelete();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            // D-334: references to the proposal, its version and its club row; F5 adds the foreign keys.
            $table->unsignedBigInteger('proposal_id');
            $table->unsignedBigInteger('proposal_version_id');
            $table->unsignedBigInteger('proposal_version_club_id');
            $table->string('resource_type', 32);
            $table->foreignId('inventory_pool_id')->nullable()->constrained('inventory_pools')->restrictOnDelete();
            $table->foreignId('partnership_tier_id')->nullable()->constrained('partnership_tiers')->restrictOnDelete();
            $table->foreignId('hospitality_variant_id')->nullable()->constrained('hospitality_variants')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_forced')->default(false);
            $table->text('forced_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('release_reason', 32)->nullable();
            $table->timestamps();

            $table->index(['season_id', 'club_id', 'released_at'], 'inventory_commitments_residual_index');
            $table->index('proposal_id');
            $table->index('proposal_version_id');
            $table->index('proposal_version_club_id');
        });

        DB::statement("ALTER TABLE inventory_commitments ADD CONSTRAINT inventory_commitments_resource_valid CHECK (
            (resource_type = 'pool' AND inventory_pool_id IS NOT NULL AND partnership_tier_id IS NULL AND hospitality_variant_id IS NULL)
            OR (resource_type = 'partnership' AND partnership_tier_id IS NOT NULL AND inventory_pool_id IS NULL AND hospitality_variant_id IS NULL)
            OR (resource_type = 'hospitality' AND hospitality_variant_id IS NOT NULL AND inventory_pool_id IS NULL AND partnership_tier_id IS NULL)
        )");
        DB::statement('ALTER TABLE inventory_commitments ADD CONSTRAINT inventory_commitments_quantity_positive CHECK (quantity > 0)');
        DB::statement('ALTER TABLE inventory_commitments ADD CONSTRAINT inventory_commitments_period_valid CHECK (ends_on >= starts_on)');
        DB::statement('ALTER TABLE inventory_commitments ADD CONSTRAINT inventory_commitments_forced_coherent CHECK (
            (is_forced = 0 AND forced_reason IS NULL)
            OR (is_forced = 1 AND forced_reason IS NOT NULL AND CHAR_LENGTH(TRIM(forced_reason)) > 0)
        )');
        DB::statement("ALTER TABLE inventory_commitments ADD CONSTRAINT inventory_commitments_release_coherent CHECK (
            (released_at IS NULL AND released_by IS NULL AND release_reason IS NULL)
            OR (released_at IS NOT NULL AND release_reason IS NOT NULL AND release_reason IN ('superseded', 'cancelled'))
        )");

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER inventory_commitments_before_update BEFORE UPDATE ON inventory_commitments FOR EACH ROW
            BEGIN
                IF OLD.released_at IS NOT NULL
                    OR NOT (NEW.id <=> OLD.id) OR NOT (NEW.season_id <=> OLD.season_id) OR NOT (NEW.club_id <=> OLD.club_id)
                    OR NOT (NEW.proposal_id <=> OLD.proposal_id) OR NOT (NEW.proposal_version_id <=> OLD.proposal_version_id)
                    OR NOT (NEW.proposal_version_club_id <=> OLD.proposal_version_club_id)
                    OR NOT (NEW.resource_type <=> OLD.resource_type) OR NOT (NEW.inventory_pool_id <=> OLD.inventory_pool_id)
                    OR NOT (NEW.partnership_tier_id <=> OLD.partnership_tier_id)
                    OR NOT (NEW.hospitality_variant_id <=> OLD.hospitality_variant_id)
                    OR NOT (NEW.quantity <=> OLD.quantity) OR NOT (NEW.starts_on <=> OLD.starts_on) OR NOT (NEW.ends_on <=> OLD.ends_on)
                    OR NOT (NEW.is_forced <=> OLD.is_forced) OR NOT (NEW.forced_reason <=> OLD.forced_reason)
                    OR NOT (NEW.created_by <=> OLD.created_by) OR NOT (NEW.created_at <=> OLD.created_at)
                THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'inventory_commitments rows are immutable: only an open commitment can be released, once';
                END IF;
            END
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER inventory_commitments_before_delete BEFORE DELETE ON inventory_commitments FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'inventory_commitments rows are never deleted: release them instead';
            END
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_commitments');
        Schema::dropIfExists('hospitality_capacities');
        Schema::dropIfExists('partnership_capacities');
        Schema::dropIfExists('pool_capacities');
    }
};
