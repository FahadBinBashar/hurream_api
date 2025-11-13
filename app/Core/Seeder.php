<?php

namespace App\Core;

class Seeder
{
    public function run(): void
    {
        $seederFile = dirname(__DIR__, 1) . '/../database/seeders/DatabaseSeeder.php';
        if (!file_exists($seederFile)) {
            return;
        }

        $seeder = require $seederFile;
        $seeder->run();
    }
}
