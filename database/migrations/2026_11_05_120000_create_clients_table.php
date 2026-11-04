<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clients (M05 F-01, F-03, F-04; D-074…D-081, D-196, D-197, D-358, D-361; progettazione-tecnica
 * §5.3.4; slice F3-02).
 *
 * - owner_id: the agent who owns the client (D-076, D-358); the agent sees only his own clients
 *   (HasOwner). created_by: who created it (the agent, or the admin for an agent).
 * - vat_number: Italian VAT number of 11 digits (D-361), unique when present (D-080, D-196): many
 *   clients without a VAT number are allowed (NULL is never equal to NULL).
 * - company_name_normalized: the company name without case, accents, spaces, punctuation and legal
 *   form (CompanyNameNormalizer, D-361). The duplicate check on it is in the code, not a UNIQUE
 *   constraint, because two companies with the same name and different VAT numbers are allowed
 *   (D-197).
 * - logo_file_id: the current logo in stored_files; a new logo is a new file, never overwritten
 *   (C10, RB-07).
 * - is_active and merged_into_id: deactivation and merge arrive with F3-03 (D-359); a client is never
 *   deleted (C4, RB-06).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('company_name', 255);
            $table->string('company_name_normalized', 255)->index();
            $table->char('vat_number', 11)->nullable()->unique();
            $table->string('email', 255);
            $table->foreignId('logo_file_id')->constrained('stored_files')->restrictOnDelete();
            $table->foreignId('commodity_sector_id')->constrained('commodity_sectors')->restrictOnDelete();
            $table->string('sector_free_text', 255)->nullable();
            $table->string('contact_name', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('merged_into_id')->nullable()->constrained('clients')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        // C9. "(?-i)" keeps the patterns case-sensitive with the accent- and case-insensitive
        // collation of the columns. A NULL VAT number passes the CHECK (absent).
        DB::statement("ALTER TABLE clients ADD CONSTRAINT clients_vat_number_format CHECK (vat_number REGEXP '(?-i)^[0-9]{11}$')");
        DB::statement('ALTER TABLE clients ADD CONSTRAINT clients_company_name_length CHECK (CHAR_LENGTH(TRIM(company_name)) >= 2)');
        DB::statement("ALTER TABLE clients ADD CONSTRAINT clients_company_name_normalized_format CHECK (company_name_normalized REGEXP '(?-i)^[A-Z0-9]+$')");
        DB::statement('ALTER TABLE clients ADD CONSTRAINT clients_email_filled CHECK (CHAR_LENGTH(TRIM(email)) > 0)');
        DB::statement('ALTER TABLE clients ADD CONSTRAINT clients_sector_free_text_filled CHECK (sector_free_text IS NULL OR CHAR_LENGTH(TRIM(sector_free_text)) > 0)');
        // D-359: a merged client is no longer active (the merge into itself is refused by the code
        // of F3-03: a CHECK cannot read the auto-increment id).
        DB::statement('ALTER TABLE clients ADD CONSTRAINT clients_merged_inactive CHECK (merged_into_id IS NULL OR is_active = 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
