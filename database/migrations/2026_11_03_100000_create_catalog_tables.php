<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogue of what is sold (M03 F-01…F-04, F-06; D-190, progettazione-tecnica §5.3.3; slice F2-01):
 * territories, visibility types, inventory pools, LED formats with the pools they consume,
 * partnership tiers, hospitality variants and benefit categories.
 *
 * - The codes are stable (C3) and come only from the seeder of the system data (D-331, D-332): the
 *   pools consumed by a format (D-051) and the conditions of the slides (D-202) depend on them.
 * - Values are deactivated, never deleted (C4): the price lists, capacities and proposals of the
 *   next slices point to them with restrictive foreign keys.
 * - led_format_pools is a pure child of the format (CASCADE, C3); a pool used by a format cannot
 *   be deleted (RESTRICT). A format consumes the same seconds on each of its pools (M04 RB-02).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('territories', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('visibility_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('inventory_pools', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->foreignId('visibility_type_id')->constrained('visibility_types')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('led_formats', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $table->foreignId('visibility_type_id')->constrained('visibility_types')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('led_format_pools', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('led_format_id')->constrained('led_formats')->cascadeOnDelete();
            $table->foreignId('inventory_pool_id')->constrained('inventory_pools')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['led_format_id', 'inventory_pool_id']);
            $table->index('inventory_pool_id');
        });

        Schema::create('partnership_tiers', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->boolean('is_free_package')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('hospitality_variants', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('benefit_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // C9: codes in capital letters, digits and underscores; "(?-i)" keeps the pattern
        // case-sensitive with the accent- and case-insensitive collation of the columns.
        foreach (['territories', 'visibility_types', 'inventory_pools', 'led_formats', 'partnership_tiers', 'hospitality_variants', 'benefit_categories'] as $table) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_code_format CHECK (code REGEXP '(?-i)^[A-Z][A-Z0-9_]*$')");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_name_filled CHECK (CHAR_LENGTH(TRIM(name)) > 0)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('benefit_categories');
        Schema::dropIfExists('hospitality_variants');
        Schema::dropIfExists('partnership_tiers');
        Schema::dropIfExists('led_format_pools');
        Schema::dropIfExists('led_formats');
        Schema::dropIfExists('inventory_pools');
        Schema::dropIfExists('visibility_types');
        Schema::dropIfExists('territories');
    }
};
