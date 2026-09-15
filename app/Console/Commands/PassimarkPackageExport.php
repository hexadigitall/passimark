<?php

namespace App\Console\Commands;

use App\Models\PassimarkCertificationTrack;
use App\Models\PassimarkExam;
use App\Models\PassimarkSession;
use App\Services\PassimarkPackage\PackageExporter;
use Illuminate\Console\Command;

class PassimarkPackageExport extends Command
{
    protected $signature = 'passimark:package:export
        {--course= : certification track slug to export as a course package}
        {--module= : session id to export as a module package}
        {--exam= : exam id to export as an exam package}
        {--dest= : destination .psmk file path (default: storage/app/exports/) }';

    protected $description = 'Export a certification course, module (session), or exam to a .psmk package';

    public function handle(): int
    {
        $flags = array_filter(['course' => $this->option('course'), 'module' => $this->option('module'), 'exam' => $this->option('exam')], fn ($v) => $v !== null);
        if (count($flags) !== 1) {
            $this->error('Specify exactly one of --course, --module, or --exam.');
            return self::FAILURE;
        }

        $type = array_key_first($flags);
        $value = $flags[$type];
        $dest = (string) $this->option('dest');

        try {
            $package = match ($type) {
                'course' => PackageExporter::exportCourse(PassimarkCertificationTrack::query()->where('slug', $value)->firstOrFail()),
                'module' => PackageExporter::exportModule(PassimarkSession::query()->findOrFail((int) $value)),
                'exam' => PackageExporter::exportExam(PassimarkExam::query()->findOrFail((int) $value)),
            };
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if ($dest === '') {
            $dest = storage_path("app/exports/{$package['package_id']}-{$value}.psmk");
        }
        $dir = dirname($dest);
        if (! is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        file_put_contents($dest, $package['binary']);

        $this->line("Exported {$package['content_type']} package '{$package['package_id']}' ({$package['size']} bytes, sha256 {$package['checksum']})");
        $this->info("Saved to {$dest}");

        return self::SUCCESS;
    }
}