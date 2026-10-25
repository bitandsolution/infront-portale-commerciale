<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stadiums, clubs, club aliases and external references (M02; D-185, D-186, D-188,
 * progettazione-tecnica §5.3.2; slice F1-02).
 *
 * - No competition on the club: the category lives in the seasonal enrollment (F1-03).
 * - is_infront is on the club, not per season (D-188); is_top_match_opponent marks the guests that
 *   make a top match (RB-06, D-253, D-257).
 * - An alias never points to two clubs: unique normalized alias per discipline. Aliases are pure
 *   children of the club (CASCADE, C3); clubs are deactivated, never deleted (C4, RB-08).
 * - external_references (D-186): ids of other systems (old idoc, accrediti), more than one per
 *   entity. Polymorphic, so no foreign key: integrity is kept by the code and its tests.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stadiums', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('city', 100);
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('name');
        });

        Schema::create('clubs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('discipline_id')->constrained('disciplines')->restrictOnDelete();
            $table->string('code', 32)->unique();
            $table->string('name', 150);
            $table->string('short_name', 60);
            $table->foreignId('home_stadium_id')->nullable()->constrained('stadiums')->restrictOnDelete();
            $table->foreignId('logo_file_id')->nullable()->constrained('stored_files')->restrictOnDelete();
            $table->char('primary_color', 7)->nullable();
            $table->char('secondary_color', 7)->nullable();
            $table->boolean('is_infront')->default(false);
            $table->boolean('is_top_match_opponent')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_infront', 'is_active']);
        });

        Schema::create('club_aliases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignId('discipline_id')->constrained('disciplines')->restrictOnDelete();
            $table->string('alias', 150);
            $table->string('alias_normalized', 150);
            $table->string('source', 32);
            $table->timestamps();

            $table->unique(['discipline_id', 'alias_normalized']);
            $table->index('club_id');
        });

        Schema::create('external_references', function (Blueprint $table): void {
            $table->id();
            $table->string('platform', 32);
            $table->string('entity_type', 32);
            $table->unsignedBigInteger('entity_id');
            $table->string('external_id', 64);
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->unique(['platform', 'entity_type', 'external_id']);
            $table->index(['entity_type', 'entity_id']);
        });

        // C9. "(?-i)" keeps the patterns case-sensitive with the case-insensitive collation.
        DB::statement('ALTER TABLE stadiums ADD CONSTRAINT stadiums_capacity CHECK (capacity IS NULL OR capacity > 0)');
        DB::statement("ALTER TABLE clubs ADD CONSTRAINT clubs_code_format CHECK (code REGEXP '(?-i)^[A-Z][A-Z0-9_]*$')");
        DB::statement("ALTER TABLE clubs ADD CONSTRAINT clubs_primary_color CHECK (primary_color IS NULL OR primary_color REGEXP '(?-i)^#[0-9A-F]{6}$')");
        DB::statement("ALTER TABLE clubs ADD CONSTRAINT clubs_secondary_color CHECK (secondary_color IS NULL OR secondary_color REGEXP '(?-i)^#[0-9A-F]{6}$')");
        DB::statement("ALTER TABLE club_aliases ADD CONSTRAINT club_aliases_source CHECK (source IN ('import', 'manual', 'legacy'))");
        DB::statement("ALTER TABLE club_aliases ADD CONSTRAINT club_aliases_normalized CHECK (alias_normalized <> '')");
        DB::statement("ALTER TABLE external_references ADD CONSTRAINT external_references_platform CHECK (platform REGEXP '(?-i)^[a-z][a-z0-9_]*$')");
        DB::statement("ALTER TABLE external_references ADD CONSTRAINT external_references_entity_type CHECK (entity_type REGEXP '(?-i)^[a-z][a-z0-9_]*$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('external_references');
        Schema::dropIfExists('club_aliases');
        Schema::dropIfExists('clubs');
        Schema::dropIfExists('stadiums');
    }
};
