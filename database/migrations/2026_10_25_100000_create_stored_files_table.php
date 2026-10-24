<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Files of the portal (C10, D-184, D-218): client and club logos, slide images, Excel calendars,
 * generated PDFs. The entities point to a row with a "…_file_id" column, added by their slices.
 *
 * - path is built by App\Domain\Files\Services\FileStorage: "<purpose>/<yyyy>/<mm>/<ulid>.<ext>",
 *   never from the name chosen by the user; original_name is kept only for display and downloads.
 * - mime_type, size_bytes, sha256, width and height describe the stored file, i.e. the image after
 *   the re-encoding (D-218).
 * - created_by is null for the system (server commands, generated documents).
 *
 * A file is never overwritten (C10, M05 RB-07): a new upload is a new row and a new file, also
 * when the content is identical. The UPDATE trigger refuses any change to a row; DELETE is allowed
 * for the clean-up of rows nobody points to.
 *
 * migrate:fresh drops the table and its trigger with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stored_files', function (Blueprint $table): void {
            $table->id();
            $table->string('disk', 32);
            $table->string('path', 255);
            $table->string('purpose', 32);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->string('original_name', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['disk', 'path']);
            $table->index('sha256');
            $table->index(['purpose', 'created_at']);
        });

        // C9: constraints in the database where possible.
        DB::statement('ALTER TABLE stored_files ADD CONSTRAINT stored_files_size_positive CHECK (size_bytes > 0)');
        DB::statement("ALTER TABLE stored_files ADD CONSTRAINT stored_files_sha256_hex CHECK (CAST(sha256 AS BINARY) REGEXP '^[0-9a-f]{64}$')");
        DB::statement('ALTER TABLE stored_files ADD CONSTRAINT stored_files_dimensions CHECK ((width IS NULL AND height IS NULL) OR (width > 0 AND height > 0))');

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER stored_files_before_update BEFORE UPDATE ON stored_files FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'stored_files rows are immutable: store a new file instead';
            END
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('stored_files');
    }
};
