<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Configuration of the simulation of the logo on the LED (D-398, D-423…D-427; DM-06): one row per
 * club and kind of photo (1ª fila, 2ª fila, Jumbo/Domination), plus the generic rows of Infront
 * (club_id NULL) used for the clubs without their own photo and for the network documents.
 *
 * - photo_file_id: the photo of the field (stored_files, re-encoded, never overwritten: a new photo
 *   is a new file);
 * - areas: JSON list of the areas of the LED in pixels of the photo, each four points [x, y] from
 *   the top left clockwise: one area for a row, two (upper and lower row) for Jumbo/Domination;
 * - logo_height_percent: height of the logo in % of the height of the LED;
 * - spacing: pixels of the photo between two logos;
 * - club_slot: generated (club or 0) for the unique key with the generic rows.
 * Not by season: the stadiums seldom change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('led_mockup_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->nullable()->constrained('clubs')->restrictOnDelete();
            $table->unsignedBigInteger('club_slot')->storedAs('IFNULL(`club_id`, 0)');
            $table->string('photo_kind', 32);
            $table->foreignId('photo_file_id')->constrained('stored_files')->restrictOnDelete();
            $table->json('areas');
            $table->unsignedTinyInteger('logo_height_percent');
            $table->unsignedSmallInteger('spacing');
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['club_slot', 'photo_kind'], 'led_mockup_settings_club_kind_unique');
        });

        DB::statement("ALTER TABLE led_mockup_settings ADD CONSTRAINT led_mockup_settings_kind_valid CHECK (photo_kind IN ('row_1', 'row_2', 'both_rows'))");
        DB::statement('ALTER TABLE led_mockup_settings ADD CONSTRAINT led_mockup_settings_height_valid CHECK (logo_height_percent BETWEEN 10 AND 100)');
        DB::statement('ALTER TABLE led_mockup_settings ADD CONSTRAINT led_mockup_settings_areas_valid CHECK (JSON_VALID(areas) AND JSON_TYPE(areas) = \'ARRAY\' AND JSON_LENGTH(areas) BETWEEN 1 AND 2)');
    }

    public function down(): void
    {
        Schema::dropIfExists('led_mockup_settings');
    }
};
