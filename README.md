
# Passimark v1.0 - Adaptive Exam Intelligence
Master Every Session. Unlock Your Certification.

Laravel 11 + Inertia + React CAT Simulator with Session Gating.

## Install
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install && npm run dev
php artisan serve

Default logins:
admin@passimark.com / password (instructor)
student@passimark.com / password

## Structure
- 46 Sessions (Phase 1-4) seeded from CISSP textbook
- CAT Engine: IRT 3PL adaptive (theta, b, a, c)
- Gating: student completes -> pending_approval -> admin approves -> next unlocks
- Modes: cat (150Q adaptive), timed (180min), practice (instant feedback)

## Key Files
- database/seeders/PassimarkSeeder.php - 46 sessions + 500+ questions
- app/Services/CatEngine.php - adaptive logic
- resources/js/Pages/* - React Inertia UI (Pearson VUE style)
