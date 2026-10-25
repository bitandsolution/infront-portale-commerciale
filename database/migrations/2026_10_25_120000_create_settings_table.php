<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * System settings (D-191; progettazione-tecnica §5.3.3, PR-07): one row per key of
 * App\Enums\SettingKey, written by the system data seeder (only the missing keys) and changed by
 * the admin from the Settings page (App\Domain\Settings\Actions\UpdateSettings, with audit).
 *
 * - value is a JSON scalar (an integer or a string): the allowed range of each key lives in the
 *   code (SettingKey), so a new key never needs a new CHECK constraint.
 * - updated_by is null for the system (seeder); otherwise the admin of the last change. The
 *   history of the changes is in the audit (C12).
 * - A missing row is not an error: the reading service returns the default of the code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->json('value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        // C9: constraints in the database where possible. "(?-i)" keeps the pattern case-sensitive
        // with the accent- and case-insensitive collation of the column.
        DB::statement("ALTER TABLE settings ADD CONSTRAINT settings_key_format CHECK (`key` REGEXP '(?-i)^[a-z][a-z0-9_]*$')");
        DB::statement("ALTER TABLE settings ADD CONSTRAINT settings_value_scalar CHECK (JSON_TYPE(`value`) IN ('INTEGER', 'STRING'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
