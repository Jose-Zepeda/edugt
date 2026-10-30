<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

require_once __DIR__.'/SimulatedStudentsSeeder.php';

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ContentSeeder::class,
            DevUsersSeeder::class,
            SimulatedStudentsSeeder::class,
        ]);
    }
}
