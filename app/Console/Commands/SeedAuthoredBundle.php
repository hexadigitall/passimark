<?php

namespace App\Console\Commands;

use Database\Seeders\AuthoredBundleSeeder;
use Illuminate\Console\Command;

/**
 * Seed one authored bundle (or every validated bundle in data/bundles).
 *
 * The plain `db:seed` command cannot forward a bundle choice to a seeder, so this
 * wraps AuthoredBundleSeeder and declares the --bundle option it reads.
 */
class SeedAuthoredBundle extends Command
{
    protected $signature = 'passimark:seed-bundle
        {--bundle= : Cert key (data/bundles/<key>.json). Omit to seed every validated bundle in the folder.}';

    protected $description = 'Seed authored bundles, replacing the synthetic catalog content for each cert.';

    public function handle(): int
    {
        $this->laravel->make(AuthoredBundleSeeder::class)
            ->setContainer($this->laravel)
            ->setCommand($this)
            ->__invoke();

        return self::SUCCESS;
    }
}