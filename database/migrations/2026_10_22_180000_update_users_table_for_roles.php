<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Users and roles (PR-02; D-208, D-209; progettazione-tecnica §5.3.6).
 *
 * Replaces the columns of the Laravel skeleton (name, email_verified_at) with the D-209 model:
 * one role per user stored as VARCHAR(32) (C8, RB-01), deactivation instead of deletion (RB-05)
 * and the two-factor columns used by Fortify from PR-03.
 *
 * The skeleton table never reached production, so dropping its columns is safe (D-178).
 * The migration refuses to run on a table with rows: on a development machine use migrate:fresh --seed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->ensureEmpty();

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['name', 'email_verified_at']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('first_name', 100)->after('id');
            $table->string('last_name', 100)->after('first_name');
            $table->string('phone', 32)->nullable()->after('email');
            $table->string('role', 32)->after('phone');
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            $table->boolean('is_active')->default(true)->after('two_factor_confirmed_at');
            $table->timestamp('activated_at')->nullable()->after('is_active');
            $table->timestamp('deactivated_at')->nullable()->after('activated_at');
            $table->foreignId('deactivated_by')->nullable()->after('deactivated_at')
                ->constrained('users')->restrictOnDelete();
            $table->timestamp('last_login_at')->nullable()->after('deactivated_by');

            $table->index(['role', 'is_active']);
        });

        // C8, RB-01: exactly one of the fixed roles (App\Enums\Role).
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('admin', 'manager', 'agent'))");

        // RB-05: an inactive user always carries the deactivation date, an active one never does.
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_deactivation_check CHECK '
            .'((is_active = 1 AND deactivated_at IS NULL) OR (is_active = 0 AND deactivated_at IS NOT NULL))');
    }

    public function down(): void
    {
        $this->ensureEmpty();

        DB::statement('ALTER TABLE users DROP CONSTRAINT users_deactivation_check');
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_role_check');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['deactivated_by']);
            $table->dropIndex(['role', 'is_active']);
            $table->dropColumn([
                'first_name', 'last_name', 'phone', 'role',
                'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
                'is_active', 'activated_at', 'deactivated_at', 'deactivated_by', 'last_login_at',
            ]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('name')->after('id');
            $table->timestamp('email_verified_at')->nullable()->after('email');
        });
    }

    private function ensureEmpty(): void
    {
        if (DB::table('users')->exists()) {
            throw new RuntimeException(
                'The users table contains rows (skeleton test users). Run "php artisan migrate:fresh --seed" instead.'
            );
        }
    }
};
