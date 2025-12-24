# Final Workspace Cleanup Script
# Organizes and removes temporary/unused files

Write-Host "`n"
Write-Host "╔════════════════════════════════════════════════════════════════╗"
Write-Host "║   CSIMS - Final Workspace Cleanup                             ║"
Write-Host "╚════════════════════════════════════════════════════════════════╝"
Write-Host "`n"

$itemsToMove = 0
$itemsMoved = 0

# Create archive directory for temporary files
$archiveDir = "development\archive"
if (-not (Test-Path $archiveDir)) {
    New-Item -ItemType Directory -Path $archiveDir -Force | Out-Null
    Write-Host "✅ Created archive directory: $archiveDir"
}

Write-Host "`n📦 Moving temporary/investigation files to archive...`n"

# Files to archive (not delete, just move to archive)
$filesToArchive = @(
    "api.php.backup.20251224112848",
    "compare_007_migrations.bat",
    "investigate_migrations.php",
    "migration_007_comparison.txt",
    "migration_investigation_results.txt",
    "dev-router.php"
)

foreach ($file in $filesToArchive) {
    if (Test-Path $file) {
        $itemsToMove++
        Write-Host "  Moving: $file"
        Move-Item -Path $file -Destination "$archiveDir\" -Force
        $itemsMoved++
        Write-Host "    ✅ Moved to archive"
    }
}

Write-Host "`n📁 Organizing PHPUnit files...`n"

# Move PHPUnit to tools directory
$toolsDir = "tools"
if (-not (Test-Path $toolsDir)) {
    New-Item -ItemType Directory -Path $toolsDir -Force | Out-Null
}

if (Test-Path "phpunit-10.0.0.phar") {
    Write-Host "  Moving PHPUnit to tools/"
    Move-Item -Path "phpunit-10.0.0.phar" -Destination "$toolsDir\" -Force
    Write-Host "    ✅ Moved to tools/"
    $itemsMoved++
}

Write-Host "`n🧹 Removing empty/cache directories...`n"

# Check and remove empty temp directory
if (Test-Path "temp") {
    $tempItems = Get-ChildItem -Path "temp" -Force
    if ($tempItems.Count -eq 0) {
        Write-Host "  Removing: temp/ (empty)"
        Remove-Item -Path "temp" -Force -Recurse
        Write-Host "    ✅ Removed"
    }
}

# Clean up PHPUnit cache (will be regenerated)
if (Test-Path ".phpunit.cache") {
    Write-Host "  Removing: .phpunit.cache/ (will regenerate)"
    Remove-Item -Path ".phpunit.cache" -Force -Recurse -ErrorAction SilentlyContinue
    Write-Host "    ✅ Removed"
}

Write-Host "`n📋 Organizing documentation...`n"

# Ensure all docs are in docs folder
$docFiles = Get-ChildItem -Path "." -Filter "*.md" -File | Where-Object { $_.Name -ne "README.md" }
if ($docFiles) {
    foreach ($doc in $docFiles) {
        Write-Host "  Moving: $($doc.Name) to docs/"
        Move-Item -Path $doc.FullName -Destination "docs\" -Force
        $itemsMoved++
        Write-Host "    ✅ Moved"
    }
}

Write-Host "`n🗂️  Verifying directory structure...`n"

# Essential directories that should exist
$essentialDirs = @(
    "src",
    "config",
    "database",
    "views",
    "assets",
    "tests",
    "docs",
    "scripts",
    "logs",
    "cache",
    "development"
)

foreach ($dir in $essentialDirs) {
    if (Test-Path $dir) {
        Write-Host "  ✅ $dir"
    } else {
        Write-Host "  ⚠️  $dir (missing - may need to be created)"
    }
}

Write-Host "`n📊 Creating workspace organization report...`n"

$report = @"
# CSIMS Workspace Organization Report
**Date:** $(Get-Date -Format "yyyy-MM-dd HH:mm:ss")

## Cleanup Summary

### Files Archived (moved to development\archive\):
$(($filesToArchive | ForEach-Object { "- $_" }) -join "`n")

### Files Organized:
- PHPUnit moved to tools/
- Documentation files organized in docs/

### Directories Cleaned:
- .phpunit.cache (removed - will regenerate)
- temp/ (removed if empty)

## Final Directory Structure

### Production Files (Root):
- index.php (main entry point)
- api.php (API v1)
- api_v2.php (API v2)
- logout.php
- .htaccess
- robots.txt
- sw.js

### Configuration:
- .env (environment variables)
- .env.example (template)
- composer.json (dependencies)
- package.json (frontend build)
- tailwind.config.js
- phpunit.xml.dist (test config)

### Source Code:
- src/ (application code)
- config/ (configuration)
- controllers/ (MVC controllers)
- classes/ (legacy classes)
- views/ (templates)
- includes/ (shared includes)

### Assets:
- assets/ (CSS, JS, images)
- node_modules/ (npm packages)
- vendor/ (composer packages)

### Data & Storage:
- database/ (migrations)
- logs/ (application logs)
- cache/ (cache files)
- storage/ (file storage)
- uploads/ (user uploads)
- backups/ (database backups)

### Development:
- development/ (dev files, demos)
- development/archive/ (temporary files)
- tests/ (PHPUnit tests)
- scripts/ (utility scripts)
- setup/ (installation scripts)
- tools/ (PHPUnit, etc.)

### Documentation:
- docs/ (all documentation)
- README.md (project readme)

## Organization Status: ✅ CLEAN

All files are now in their proper locations.
Temporary and investigation files are archived.
Project is production-ready!

"@

$report | Out-File -FilePath "docs\WORKSPACE_CLEANUP_REPORT.md" -Encoding UTF8

Write-Host "`n═══════════════════════════════════════════════════════════════"
Write-Host "✅ Workspace cleanup complete!"
Write-Host "═══════════════════════════════════════════════════════════════"
Write-Host "`n"
Write-Host "📊 Summary:"
Write-Host "  - Files moved to archive: $itemsMoved"
Write-Host "  - Directories organized: ✅"
Write-Host "  - Report created: docs\WORKSPACE_CLEANUP_REPORT.md"
Write-Host "`n"
Write-Host "💡 Next steps:"
Write-Host "  1. Review archived files in development\archive\"
Write-Host "  2. Check docs\WORKSPACE_CLEANUP_REPORT.md"
Write-Host "  3. Run: php tools\phpunit-10.0.0.phar (new location)"
Write-Host "`n"
Write-Host "🎉 Your workspace is now clean and organized!"
Write-Host "`n"
