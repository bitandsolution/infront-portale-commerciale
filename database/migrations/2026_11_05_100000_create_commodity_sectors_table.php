<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Commodity sectors of the clients (M05 F-05; D-075, D-357; progettazione-tecnica §5.3.4; slice
 * F3-01).
 *
 * - The code is stable (C3): the 24 initial sectors come from the system data, a sector created by
 *   the admin gets a code from its name, which then never changes.
 * - requires_free_text: ALTRO_VARIE asks for a free text on the client (RB-04).
 * - Deactivated, never deleted (C4, RB-08): the clients of F3-02 point to a sector with a
 *   restrictive foreign key. Names are unique (the collation ignores case and accents).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commodity_sectors', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 100)->unique();
            $table->boolean('requires_free_text')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // C9: codes in capital letters, digits and underscores; "(?-i)" keeps the pattern
        // case-sensitive with the accent- and case-insensitive collation of the column.
        DB::statement("ALTER TABLE commodity_sectors ADD CONSTRAINT commodity_sectors_code_format CHECK (code REGEXP '(?-i)^[A-Z][A-Z0-9_]*$')");
        DB::statement('ALTER TABLE commodity_sectors ADD CONSTRAINT commodity_sectors_name_filled CHECK (CHAR_LENGTH(TRIM(name)) > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('commodity_sectors');
    }
};
