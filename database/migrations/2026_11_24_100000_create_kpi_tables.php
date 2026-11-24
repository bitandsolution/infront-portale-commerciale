<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * KPI of the clubs (M08 §5, F-05, F-08; D-108, D-109, D-203, D-490…D-492; progettazione-tecnica
 * §5.3.5; slice F6-01).
 *
 * - kpi_definitions: the 13 KPI of D-108 from the system data (ContentSystemDataSeeder, D-490), with a
 *   stable code, the group (club / digital; "group" is a reserved word of SQL: quoted in the CHECK),
 *   the unit, the flag of the network sums (D-108) and the order. No page: like the sections (D-406).
 * - club_kpi_values: one value per club, season and KPI (UQ), an integer ≥ 0 (BIGINT UNSIGNED, M08 §9);
 *   a missing value has no row (D-491). Source and period are required with the value, because they
 *   go into the note of the layout (D-491, layout-dinamici §4.6). updated_at and updated_by change only
 *   when value, source or period change: they drive the warning of the KPI not updated for more than
 *   6 months (F-08, D-492).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_definitions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->string('group', 32);
            $table->string('unit', 32);
            $table->boolean('network_aggregate')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::statement("ALTER TABLE kpi_definitions ADD CONSTRAINT kpi_definitions_code_format CHECK (code REGEXP '(?-i)^[A-Z][A-Z0-9_]*$')");
        DB::statement("ALTER TABLE kpi_definitions ADD CONSTRAINT kpi_definitions_group_valid CHECK (`group` IN ('club', 'digital'))");
        DB::statement('ALTER TABLE kpi_definitions ADD CONSTRAINT kpi_definitions_name_filled CHECK (CHAR_LENGTH(TRIM(name)) > 0)');

        Schema::create('club_kpi_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->foreignId('season_id')->constrained('seasons')->restrictOnDelete();
            $table->foreignId('kpi_definition_id')->constrained('kpi_definitions')->restrictOnDelete();
            $table->unsignedBigInteger('value');
            $table->string('source', 200);
            $table->string('reference_period', 100);
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['club_id', 'season_id', 'kpi_definition_id'], 'club_kpi_values_club_season_kpi_unique');
            $table->index(['season_id', 'club_id'], 'club_kpi_values_season_club_index');
        });

        DB::statement('ALTER TABLE club_kpi_values ADD CONSTRAINT club_kpi_values_source_filled CHECK (CHAR_LENGTH(TRIM(source)) > 0)');
        DB::statement('ALTER TABLE club_kpi_values ADD CONSTRAINT club_kpi_values_period_filled CHECK (CHAR_LENGTH(TRIM(reference_period)) > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('club_kpi_values');
        Schema::dropIfExists('kpi_definitions');
    }
};
