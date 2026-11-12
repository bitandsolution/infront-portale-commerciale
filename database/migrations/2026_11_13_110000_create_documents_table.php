<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Documents of the approved groups (M09; progettazione-tecnica §5.3.5; D-120…D-125, D-204, D-205,
 * D-413; DM-04):
 *
 * - one row per generation of the document of a group of variants (D-413: proposal_group_id; the
 *   frozen versions of the variants are listed in page_model; proposal_id and proposal_version_id
 *   point to the first variant of the document, A at the approval, the chosen one after a
 *   regeneration that follows the choice of the client, D-414);
 * - status queued → generating → ready / failed, with attempts and the last error;
 * - kpi_snapshot and page_model (D-111, D-204): the document can be reproduced without composing it
 *   again;
 * - a regeneration keeps the previous row as replaced (superseded_at, superseded_by_id; D-205): only
 *   one current document per (group, format, language), with the generated column current_slot and
 *   a UNIQUE key, like the working version of the proposals.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('proposal_group_id')->constrained('proposal_groups')->restrictOnDelete();
            $table->foreignId('proposal_id')->constrained('proposals')->restrictOnDelete();
            $table->foreignId('proposal_version_id')->constrained('proposal_versions')->restrictOnDelete();
            $table->string('document_type', 32);
            $table->string('format', 8)->default('pdf');
            $table->char('language', 2)->default('it');
            $table->string('status', 32);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('last_error', 1000)->nullable();
            $table->foreignId('file_id')->nullable()->constrained('stored_files')->restrictOnDelete();
            $table->unsignedSmallInteger('page_count')->nullable();
            $table->unsignedInteger('size_bytes')->nullable();
            $table->json('kpi_snapshot')->nullable();
            $table->json('page_model')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->foreignId('superseded_by_id')->nullable()->constrained('documents')->restrictOnDelete();
            // 1 for the current document, NULL for the replaced ones: one current document per group.
            $table->unsignedTinyInteger('current_slot')->nullable()->storedAs('IF(`superseded_at` IS NULL, 1, NULL)');
            $table->timestamps();

            $table->unique(['proposal_group_id', 'format', 'language', 'current_slot'], 'documents_one_current_unique');
            $table->index(['proposal_id', 'generated_at']);
        });

        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_type_valid CHECK (document_type IN ('club', 'network'))");
        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_format_valid CHECK (format IN ('pdf', 'pptx', 'web'))");
        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_status_valid CHECK (status IN ('queued', 'generating', 'ready', 'failed'))");
        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_ready_has_file CHECK (status <> 'ready' OR (file_id IS NOT NULL AND generated_at IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
