<?php
// Direct seeding
require_once 'vendor/autoload.php';
require_once 'database/seeders/PassimarkSeeder.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Starting database seeding...\n";
$seeder = new \Database\Seeders\PassimarkSeeder();
$seeder->run();
echo "Seeding completed successfully!\n";
