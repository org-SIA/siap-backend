<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        if (config('siap.seed_demo') && ! app()->isProduction()) {
            $this->call(DemoSeeder::class);
        }
    }
}
