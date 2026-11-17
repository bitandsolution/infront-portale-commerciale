<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * E-mails of the proposals (M01 §7, M07 F-08; D-221, D-299, D-474…D-478; F5-06):
 *
 * - notification_logs.proposal_id (progettazione-tecnica §5.3.6, D-299): the variant A of the group the
 *   e-mail is about. SET NULL instead of RESTRICT: a draft or a rejected proposal can still be deleted
 *   by its agent (M07 RB-08, D-412) after the e-mail of its sending, and the row of the register stays;
 * - documents.pending_notification (D-221, D-474, D-477): the e-mail to the agent that waits for this
 *   document ("approved", "new_version", or "document_ready" after a failed generation), sent and
 *   cleared by whoever marks the document "ready" or "failed"; a regeneration inherits it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_logs', function (Blueprint $table): void {
            $table->foreignId('proposal_id')->nullable()->after('user_id')->constrained('proposals')->nullOnDelete();
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->string('pending_notification', 32)->nullable()->after('status');
        });

        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_pending_notification_valid CHECK (pending_notification IS NULL OR pending_notification IN ('approved', 'new_version', 'document_ready'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE documents DROP CONSTRAINT documents_pending_notification_valid');

        Schema::table('documents', function (Blueprint $table): void {
            $table->dropColumn('pending_notification');
        });

        Schema::table('notification_logs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('proposal_id');
        });
    }
};
