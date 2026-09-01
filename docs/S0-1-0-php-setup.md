# S0.1.0: PHP and Development Environment Setup

## Objective
Install and configure PHP 8.2+, Composer, and Node.js so the Passimark project can run locally.

## Option A: Laragon (Recommended - Fastest Setup)
**Time: 10-15 minutes**

Laragon is a lightweight, all-in-one dev environment for Windows. It includes PHP, Composer, MySQL, Node.js, and more.

### Steps
1. Download Laragon from https://laragon.org/download/
2. Run the installer (choose Full installation)
3. Launch Laragon (adds right-click context menu)
4. Verify tools are installed:
   ```powershell
   php -v          # Should show PHP 8.2+
   composer -v     # Should show Composer version
   node -v         # Should show Node.js version
   npm -v          # Should show npm version
   ```

### After installation
Laragon adds PHP to your PATH automatically. Restart your terminal after install.

---

## Option B: WSL2 with Ubuntu (If you prefer Linux)
**Time: 20-30 minutes**

If you have WSL2 installed with Ubuntu:

```bash
# In WSL terminal
sudo apt update
sudo apt install php8.2 php8.2-cli php8.2-zip composer nodejs npm
php -v
composer -v
```

Then navigate to your project: `cd /mnt/d/projects/passimark`

---

## Option C: Manual Setup (Advanced)
**Time: 30-45 minutes**

1. **Install PHP 8.2:**
   - Download from https://windows.php.net/downloads/releases/
   - Extract to `C:\php`
   - Add `C:\php` to system PATH

2. **Install Composer:**
   - Download from https://getcomposer.org/download/
   - Run installer, select your PHP path

3. **Install Node.js:**
   - Download from https://nodejs.org/ (18+ LTS)
   - Run installer

4. **Verify:**
   ```powershell
   php -v
   composer -v
   node -v
   npm -v
   ```

---

## Quick Test
After setup, verify everything works:

```powershell
cd d:\projects\passimark
php artisan --version
npm --version
```

Both should return version numbers without errors.

---

## Next Steps
Once this is complete:
1. Run S0.1.1 (Validate Laravel startup)
2. Continue with the rest of Sprint 0 tasks

## Troubleshooting
- **"php: command not found"** → PHP not in PATH or Laragon not added to PATH (restart terminal)
- **"composer: command not found"** → Composer not installed or not in PATH
- **"node: command not found"** → Node.js not installed

---

## Completion Checklist
- [ ] PHP 8.2+ installed and in PATH
- [ ] Composer installed and in PATH
- [ ] Node.js 18+ installed
- [ ] npm installed and in PATH
- [ ] All four commands work: `php -v`, `composer -v`, `node -v`, `npm -v`
- [ ] Can run `php artisan --version` in project folder

When all items are checked, proceed to S0.1.1.
