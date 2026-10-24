<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail (M10 §4; D-127…D-129, D-210, D-211; progettazione-tecnica §5.3.6).
 *
 * - No foreign key: an event outlives every object it talks about, users included (M10 §8).
 *   user_name and user_role are copies taken when the event is written.
 * - No created_at / updated_at (exception to C12): occurred_at is the creation time, with
 *   microseconds so that events of the same request keep their order, and a row never changes.
 *
 * Append-only also for the database (D-210, RB-A1, M10-CA3): two triggers refuse UPDATE and DELETE
 * to every MariaDB user, root included, with only the two exceptions the retention needs (D-283):
 * - UPDATE that only clears ip_address (D-211: the IP expires before the event);
 * - DELETE of an event older than 5 years (RB-A2, D-128).
 * The runtime user of the portal has no DDL privilege, so it cannot drop the triggers
 * (database/sql/audit-users.sql, requirement R7).
 *
 * migrate:fresh drops the table and its triggers with it; TRUNCATE (tests) does not fire them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->id();
            $table->dateTime('occurred_at', 6);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name', 201)->nullable();
            $table->string('user_role', 32)->nullable();
            $table->string('area', 32);
            $table->string('action', 64);
            $table->string('object_type', 32)->nullable();
            $table->unsignedBigInteger('object_id')->nullable();
            $table->string('object_label', 255)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('reason', 1000)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('request_id', 36)->nullable();

            $table->index('occurred_at');
            $table->index(['object_type', 'object_id']);
            $table->index(['user_id', 'occurred_at']);
            $table->index(['area', 'action', 'occurred_at']);
        });

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER audit_events_before_update BEFORE UPDATE ON audit_events FOR EACH ROW
            BEGIN
                IF NOT (
                    NEW.ip_address IS NULL
                    AND NEW.id <=> OLD.id
                    AND NEW.occurred_at <=> OLD.occurred_at
                    AND NEW.user_id <=> OLD.user_id
                    AND NEW.user_name <=> OLD.user_name
                    AND NEW.user_role <=> OLD.user_role
                    AND NEW.area <=> OLD.area
                    AND NEW.action <=> OLD.action
                    AND NEW.object_type <=> OLD.object_type
                    AND NEW.object_id <=> OLD.object_id
                    AND NEW.object_label <=> OLD.object_label
                    AND NEW.old_values <=> OLD.old_values
                    AND NEW.new_values <=> OLD.new_values
                    AND NEW.reason <=> OLD.reason
                    AND NEW.request_id <=> OLD.request_id
                ) THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'audit_events is append-only: only ip_address can be cleared';
                END IF;
            END
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER audit_events_before_delete BEFORE DELETE ON audit_events FOR EACH ROW
            BEGIN
                IF OLD.occurred_at >= UTC_TIMESTAMP(6) - INTERVAL 5 YEAR THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'audit_events is append-only: only events older than 5 years can be deleted';
                END IF;
            END
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS audit_events_before_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS audit_events_before_update');

        Schema::dropIfExists('audit_events');
    }
};
