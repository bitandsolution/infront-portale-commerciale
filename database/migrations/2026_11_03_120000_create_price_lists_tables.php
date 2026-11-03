<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Price list of a season and LED prices (M03 F-05, F-11; D-190, D-333, progettazione-tecnica
 * §5.3.3; slice F2-02).
 *
 * - One price list per season (UNIQUE season_id), born empty in draft; publishing is final: no way
 *   back to draft (D-333). published_at and published_by are filled exactly when it is published.
 * - copied_from_id and copy_increase_pct belong to the copy with +% of the 1.1 release (M03 F-12,
 *   D-236): in the model since now, always empty in 1.0.
 * - led_prices: cost for 1′ per match (D-054) with 2 decimals (D-057), never negative. "Not available"
 *   is the absence of the row (D-190): an empty cell of the grid.
 * - Every foreign key is RESTRICT (C3): price lists are never deleted, and a club, a format or a
 *   user referenced by a price list cannot be deleted either.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_lists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('season_id')->unique()->constrained('seasons')->restrictOnDelete();
            $table->string('status', 32)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('copied_from_id')->nullable()->constrained('price_lists')->restrictOnDelete();
            $table->decimal('copy_increase_pct', 5, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('led_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('price_list_id')->constrained('price_lists')->restrictOnDelete();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->foreignId('led_format_id')->constrained('led_formats')->restrictOnDelete();
            $table->decimal('unit_price', 12, 2);
            $table->timestamps();

            $table->unique(['price_list_id', 'club_id', 'led_format_id']);
            $table->index('club_id');
            $table->index('led_format_id');
        });

        DB::statement("ALTER TABLE price_lists ADD CONSTRAINT price_lists_status_valid CHECK (status IN ('draft', 'published'))");
        DB::statement("ALTER TABLE price_lists ADD CONSTRAINT price_lists_published_coherent CHECK (
            (status = 'draft' AND published_at IS NULL AND published_by IS NULL)
            OR (status = 'published' AND published_at IS NOT NULL AND published_by IS NOT NULL)
        )");
        DB::statement('ALTER TABLE price_lists ADD CONSTRAINT price_lists_copy_coherent CHECK (
            (copied_from_id IS NULL AND copy_increase_pct IS NULL)
            OR (copied_from_id IS NOT NULL AND copy_increase_pct IS NOT NULL AND copy_increase_pct >= 0)
        )');
        DB::statement('ALTER TABLE led_prices ADD CONSTRAINT led_prices_unit_price_not_negative CHECK (unit_price >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('led_prices');
        Schema::dropIfExists('price_lists');
    }
};
