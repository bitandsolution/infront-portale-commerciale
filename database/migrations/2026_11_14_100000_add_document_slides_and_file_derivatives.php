<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step "Documento" of the configurator (D-397, D-418…D-420; DM-05):
 *
 * - proposal_groups.document_mode ("complete" / "summary") and excluded_slide_ids (JSON list of slide
 *   ids): the working choice of the slides of the group, changed by the agent on drafts and rejected
 *   groups, by admin and manager while the group waits for approval (D-419);
 * - the same two columns on proposal_versions, nullable: ApproveGroup copies the choice of the group
 *   on the frozen version of every variant, so the document and its regenerations read it there
 *   (immutable with the version, C11). Versions frozen before this migration have NULL: complete
 *   document without exclusions;
 * - stored_file_derivatives (D-310, D-420): one derived file (thumbnail) per original file and kind,
 *   insert only like stored_files: triggers refuse every UPDATE and DELETE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposal_groups', function (Blueprint $table): void {
            $table->string('document_mode', 32)->default('complete')->after('season_id');
            $table->json('excluded_slide_ids')->nullable()->after('document_mode');
        });

        DB::statement("ALTER TABLE proposal_groups ADD CONSTRAINT proposal_groups_document_mode_valid CHECK (document_mode IN ('complete', 'summary'))");

        Schema::table('proposal_versions', function (Blueprint $table): void {
            $table->string('document_mode', 32)->nullable()->after('top_matches');
            $table->json('excluded_slide_ids')->nullable()->after('document_mode');
        });

        DB::statement("ALTER TABLE proposal_versions ADD CONSTRAINT proposal_versions_document_mode_valid CHECK (document_mode IS NULL OR document_mode IN ('complete', 'summary'))");

        Schema::create('stored_file_derivatives', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('original_file_id')->constrained('stored_files')->restrictOnDelete();
            $table->string('kind', 32);
            $table->foreignId('derivative_file_id')->constrained('stored_files')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['original_file_id', 'kind'], 'stored_file_derivatives_original_kind_unique');
            $table->unique('derivative_file_id', 'stored_file_derivatives_derivative_unique');
        });

        DB::statement("ALTER TABLE stored_file_derivatives ADD CONSTRAINT stored_file_derivatives_kind_valid CHECK (kind IN ('thumbnail'))");
        DB::statement('ALTER TABLE stored_file_derivatives ADD CONSTRAINT stored_file_derivatives_distinct CHECK (original_file_id <> derivative_file_id)');

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER stored_file_derivatives_before_update BEFORE UPDATE ON stored_file_derivatives FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'stored file derivatives are never changed';
            END
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER stored_file_derivatives_before_delete BEFORE DELETE ON stored_file_derivatives FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'stored file derivatives are never deleted';
            END
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS stored_file_derivatives_before_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS stored_file_derivatives_before_update');
        Schema::dropIfExists('stored_file_derivatives');

        DB::statement('ALTER TABLE proposal_versions DROP CONSTRAINT proposal_versions_document_mode_valid');

        Schema::table('proposal_versions', function (Blueprint $table): void {
            $table->dropColumn(['document_mode', 'excluded_slide_ids']);
        });

        DB::statement('ALTER TABLE proposal_groups DROP CONSTRAINT proposal_groups_document_mode_valid');

        Schema::table('proposal_groups', function (Blueprint $table): void {
            $table->dropColumn(['document_mode', 'excluded_slide_ids']);
        });
    }
};
