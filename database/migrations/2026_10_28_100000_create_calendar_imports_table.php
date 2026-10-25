<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Calendar imports (M02 F-09, F-10, FL-02; progettazione-tecnica §5.3.2; slice F1-04).
 *
 * - One row per uploaded Excel file of an edition: the preview persists between the requests of
 *   FL-02 (column mapping, names associated as aliases, recalculation, confirmation; D-325).
 * - status: preview → confirmed or discarded. Unconfirmed previews are discarded at night
 *   (calendar-imports:prune). Rows are never deleted: the file stays in stored_files (C10).
 * - The counters are those of the confirmation (FL-02 step 5): inserted, updated, unchanged and
 *   discarded rows.
 * - matches.last_import_id: the last import that inserted or changed the match (F-10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('competition_season_id')->constrained('competition_seasons')->restrictOnDelete();
            $table->foreignId('file_id')->constrained('stored_files')->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 32)->default('preview');
            $table->json('column_mapping')->nullable();
            $table->json('preview')->nullable();
            $table->unsignedSmallInteger('inserted_count')->default(0);
            $table->unsignedSmallInteger('updated_count')->default(0);
            $table->unsignedSmallInteger('unchanged_count')->default(0);
            $table->unsignedSmallInteger('discarded_count')->default(0);
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['competition_season_id', 'status']);
            $table->index(['status', 'updated_at']);
        });

        DB::statement("ALTER TABLE calendar_imports ADD CONSTRAINT calendar_imports_status CHECK (status IN ('preview', 'confirmed', 'discarded'))");
        DB::statement("ALTER TABLE calendar_imports ADD CONSTRAINT calendar_imports_confirmed CHECK ((status = 'confirmed') = (confirmed_at IS NOT NULL))");

        Schema::table('matches', function (Blueprint $table): void {
            $table->foreignId('last_import_id')->nullable()->after('source')->constrained('calendar_imports')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('last_import_id');
        });

        Schema::dropIfExists('calendar_imports');
    }
};
