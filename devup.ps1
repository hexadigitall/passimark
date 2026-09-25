# Passimark two-server devup: boots BOTH php artisan serve (:8000) AND Vite (:5173) together.
# 127.0.0.1:8000 is Laravel's HTML shell; without Vite running the JS bundle never reaches the
# browser and the page renders white EVEN WHEN THE BUILD IS GREEN - that is the real recurring
# white-screen class (a missing-error class the old ErrorBoundary fix could never cure because the
# JS itself was never served). This helper makes "the white screen" impossible to reproduce: one
# command boots both servers, then opens the app. Stop both with Ctrl+C.
$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot
Write-Host '[passimark devup] artisan serve on :8000 + vite on :5173 - both up, one screen' -ForegroundColor Cyan
$web  = Start-Process pwsh -ArgumentList '-NoProfile','-Command',"php artisan serve --host=127.0.0.1 --port=8000" -WorkingDirectory $PSScriptRoot
$vite = Start-Process pwsh -ArgumentList '-NoProfile','-Command',"npm run dev" -WorkingDirectory $PSScriptRoot
Start-Sleep -Seconds 2
Start-Process 'http://127.0.0.1:8000'
Write-Host 'Both booted. Press Ctrl+C when done - both windows close together.' -ForegroundColor Cyan
try { Wait-Process -Id $web.Id,$vite.Id } catch {}