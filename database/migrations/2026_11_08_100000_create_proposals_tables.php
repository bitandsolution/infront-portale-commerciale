<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Proposals: header, versions, club rows, hospitality seats and history (M06, M07; D-095, D-096,
 * D-198, D-199, D-371, D-376; progettazione-tecnica §5.3.4; slice F4-01).
 *
 * - Every column of the data model is created now; the columns of the life cycle (submission,
 *   decision, cancellation, review lock, discount, freezing, forcing) stay null until F5 (D-376,
 *   like D-334). The status has no "historical": nothing is migrated (D-227).
 * - proposals.number: progressive, from the MariaDB sequence proposal_numbers, which starts from 204
 *   because the last number of the old system is 203 (D-371). Never reused: a deleted draft leaves a
 *   gap. migrate:fresh does not drop sequences (Laravel lists only base tables), hence CREATE OR
 *   REPLACE.
 * - proposal_versions: the working version has a null number (one per proposal: generated column
 *   working_slot + UNIQUE), the frozen versions are numbered 1, 2… and immutable (D-198).
 * - Triggers (second line of defence after the models, §5.3.4): a frozen version, its club rows and
 *   their seats are never changed nor deleted, and no row is added to a frozen version; a proposal is
 *   deleted only while it is a draft without frozen versions (M07 RB-08); proposal_events accepts
 *   only inserts (D-199). Foreign key actions do not fire triggers on MariaDB: the physical deletion
 *   of a draft cascades on its working version, rows, seats and history (C3, §5.3.4), and the trigger
 *   on proposals guards that cascade.
 * - The foreign keys of inventory_commitments towards the proposals (D-334) and
 *   notification_logs.proposal_id (D-299) arrive with F5.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE OR REPLACE SEQUENCE proposal_numbers START WITH 204 MINVALUE 1 INCREMENT BY 1 NOCACHE NOCYCLE');

        Schema::create('proposals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('number')->unique();
            $table->foreignId('agent_id')->constrained('users')->restrictOnDelete();
            // Null only in a draft (automatic save, D-165); D-375 creates the draft with the client.
            $table->foreignId('client_id')->nullable()->constrained('clients')->restrictOnDelete();
            $table->foreignId('season_id')->nullable()->constrained('seasons')->restrictOnDelete();
            $table->string('status', 32)->default('draft');
            // Last frozen version (F5); the foreign key is added after proposal_versions.
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('last_rejection_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('cancel_reason')->nullable();
            $table->foreignId('review_locked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('review_locked_at')->nullable();
            // The source of a duplicate (D-091): deleting the source draft keeps the copy.
            $table->foreignId('duplicated_from_id')->nullable()->constrained('proposals')->nullOnDelete();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->index(['agent_id', 'status']);
            $table->index(['status', 'submitted_at']);
            $table->index(['season_id', 'status']);
            $table->index('client_id');
        });

        DB::statement('ALTER TABLE proposals ADD CONSTRAINT proposals_number_positive CHECK (number >= 1)');
        DB::statement("ALTER TABLE proposals ADD CONSTRAINT proposals_status_valid CHECK (status IN ('draft', 'submitted', 'approved', 'rejected', 'cancelled'))");
        DB::statement("ALTER TABLE proposals ADD CONSTRAINT proposals_client_season_outside_draft CHECK (status = 'draft' OR (client_id IS NOT NULL AND season_id IS NOT NULL))");
        DB::statement('ALTER TABLE proposals ADD CONSTRAINT proposals_decision_coherent CHECK ((decided_at IS NULL AND decided_by IS NULL) OR (decided_at IS NOT NULL AND decided_by IS NOT NULL))');
        DB::statement('ALTER TABLE proposals ADD CONSTRAINT proposals_cancellation_coherent CHECK ((cancelled_at IS NULL AND cancelled_by IS NULL) OR (cancelled_at IS NOT NULL AND cancelled_by IS NOT NULL))');
        DB::statement('ALTER TABLE proposals ADD CONSTRAINT proposals_review_lock_coherent CHECK ((review_locked_by IS NULL AND review_locked_at IS NULL) OR (review_locked_by IS NOT NULL AND review_locked_at IS NOT NULL))');

        Schema::create('proposal_versions', function (Blueprint $table): void {
            $table->id();
            // Pure child of the proposal: the deletion of a draft removes its working version (C3).
            $table->foreignId('proposal_id')->constrained('proposals')->cascadeOnDelete();
            $table->unsignedSmallInteger('number')->nullable();
            // 1 for the working version, NULL for the frozen ones: one working version per proposal.
            $table->unsignedTinyInteger('working_slot')->nullable()->storedAs('IF(`number` IS NULL, 1, NULL)');
            $table->timestamp('frozen_at')->nullable();
            $table->foreignId('frozen_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('change_note')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            // Client frozen with the version (RB-07 M05, D-252), written by F5.
            $table->string('client_name', 255)->nullable();
            $table->foreignId('client_logo_file_id')->nullable()->constrained('stored_files')->restrictOnDelete();
            $table->foreignId('commodity_sector_id')->nullable()->constrained('commodity_sectors')->restrictOnDelete();
            $table->string('sector_free_text', 255)->nullable();
            $table->decimal('list_total', 12, 2)->default(0);
            $table->string('discount_type', 32)->nullable();
            $table->decimal('discount_value', 12, 2)->nullable();
            $table->decimal('discount_amount', 12, 2)->nullable();
            $table->decimal('final_total', 12, 2)->nullable();
            // Campaign KPIs (D-093, D-111); top_matches null = not available or not applicable (D-320).
            $table->date('campaign_starts_on')->nullable();
            $table->unsignedTinyInteger('campaign_months')->nullable();
            $table->unsignedSmallInteger('total_matches')->nullable();
            $table->unsignedInteger('total_spots')->nullable();
            $table->unsignedSmallInteger('top_matches')->nullable();
            $table->boolean('is_forced')->default(false);
            $table->text('forced_reason')->nullable();
            // Data model column for migrated proposals: always false, nothing is migrated (D-227).
            $table->boolean('is_legacy')->default(false);
            $table->json('snapshot')->nullable();
            $table->timestamps();

            $table->unique(['proposal_id', 'number']);
            $table->unique(['proposal_id', 'working_slot'], 'proposal_versions_one_working_unique');
        });

        DB::statement('ALTER TABLE proposal_versions ADD CONSTRAINT proposal_versions_frozen_coherent CHECK (
            (number IS NULL AND frozen_at IS NULL AND frozen_by IS NULL)
            OR (number IS NOT NULL AND number >= 1 AND frozen_at IS NOT NULL AND frozen_by IS NOT NULL)
        )');
        DB::statement('ALTER TABLE proposal_versions ADD CONSTRAINT proposal_versions_period_valid CHECK (starts_on IS NULL OR ends_on IS NULL OR ends_on >= starts_on)');
        DB::statement("ALTER TABLE proposal_versions ADD CONSTRAINT proposal_versions_discount_coherent CHECK (
            (discount_type IS NULL AND discount_value IS NULL AND discount_amount IS NULL)
            OR (discount_type IN ('percent', 'amount') AND discount_value IS NOT NULL AND discount_amount IS NOT NULL
                AND discount_value >= 0 AND discount_amount >= 0 AND (discount_type = 'amount' OR discount_value <= 100))
        )");
        DB::statement('ALTER TABLE proposal_versions ADD CONSTRAINT proposal_versions_totals_valid CHECK (
            list_total >= 0 AND (final_total IS NULL OR final_total >= 0) AND (discount_amount IS NULL OR discount_amount <= list_total)
        )');
        DB::statement('ALTER TABLE proposal_versions ADD CONSTRAINT proposal_versions_forced_coherent CHECK (
            (is_forced = 0 AND forced_reason IS NULL)
            OR (is_forced = 1 AND forced_reason IS NOT NULL AND CHAR_LENGTH(TRIM(forced_reason)) > 0)
        )');
        DB::statement('ALTER TABLE proposal_versions ADD CONSTRAINT proposal_versions_sector_free_text_filled CHECK (sector_free_text IS NULL OR CHAR_LENGTH(TRIM(sector_free_text)) > 0)');

        Schema::table('proposals', function (Blueprint $table): void {
            $table->foreign('current_version_id')->references('id')->on('proposal_versions')->restrictOnDelete();
        });

        Schema::create('proposal_version_clubs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('proposal_version_id')->constrained('proposal_versions')->cascadeOnDelete();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            // The league of the club in the season; null while the club is not enrolled (D-328).
            $table->foreignId('competition_season_id')->nullable()->constrained('competition_seasons')->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('territory_id')->constrained('territories')->restrictOnDelete();
            $table->foreignId('visibility_type_id')->constrained('visibility_types')->restrictOnDelete();
            // Null while the agent has not chosen a package yet (the draft is always saved, D-373).
            $table->foreignId('partnership_tier_id')->nullable()->constrained('partnership_tiers')->restrictOnDelete();
            $table->foreignId('led_format_id')->nullable()->constrained('led_formats')->restrictOnDelete();
            $table->unsignedSmallInteger('led_seconds_per_match')->nullable();
            $table->unsignedSmallInteger('home_matches')->nullable();
            $table->string('matches_source', 32)->nullable();
            $table->foreignId('price_list_id')->nullable()->constrained('price_lists')->restrictOnDelete();
            $table->decimal('led_unit_price', 12, 2)->nullable();
            $table->decimal('led_price', 12, 2)->nullable();
            $table->decimal('partnership_price', 12, 2)->nullable();
            $table->decimal('hospitality_price', 12, 2)->nullable();
            $table->decimal('club_total', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['proposal_version_id', 'club_id']);
            $table->index('club_id');
        });

        // RB-08 M06, D-088: one LED format per club, with its seconds (C6; the 30″ step is a rule of the
        // code, read from settings, D-063); at most a whole match (D-345).
        DB::statement('ALTER TABLE proposal_version_clubs ADD CONSTRAINT proposal_version_clubs_led_coherent CHECK (
            (led_format_id IS NULL AND led_seconds_per_match IS NULL)
            OR (led_format_id IS NOT NULL AND led_seconds_per_match IS NOT NULL AND led_seconds_per_match BETWEEN 1 AND 5400)
        )');
        DB::statement("ALTER TABLE proposal_version_clubs ADD CONSTRAINT proposal_version_clubs_matches_source_valid CHECK (matches_source IS NULL OR matches_source IN ('calendar', 'standard'))");
        DB::statement('ALTER TABLE proposal_version_clubs ADD CONSTRAINT proposal_version_clubs_prices_valid CHECK (
            (led_unit_price IS NULL OR led_unit_price >= 0) AND (led_price IS NULL OR led_price >= 0)
            AND (partnership_price IS NULL OR partnership_price >= 0) AND (hospitality_price IS NULL OR hospitality_price >= 0)
            AND (club_total IS NULL OR club_total >= 0)
        )');

        Schema::create('proposal_version_club_seats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('proposal_version_club_id')->constrained('proposal_version_clubs')->cascadeOnDelete();
            $table->foreignId('hospitality_variant_id')->constrained('hospitality_variants')->restrictOnDelete();
            $table->unsignedTinyInteger('seats');
            $table->decimal('seat_price', 12, 2)->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['proposal_version_club_id', 'hospitality_variant_id'], 'proposal_version_club_seats_variant_unique');
            $table->index('hospitality_variant_id');
        });

        // D-147, D-293: the ceiling of hospitality_max_seats; the total of 6 per club is in the code.
        DB::statement('ALTER TABLE proposal_version_club_seats ADD CONSTRAINT proposal_version_club_seats_seats_range CHECK (seats BETWEEN 1 AND 6)');
        DB::statement('ALTER TABLE proposal_version_club_seats ADD CONSTRAINT proposal_version_club_seats_prices_valid CHECK ((seat_price IS NULL OR seat_price >= 0) AND (price IS NULL OR price >= 0))');

        Schema::create('proposal_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('proposal_id')->constrained('proposals')->cascadeOnDelete();
            $table->foreignId('proposal_version_id')->nullable()->constrained('proposal_versions')->nullOnDelete();
            $table->string('type', 32);
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['proposal_id', 'created_at']);
        });

        DB::statement("ALTER TABLE proposal_events ADD CONSTRAINT proposal_events_type_valid CHECK (type IN (
            'created', 'submitted', 'withdrawn', 'edited_in_review', 'discount_set', 'approved', 'forced', 'rejected',
            'resubmitted', 'new_version', 'cancelled', 'duplicated'
        ))");

        $this->createTriggers();
    }

    private function createTriggers(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER proposals_before_delete BEFORE DELETE ON proposals FOR EACH ROW
            BEGIN
                IF OLD.status <> 'draft'
                    OR EXISTS (SELECT 1 FROM proposal_versions WHERE proposal_id = OLD.id AND frozen_at IS NOT NULL)
                THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'proposals are never deleted: only a draft without frozen versions can be deleted';
                END IF;
            END
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER proposal_versions_before_update BEFORE UPDATE ON proposal_versions FOR EACH ROW
            BEGIN
                IF OLD.frozen_at IS NOT NULL THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'frozen proposal versions are immutable';
                END IF;
            END
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER proposal_versions_before_delete BEFORE DELETE ON proposal_versions FOR EACH ROW
            BEGIN
                IF OLD.frozen_at IS NOT NULL THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'frozen proposal versions are immutable';
                END IF;
            END
            SQL);

        foreach (['insert' => 'NEW', 'update' => 'OLD', 'delete' => 'OLD'] as $event => $row) {
            // On update both the old and the new version are checked: a row cannot move into or out
            // of a frozen version.
            $versions = $event === 'update' ? 'OLD.proposal_version_id, NEW.proposal_version_id' : "{$row}.proposal_version_id";
            $upper = strtoupper($event);

            DB::unprepared(<<<SQL
                CREATE TRIGGER proposal_version_clubs_before_{$event} BEFORE {$upper} ON proposal_version_clubs FOR EACH ROW
                BEGIN
                    IF EXISTS (SELECT 1 FROM proposal_versions WHERE id IN ({$versions}) AND frozen_at IS NOT NULL) THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'the rows of a frozen proposal version are immutable';
                    END IF;
                END
                SQL);

            $rows = $event === 'update' ? 'OLD.proposal_version_club_id, NEW.proposal_version_club_id' : "{$row}.proposal_version_club_id";

            DB::unprepared(<<<SQL
                CREATE TRIGGER proposal_version_club_seats_before_{$event} BEFORE {$upper} ON proposal_version_club_seats FOR EACH ROW
                BEGIN
                    IF EXISTS (
                        SELECT 1 FROM proposal_version_clubs c JOIN proposal_versions v ON v.id = c.proposal_version_id
                        WHERE c.id IN ({$rows}) AND v.frozen_at IS NOT NULL
                    ) THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'the rows of a frozen proposal version are immutable';
                    END IF;
                END
                SQL);
        }

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER proposal_events_before_update BEFORE UPDATE ON proposal_events FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'proposal_events rows are never changed: the history only accepts inserts';
            END
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER proposal_events_before_delete BEFORE DELETE ON proposal_events FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'proposal_events rows are never deleted: the history only accepts inserts';
            END
            SQL);
    }

    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table): void {
            $table->dropForeign(['current_version_id']);
        });

        Schema::dropIfExists('proposal_events');
        Schema::dropIfExists('proposal_version_club_seats');
        Schema::dropIfExists('proposal_version_clubs');
        Schema::dropIfExists('proposal_versions');
        Schema::dropIfExists('proposals');
        DB::statement('DROP SEQUENCE IF EXISTS proposal_numbers');
    }
};
