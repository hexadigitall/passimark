# ==============================================================================
# Passimark Mobile — Native Android Build Script (PowerShell)
# Builds: Debug & Release APK, Android App Bundle (.aab)
# ==============================================================================

param (
    [string]$Target = "all" # "apk", "aab", or "all"
)

$ErrorActionPreference = "Stop"

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "  Passimark Mobile — Native Android Build Pipeline" -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan

# 1. Environment Detection
if (-not $env:JAVA_HOME) {
    if (Test-Path "C:\Program Files\Java\jdk-17") {
        $env:JAVA_HOME = "C:\Program Files\Java\jdk-17"
    } elseif (Test-Path "C:\Program Files\Java\jdk-21") {
        $env:JAVA_HOME = "C:\Program Files\Java\jdk-21"
    }
}
Write-Host "[1/5] JAVA_HOME: $env:JAVA_HOME" -ForegroundColor Green

if (-not $env:ANDROID_HOME) {
    if (Test-Path "D:\android-sdk") {
        $env:ANDROID_HOME = "D:\android-sdk"
    } elseif (Test-Path "$env:LOCALAPPDATA\Android\Sdk") {
        $env:ANDROID_HOME = "$env:LOCALAPPDATA\Android\Sdk"
    }
}
Write-Host "[2/5] ANDROID_HOME: $env:ANDROID_HOME" -ForegroundColor Green

# 2. Build Frontend
Write-Host "[3/5] Building production web assets..." -ForegroundColor Yellow
npm run build

# 3. Synchronize Capacitor Assets
Write-Host "[4/5] Copying assets to Android native shell..." -ForegroundColor Yellow
.\node_modules\.bin\cap.cmd copy android

# 4. Execute Gradle Build
Push-Location android
try {
    if ($Target -eq "apk" -or $Target -eq "all") {
        Write-Host "[5/5] Compiling Android APK (assembleDebug)..." -ForegroundColor Yellow
        .\gradlew.bat assembleDebug
    }

    if ($Target -eq "aab" -or $Target -eq "all") {
        Write-Host "[5/5] Compiling Android App Bundle (bundleDebug)..." -ForegroundColor Yellow
        .\gradlew.bat bundleDebug
    }
} finally {
    Pop-Location
}

Write-Host "`nBuild Complete! Artifacts:" -ForegroundColor Green
Get-ChildItem -Recurse android\app\build\outputs\apk\debug\*.apk, android\app\build\outputs\bundle\debug\*.aab | Select-Object FullName, Length, LastWriteTime | Format-Table -AutoSize
