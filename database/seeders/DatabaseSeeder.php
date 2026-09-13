<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Fresh installs get the real Hexadigitall CISSP textbook bundle (46 sessions,
     * 980 assessment questions) when the extracted JSON is present; otherwise the
     * lightweight prototype seeder is used as a fallback. CI seeds PassimarkSeeder
     * explicitly, and either can be invoked on demand via --class.
     */
    public function run(): void
    {
        if (is_file(database_path(CISSPBundleSeeder::BUNDLE_JSON))) {
            $this->call(CISSPBundleSeeder::class);
        } else {
            $this->call(PassimarkSeeder::class);
        }
    }
}