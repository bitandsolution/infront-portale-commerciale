<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Structure of the sports registry (M02; D-185, progettazione-tecnica §5.3.2; slice F1-01):
 * disciplines, seasons, competitions and editions (competition × season).
 *
 * - Every Serie C group is a competition of its own (D-185), with a tier (1 = A, 2 = B, 3 = C).
 * - The standard number of home matches lives on the edition: the number of teams can change
 *   between seasons (D-034). calendar_loaded_at is set by the calendar import (F1-04).
 * - Seasons of the same discipline never overlap: a rule on more rows, checked in the code (C9).
 * - Codes are stable (C3): they are what the API of release 1.1 exposes (D-237).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disciplines', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('seasons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('discipline_id')->constrained('disciplines')->restrictOnDelete();
            $table->string('code', 16);
            $table->string('name', 100);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 32)->default('planned');
            $table->timestamps();

            $table->unique(['discipline_id', 'code']);
            $table->index(['discipline_id', 'starts_on']);
        });

        Schema::create('competitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('discipline_id')->constrained('disciplines')->restrictOnDelete();
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->string('short_name', 40);
            $table->string('type', 32)->default('league');
            $table->unsignedTinyInteger('tier');
            $table->boolean('has_top_match_kpi')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('competition_seasons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('competition_id')->constrained('competitions')->restrictOnDelete();
            $table->foreignId('season_id')->constrained('seasons')->restrictOnDelete();
            $table->unsignedSmallInteger('standard_home_matches');
            $table->timestamp('calendar_loaded_at')->nullable();
            $table->timestamps();

            $table->unique(['competition_id', 'season_id']);
            $table->index('season_id');
        });

        // C9: constraints in the database where possible. "(?-i)" keeps the patterns case-sensitive
        // with the accent- and case-insensitive collation of the columns.
        DB::statement("ALTER TABLE disciplines ADD CONSTRAINT disciplines_code_format CHECK (code REGEXP '(?-i)^[A-Z][A-Z0-9_]*$')");
        DB::statement("ALTER TABLE seasons ADD CONSTRAINT seasons_code_format CHECK (code REGEXP '^[0-9]{4}-[0-9]{2}$')");
        DB::statement('ALTER TABLE seasons ADD CONSTRAINT seasons_dates CHECK (ends_on > starts_on)');
        DB::statement("ALTER TABLE seasons ADD CONSTRAINT seasons_status CHECK (status IN ('planned', 'open', 'closed'))");
        DB::statement("ALTER TABLE competitions ADD CONSTRAINT competitions_code_format CHECK (code REGEXP '(?-i)^[A-Z][A-Z0-9_]*$')");
        DB::statement("ALTER TABLE competitions ADD CONSTRAINT competitions_type CHECK (type IN ('league', 'cup'))");
        DB::statement('ALTER TABLE competitions ADD CONSTRAINT competitions_tier CHECK (tier BETWEEN 1 AND 9)');
        DB::statement('ALTER TABLE competition_seasons ADD CONSTRAINT competition_seasons_standard CHECK (standard_home_matches > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_seasons');
        Schema::dropIfExists('competitions');
        Schema::dropIfExists('seasons');
        Schema::dropIfExists('disciplines');
    }
};
