<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seasonal enrollments, matchdays and matches (M02 F-06, F-08; D-185, D-186,
 * progettazione-tecnica §5.3.2; slice F1-03).
 *
 * - The enrollment is club × edition: promotions and relegations change the enrollments, never the
 *   club. "One league per club and season" is a rule on more rows, checked in the code (C9).
 * - Matchdays are entities (D-186) with a stage: only the regular stage counts (RB-01).
 * - Natural key of a match: matchday + home club + away club, the key of the re-import (F-10).
 *   The stadium is saved on the match (D-185), so a change of the home stadium never rewrites past
 *   matches. Matches are cancelled, never deleted (D-319, C4).
 * - The column of the last import (last_import_id) arrives with the calendar imports (F1-04).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('competition_season_id')->constrained('competition_seasons')->restrictOnDelete();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['competition_season_id', 'club_id']);
            $table->index('club_id');
        });

        Schema::create('matchdays', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('competition_season_id')->constrained('competition_seasons')->restrictOnDelete();
            $table->string('stage', 32)->default('regular');
            $table->unsignedSmallInteger('number');
            $table->timestamps();

            $table->unique(['competition_season_id', 'stage', 'number']);
        });

        Schema::create('matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('matchday_id')->constrained('matchdays')->restrictOnDelete();
            $table->foreignId('home_club_id')->constrained('clubs')->restrictOnDelete();
            $table->foreignId('away_club_id')->constrained('clubs')->restrictOnDelete();
            $table->foreignId('stadium_id')->nullable()->constrained('stadiums')->restrictOnDelete();
            $table->date('match_date');
            $table->time('kickoff_time')->nullable();
            $table->string('status', 32)->default('scheduled');
            $table->string('source', 32)->default('manual');
            $table->timestamps();

            $table->unique(['matchday_id', 'home_club_id', 'away_club_id']);
            $table->index(['home_club_id', 'match_date']);
            $table->index(['away_club_id', 'match_date']);
        });

        DB::statement("ALTER TABLE matchdays ADD CONSTRAINT matchdays_stage CHECK (stage IN ('regular', 'playoff', 'playout'))");
        DB::statement('ALTER TABLE matchdays ADD CONSTRAINT matchdays_number CHECK (number > 0)');
        DB::statement('ALTER TABLE matches ADD CONSTRAINT matches_home_away CHECK (home_club_id <> away_club_id)');
        DB::statement("ALTER TABLE matches ADD CONSTRAINT matches_status CHECK (status IN ('scheduled', 'cancelled'))");
        DB::statement("ALTER TABLE matches ADD CONSTRAINT matches_source CHECK (source IN ('import', 'manual'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
        Schema::dropIfExists('matchdays');
        Schema::dropIfExists('club_enrollments');
    }
};
