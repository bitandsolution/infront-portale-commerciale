<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Variants A…J of a proposal in the same document (D-396; slice DM, PR DM-01).
 *
 * - proposal_groups: the group of variants of one agent, for one client and one season; the
 *   document settings of DM-05 and DM-07 arrive with their PRs. A single proposal is a group with
 *   one variant.
 * - proposals.proposal_group_id and proposals.variant_letter (A…J, unique in the group). Both stay
 *   nullable in the schema, so the code of the previous release (which creates proposals without a
 *   group) still works on this schema (D-178); every proposal written by this release has both, and
 *   the CHECK keeps them together. The existing proposals become groups of one variant "A".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposal_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agent_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->restrictOnDelete();
            $table->foreignId('season_id')->nullable()->constrained('seasons')->restrictOnDelete();
            $table->timestamps();

            $table->index('agent_id');
        });

        Schema::table('proposals', function (Blueprint $table): void {
            $table->foreignId('proposal_group_id')->nullable()->after('number')->constrained('proposal_groups')->restrictOnDelete();
            $table->char('variant_letter', 1)->nullable()->after('proposal_group_id');

            $table->unique(['proposal_group_id', 'variant_letter'], 'proposals_group_variant_unique');
        });

        // A…J only (ASCII, so a lowercase letter is refused also with a case-insensitive collation),
        // group and letter both set or both empty.
        DB::statement(<<<'SQL'
            ALTER TABLE proposals ADD CONSTRAINT proposals_variant_letter_check CHECK (
                (proposal_group_id IS NULL AND variant_letter IS NULL)
                OR (proposal_group_id IS NOT NULL AND variant_letter IS NOT NULL AND ASCII(variant_letter) BETWEEN 65 AND 74)
            )
            SQL);

        $now = now();

        foreach (DB::table('proposals')->whereNull('proposal_group_id')->orderBy('id')->get(['id', 'agent_id', 'client_id', 'season_id']) as $proposal) {
            $groupId = DB::table('proposal_groups')->insertGetId([
                'agent_id' => $proposal->agent_id,
                'client_id' => $proposal->client_id,
                'season_id' => $proposal->season_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('proposals')->where('id', $proposal->id)->update(['proposal_group_id' => $groupId, 'variant_letter' => 'A']);
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE proposals DROP CONSTRAINT proposals_variant_letter_check');

        Schema::table('proposals', function (Blueprint $table): void {
            $table->dropUnique('proposals_group_variant_unique');
            $table->dropConstrainedForeignId('proposal_group_id');
            $table->dropColumn('variant_letter');
        });

        Schema::dropIfExists('proposal_groups');
    }
};
