<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Slides composed with blocks (D-399, D-428…D-432; DM-07):
 *
 * - proposal_custom_slides: at most 3 per group of variants (checked by the Actions), the working
 *   content changed by the agent on drafts and rejected groups, by admin and manager while the group
 *   waits: title, preset layout, section of the document it closes (D-429), image of the slide (layout
 *   "image_text"), blocks (JSON, normalized by CustomSlideContent) and whether it enters the document
 *   (its tick in the step "Documento"). Pure child of the group: CASCADE;
 * - proposal_group_files: the images uploaded for the slides of a group, so the StoredFilePolicy
 *   knows who may see them (the users who see the group) also after a block is changed or the slide
 *   is deleted; insert only, a file belongs to one group;
 * - proposal_versions.custom_slides: ApproveGroup copies the slides of the group on the frozen version
 *   of every variant (like the choice of the slides, D-419), so the document and its regenerations
 *   read them there, immutable with the version (C11). NULL before DM-07: no composed slide.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposal_custom_slides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('proposal_group_id')->constrained('proposal_groups')->cascadeOnDelete();
            $table->unsignedTinyInteger('sort_order');
            $table->string('title', 120);
            $table->string('layout', 32);
            $table->foreignId('slide_section_id')->constrained('slide_sections')->restrictOnDelete();
            $table->foreignId('image_file_id')->nullable()->constrained('stored_files')->restrictOnDelete();
            $table->json('blocks');
            $table->boolean('is_included')->default(true);
            $table->timestamps();

            $table->index(['proposal_group_id', 'sort_order'], 'proposal_custom_slides_group_order_index');
        });

        DB::statement("ALTER TABLE proposal_custom_slides ADD CONSTRAINT proposal_custom_slides_layout_valid CHECK (layout IN ('one_column', 'two_columns', 'image_text'))");
        DB::statement('ALTER TABLE proposal_custom_slides ADD CONSTRAINT proposal_custom_slides_sort_order_valid CHECK (sort_order BETWEEN 1 AND 255)');

        Schema::create('proposal_group_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('proposal_group_id')->constrained('proposal_groups')->cascadeOnDelete();
            $table->foreignId('stored_file_id')->constrained('stored_files')->restrictOnDelete();
            $table->timestamps();

            $table->unique('stored_file_id', 'proposal_group_files_file_unique');
        });

        Schema::table('proposal_versions', function (Blueprint $table): void {
            $table->json('custom_slides')->nullable()->after('excluded_slide_ids');
        });
    }

    public function down(): void
    {
        Schema::table('proposal_versions', function (Blueprint $table): void {
            $table->dropColumn('custom_slides');
        });

        Schema::dropIfExists('proposal_group_files');

        DB::statement('ALTER TABLE proposal_custom_slides DROP CONSTRAINT proposal_custom_slides_sort_order_valid');
        DB::statement('ALTER TABLE proposal_custom_slides DROP CONSTRAINT proposal_custom_slides_layout_valid');
        Schema::dropIfExists('proposal_custom_slides');
    }
};
