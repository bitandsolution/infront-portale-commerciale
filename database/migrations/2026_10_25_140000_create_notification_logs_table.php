<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Register of the e-mails sent by the portal (M01 §10; D-209; progettazione-tecnica §5.3.6, PR-08):
 * one row per e-mail, written by App\Domain\Notifications\Services\Notifications when the e-mail is
 * queued, then updated when the mail server accepts it (sent) or after the last try (failed).
 *
 * - Never the content of the message, never a link or a token: only kind, recipient and subject.
 * - recipient_email is the address at the time of sending (the user may change it later).
 * - error: class and message of the last exception, cut and without addresses of web pages.
 * - proposal_id (progettazione-tecnica §5.3.6) is added by slice F5 with the proposals table.
 * - Kept 12 months (D-209): notifications:prune, weekly (routes/console.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('type', 32);
            $table->string('recipient_email');
            $table->string('subject');
            $table->string('status', 32)->default('queued');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('provider_message_id')->nullable();
            $table->string('error', 1000)->nullable();
            $table->dateTime('queued_at');
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('created_at');
        });

        // C9: constraints in the database where possible. The kinds of e-mail live in the code
        // (App\Enums\NotificationType), so a new kind never needs a new constraint.
        DB::statement("ALTER TABLE notification_logs ADD CONSTRAINT notification_logs_status CHECK (status IN ('queued', 'sent', 'failed'))");
        DB::statement("ALTER TABLE notification_logs ADD CONSTRAINT notification_logs_type_format CHECK (type REGEXP '(?-i)^[a-z][a-z0-9_]*$')");
        DB::statement("ALTER TABLE notification_logs ADD CONSTRAINT notification_logs_sent_at CHECK (status <> 'sent' OR sent_at IS NOT NULL)");
        DB::statement("ALTER TABLE notification_logs ADD CONSTRAINT notification_logs_failed_at CHECK (status <> 'failed' OR failed_at IS NOT NULL)");
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
