<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database. The SEED_CATALOG env switch selects the v4
     * worldwide load path:
     *   - `worldwide` -> 17 flagship certs (WorldwidePassimarkCatalogSeeder) plus a
     *     deterministic original question bank for every session pool
     *     (WorldwideOriginalQuestionBankSeeder; CISSP keeps its own textbook bundle)
     *   - `uniform`   -> 205-cert uniform ladder (Uniform205CatalogSeeder)
     * Otherwise fresh installs get the real Hexadigitall CISSP textbook bundle
     * (46 sessions, 980 questions) when the extracted JSON is present; the lightweight
     * prototype seeder is the fallback. CI seeds PassimarkSeeder explicitly, and any
     * seeder can be invoked on demand via --class.
     */
    public function run(): void
    {
        $catalog = env('SEED_CATALOG');
        if ($catalog === 'worldwide') {
            $this->call(WorldwidePassimarkCatalogSeeder::class);
            $this->call(WorldwideOriginalQuestionBankSeeder::class);
        } elseif ($catalog === 'uniform') {
            $this->call(Uniform205CatalogSeeder::class);
        } elseif (is_file(database_path(CISSPBundleSeeder::BUNDLE_JSON))) {
            $this->call(CISSPBundleSeeder::class);
        } else {
            $this->call(PassimarkSeeder::class);
        }
    }
}