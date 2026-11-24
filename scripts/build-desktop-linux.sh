#!/usr/bin/env bash
# ==============================================================================
# Passimark Desktop — Native Linux Build & Package Script
# Targets: .deb (Debian/Ubuntu), .AppImage (Universal Linux), .tar.gz
# ==============================================================================

set -euo pipefail

echo "=========================================================="
echo "  Passimark Desktop — Linux Native Build Pipeline"
echo "=========================================================="

# 1. Check / Install System Dependencies on Debian/Ubuntu
if command -v apt-get &> /dev/null; then
    echo "[1/4] Checking Linux build dependencies..."
    MISSING_PKGS=()
    for pkg in libwebkit2gtk-4.1-dev build-essential curl wget file libssl-dev libayatana-appindicator3-dev librsvg2-dev; do
        if ! dpkg -s "$pkg" &> /dev/null; then
            MISSING_PKGS+=("$pkg")
        fi
    done

    if [ ${#MISSING_PKGS[@]} -gt 0 ]; then
        echo "Installing required system libraries: ${MISSING_PKGS[*]}"
        sudo apt-get update
        sudo apt-get install -y "${MISSING_PKGS[@]}"
    else
        echo "All system libraries installed."
    fi
fi

# 2. Compile Web Frontend & Sync Manifest
echo "[2/4] Compiling frontend assets and native client..."
npm run build

# 3. Build Tauri Native Release Packages
echo "[3/4] Building Tauri 2.0 native Linux binaries and packages..."
npm run tauri:build

# 4. Display Outputs
echo "[4/4] Linux Native Build Complete!"
echo "Artifacts generated in src-tauri/target/release/bundle/:"
ls -la src-tauri/target/release/bundle/
