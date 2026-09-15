<?php

namespace App\Console\Commands;

use App\Services\PassimarkPackage\PackageImporter;
use Illuminate\Console\Command;

class PassimarkPackageImport extends Command
{
    protected $signature = 'passimark:package:import {file : path to a .psmk/.psme/.psmm package}
        {--cert_slug= : override the destination certification slug}';

    protected $description = 'Import a .psmk (or .psme/.psmm) package into the catalog (create-only)';

    public function handle(): int
    {
        $file = $this->argument('file');
        if (! is_file($file)) {
            $this->error("File not found: {$file}");
            return self::FAILURE;
        }

        try {
            $summary = PackageImporter::import($file, [
                'cert_slug' => $this->option('cert_slug'),
                'original_filename' => basename($file),
            ]);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info("Imported {$summary['content_type']} package {$summary['package_id']} (sha256 {$summary['checksum']})");
        foreach ($summary['created'] as $kind => $count) {
            $this->line("  {$kind}: {$count}");
        }

        return self::SUCCESS;
    }
}