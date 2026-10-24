<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * System data (settings from PR-07, catalogues with the next slices) in every environment: the
     * seeder only inserts what is missing. Fictitious users are seeded only on development
     * machines, never in staging or production.
     */
    public function run(): void
    {
        $this->call(SystemDataSeeder::class);

        if (app()->environment('local')) {
            $this->call(DevelopmentUsersSeeder::class);
        }
    }
}
