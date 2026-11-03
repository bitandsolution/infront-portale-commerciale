<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Partnership and hospitality prices, benefits and on-top texts of a price list (M03 F-07…F-10;
 * D-029, D-055, D-117, D-335, progettazione-tecnica §5.3.3; slice F2-03).
 *
 * - partnership_prices: seasonal fixed price of a club and tier (D-029); never a row for the
 *   "Libera" package, which has no price (RB-04): a rule across tables, kept by the Action.
 * - hospitality_prices: price per seat of a club and variant for the season (D-055).
 * - "Not available" is the absence of the row (D-190), as for the LED prices.
 * - partnership_benefits: one optional text per club, tier and category (D-117, D-335).
 * - on_top_assets: descriptive texts without price (D-049), for a club or general (club_id null).
 * - Every foreign key is RESTRICT (C3). The unique keys have short names: the generated ones
 *   exceed the 64 characters of MariaDB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partnership_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('price_list_id')->constrained('price_lists')->restrictOnDelete();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->foreignId('partnership_tier_id')->constrained('partnership_tiers')->restrictOnDelete();
            $table->decimal('season_price', 12, 2);
            $table->timestamps();

            $table->unique(['price_list_id', 'club_id', 'partnership_tier_id'], 'partnership_prices_cell_unique');
            $table->index('club_id');
            $table->index('partnership_tier_id');
        });

        Schema::create('hospitality_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('price_list_id')->constrained('price_lists')->restrictOnDelete();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->foreignId('hospitality_variant_id')->constrained('hospitality_variants')->restrictOnDelete();
            $table->decimal('seat_price', 12, 2);
            $table->timestamps();

            $table->unique(['price_list_id', 'club_id', 'hospitality_variant_id'], 'hospitality_prices_cell_unique');
            $table->index('club_id');
            $table->index('hospitality_variant_id');
        });

        Schema::create('partnership_benefits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('price_list_id')->constrained('price_lists')->restrictOnDelete();
            $table->foreignId('club_id')->constrained('clubs')->restrictOnDelete();
            $table->foreignId('partnership_tier_id')->constrained('partnership_tiers')->restrictOnDelete();
            $table->foreignId('benefit_category_id')->constrained('benefit_categories')->restrictOnDelete();
            $table->text('body');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['price_list_id', 'club_id', 'partnership_tier_id', 'benefit_category_id'], 'partnership_benefits_cell_unique');
            $table->index('club_id');
            $table->index('partnership_tier_id');
            $table->index('benefit_category_id');
        });

        Schema::create('on_top_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('price_list_id')->constrained('price_lists')->restrictOnDelete();
            $table->foreignId('club_id')->nullable()->constrained('clubs')->restrictOnDelete();
            $table->string('title', 150);
            $table->text('body');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['price_list_id', 'club_id']);
        });

        DB::statement('ALTER TABLE partnership_prices ADD CONSTRAINT partnership_prices_season_price_not_negative CHECK (season_price >= 0)');
        DB::statement('ALTER TABLE hospitality_prices ADD CONSTRAINT hospitality_prices_seat_price_not_negative CHECK (seat_price >= 0)');
        DB::statement('ALTER TABLE partnership_benefits ADD CONSTRAINT partnership_benefits_body_filled CHECK (CHAR_LENGTH(TRIM(body)) > 0)');
        DB::statement('ALTER TABLE on_top_assets ADD CONSTRAINT on_top_assets_title_filled CHECK (CHAR_LENGTH(TRIM(title)) > 0)');
        DB::statement('ALTER TABLE on_top_assets ADD CONSTRAINT on_top_assets_body_filled CHECK (CHAR_LENGTH(TRIM(body)) > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('on_top_assets');
        Schema::dropIfExists('partnership_benefits');
        Schema::dropIfExists('hospitality_prices');
        Schema::dropIfExists('partnership_prices');
    }
};
