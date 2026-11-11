<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Slide library (M08 §4; D-115, D-116, D-202, D-250, D-402…D-406; progettazione-tecnica §5.3.5;
 * slice DM-02).
 *
 * - slide_sections: fixed sections from the system data (D-406), ordered.
 * - slides: Infront or club scope, season, document type, section and order, static or dynamic with
 *   the layout code (enum DocumentLayout), image of the slide (PNG 1920×1080 in stored_files), the
 *   controlled options of D-250, language (it only), active. A title is required (D-402). Slides
 *   are deactivated, never deleted (C4, D-406). The image is required except for the LED
 *   simulation, whose picture is the photo of the field (D-405, DM-06). The list of layout codes is
 *   not a CHECK: it grows with DM-04…DM-07 and is validated in code.
 * - slide_conditions: one row per value with stable codes (D-202), CASCADE on the slide (C3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slide_sections', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        DB::statement("ALTER TABLE slide_sections ADD CONSTRAINT slide_sections_code_format CHECK (code REGEXP '(?-i)^[A-Z][A-Z0-9_]*$')");

        Schema::create('slides', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 120);
            $table->string('scope', 32);
            $table->foreignId('club_id')->nullable()->constrained('clubs')->restrictOnDelete();
            $table->foreignId('season_id')->constrained('seasons')->restrictOnDelete();
            $table->string('document_type', 32);
            $table->foreignId('slide_section_id')->constrained('slide_sections')->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('kind', 32);
            $table->foreignId('image_file_id')->nullable()->constrained('stored_files')->restrictOnDelete();
            $table->string('layout_code', 32)->nullable();
            $table->json('layout_options')->nullable();
            $table->string('language', 8)->default('it');
            $table->boolean('is_active')->default(true);
            $table->foreignId('copied_from_id')->nullable()->constrained('slides')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['season_id', 'scope', 'club_id', 'is_active'], 'slides_season_scope_club_active_index');
        });

        DB::statement("ALTER TABLE slides ADD CONSTRAINT slides_scope_valid CHECK (scope IN ('infront', 'club'))");
        DB::statement("ALTER TABLE slides ADD CONSTRAINT slides_scope_club CHECK ((scope = 'club' AND club_id IS NOT NULL) OR (scope = 'infront' AND club_id IS NULL))");
        DB::statement("ALTER TABLE slides ADD CONSTRAINT slides_document_type_valid CHECK (document_type IN ('network', 'club', 'both'))");
        DB::statement("ALTER TABLE slides ADD CONSTRAINT slides_kind_valid CHECK (kind IN ('static', 'dynamic'))");
        DB::statement("ALTER TABLE slides ADD CONSTRAINT slides_kind_layout CHECK ((kind = 'dynamic' AND layout_code IS NOT NULL) OR (kind = 'static' AND layout_code IS NULL))");
        DB::statement("ALTER TABLE slides ADD CONSTRAINT slides_image_required CHECK (image_file_id IS NOT NULL OR layout_code = 'led_mockup')");
        DB::statement("ALTER TABLE slides ADD CONSTRAINT slides_language_valid CHECK (language IN ('it', 'en'))");
        DB::statement('ALTER TABLE slides ADD CONSTRAINT slides_title_filled CHECK (CHAR_LENGTH(TRIM(title)) > 0)');

        Schema::create('slide_conditions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('slide_id')->constrained('slides')->cascadeOnDelete();
            $table->string('condition_type', 32);
            $table->string('value', 32);
            $table->timestamps();

            $table->unique(['slide_id', 'condition_type', 'value'], 'slide_conditions_slide_type_value_unique');
        });

        DB::statement("ALTER TABLE slide_conditions ADD CONSTRAINT slide_conditions_type_valid CHECK (condition_type IN ('partnership_tier', 'has_partnership', 'led_format', 'has_led', 'has_hospitality', 'competition_tier'))");
        DB::statement('ALTER TABLE slide_conditions ADD CONSTRAINT slide_conditions_value_filled CHECK (CHAR_LENGTH(TRIM(value)) > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('slide_conditions');
        Schema::dropIfExists('slides');
        Schema::dropIfExists('slide_sections');
    }
};
