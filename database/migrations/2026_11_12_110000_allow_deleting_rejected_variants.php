<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * D-412 (DM-03a): the agent corrects a rejected group and can also remove one of its variants, so a
 * rejected proposal without frozen versions can be deleted like a draft. A rejected proposal never has
 * frozen versions (they are born at the approval, DM-03b); every other proposal is still never deleted
 * (M07 RB-08).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS proposals_before_delete');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER proposals_before_delete BEFORE DELETE ON proposals FOR EACH ROW
            BEGIN
                IF OLD.status NOT IN ('draft', 'rejected')
                    OR EXISTS (SELECT 1 FROM proposal_versions WHERE proposal_id = OLD.id AND frozen_at IS NOT NULL)
                THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'proposals are never deleted: only a draft or a rejected proposal without frozen versions can be deleted';
                END IF;
            END
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS proposals_before_delete');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER proposals_before_delete BEFORE DELETE ON proposals FOR EACH ROW
            BEGIN
                IF OLD.status <> 'draft'
                    OR EXISTS (SELECT 1 FROM proposal_versions WHERE proposal_id = OLD.id AND frozen_at IS NOT NULL)
                THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'proposals are never deleted: only a draft without frozen versions can be deleted';
                END IF;
            END
            SQL);
    }
};
