<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * System data (settings, catalogues) arrives with PR-07 and the following slices.
     * Fictitious users are seeded only on development machines, never in staging or production.
     */
    public function run(): void
    {
        if (app()->environment('local')) {
            $this->call(DevelopmentUsersSeeder::class);
        }
    }
}
