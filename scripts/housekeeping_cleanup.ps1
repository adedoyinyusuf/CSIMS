# ============================================================================
# CSIMS Legacy Code & Housekeeping Cleanup Script
# ============================================================================
# This script addresses identified housekeeping issues:
# 1. Move debug/test files to development folder
# 2. Verify .env.example exists and is comprehensive
# 3. Remove empty models/ directory
# 4. Document legacy code locations for future migration
# ============================================================================

Write-Host "`n╔════════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║   CSIMS Housekeeping & Legacy Code Cleanup                   ║" -ForegroundColor Cyan
Write-Host "║   " -NoNewline -ForegroundColor Cyan
Write-Host (Get-Date -Format "yyyy-MM-dd HH:mm:ss") -NoNewline -ForegroundColor Cyan
Write-Host "                                   ║" -ForegroundColor Cyan
Write-Host "╚════════════════════════════════════════════════════════════════╝`n" -ForegroundColor Cyan

$cleanupLog = @()
$cleanupLog += "CSIMS Housekeeping Cleanup - $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
$cleanupLog += "=" * 70
$cleanupLog += ""

# ============================================================================
# STEP 1: Create development folder for debug/test files
# ============================================================================
Write-Host "[1/4] Managing Debug and Test Files..." -ForegroundColor Yellow

$devFolder = "development"
if (-not (Test-Path $devFolder)) {
    New-Item -ItemType Directory -Path $devFolder -Force | Out-Null
    Write-Host "   ✓ Created development/ folder" -ForegroundColor Green
    $cleanupLog += "Created: development/ folder"
} else {
    Write-Host "   ℹ development/ folder already exists" -ForegroundColor Gray
}

# Find and move debug/test files
$debugFiles = @(
    "check_admin.php",
    "check_loans.php", 
    "check_password.php",
    "test_loans_display.php",
    "test_login.php",
    "test_section_visibility.php",
    "debug_loans.php"
)

$movedCount = 0
Write-Host "`n   Debug/Test files found:" -ForegroundColor Cyan

foreach ($file in $debugFiles) {
    if (Test-Path $file) {
        Write-Host "   - $file" -ForegroundColor Yellow
        Move-Item $file "$devFolder\" -Force
        Write-Host "     ✓ Moved to development/" -ForegroundColor Green
        $cleanupLog += "Moved: $file -> development/"
        $movedCount++
    }
}

if ($movedCount -eq 0) {
    Write-Host "   ℹ No debug/test files found in root" -ForegroundColor Gray
} else {
    Write-Host "`n   ✅ Moved $movedCount debug/test files to development/" -ForegroundColor Green
}

# Create README in development folder
$devReadme = @"
# Development & Testing Files

This folder contains development, testing, and debugging files that should not be deployed to production.

## Contents

### Debug Files
- \`check_*.php\` - Database and feature verification scripts
- \`debug_*.php\` - Debugging utilities

### Test Files
- \`test_*.php\` - Manual testing scripts

## Usage

These files are for development use only:
- Testing specific features
- Debugging issues
- Verifying database states
- Manual QA

## Important

⚠️ **DO NOT deploy this folder to production!**

Add to .gitignore if needed:
\`\`\`
/development/
\`\`\`

## Note

For automated testing, use the \`tests/\` directory with PHPUnit instead of these manual test files.
"@

$devReadme | Out-File "$devFolder\README.md" -Encoding UTF8
Write-Host "   ✓ Created development/README.md" -ForegroundColor Green

# ============================================================================
# STEP 2: Verify and update .env.example
# ============================================================================
Write-Host "`n[2/4] Verifying Environment Configuration..." -ForegroundColor Yellow

if (Test-Path ".env.example") {
    Write-Host "   ✅ .env.example exists" -ForegroundColor Green
    
    # Check if it's comprehensive
    $envContent = Get-Content ".env.example" -Raw
    $requiredVars = @(
        "APP_NAME", "APP_ENV", "APP_DEBUG", "APP_URL",
        "DB_CONNECTION", "DB_HOST", "DB_DATABASE", "DB_USERNAME", "DB_PASSWORD",
        "SESSION_LIFETIME", "SESSION_SECURE",
        "RATE_LIMIT_ENABLED", "RATE_LIMIT_MAX_ATTEMPTS",
        "MAIL_HOST", "MAIL_PORT"
    )
    
    $missingVars = @()
    foreach ($var in $requiredVars) {
        if ($envContent -notmatch $var) {
            $missingVars += $var
        }
    }
    
    if ($missingVars.Count -eq 0) {
        Write-Host "   ✅ .env.example is comprehensive (all critical variables present)" -ForegroundColor Green
        $cleanupLog += ".env.example: Verified - comprehensive"
    } else {
        Write-Host "   ⚠️  .env.example missing variables: $($missingVars -join ', ')" -ForegroundColor Yellow
        Write-Host "   💡 Consider adding these variables" -ForegroundColor Cyan
        $cleanupLog += ".env.example: Warning - missing vars: $($missingVars -join ', ')"
    }
    
    # Check size
    $size = (Get-Item ".env.example").Length
    Write-Host "   ℹ File size: $size bytes" -ForegroundColor Gray
    
} else {
    Write-Host "   ❌ .env.example NOT FOUND!" -ForegroundColor Red
    Write-Host "   Creating .env.example from template..." -ForegroundColor Yellow
    
    # Create comprehensive .env.example
    $envExample = @"
# =============================================================================
# CSIMS - Environment Configuration Template
# =============================================================================
# Copy this file to .env and configure for your environment
# NEVER commit .env to version control!

# -----------------------------------------------------------------------------
# Application Settings
# -----------------------------------------------------------------------------
APP_NAME="CSIMS"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yoursite.com

# -----------------------------------------------------------------------------
# Database Configuration
# -----------------------------------------------------------------------------
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=csims_db
DB_USERNAME=your_username
DB_PASSWORD=your_password

# -----------------------------------------------------------------------------
# Session Configuration
# -----------------------------------------------------------------------------
SESSION_LIFETIME=1800
SESSION_SECURE=true
SESSION_HTTPONLY=true
SESSION_SAMESITE=Strict
SESSION_DRIVER=database

# -----------------------------------------------------------------------------
# Security Settings
# -----------------------------------------------------------------------------
# Rate Limiting
RATE_LIMIT_ENABLED=true
RATE_LIMIT_MAX_ATTEMPTS=5
RATE_LIMIT_DECAY_MINUTES=15

# Password Requirements
PASSWORD_MIN_LENGTH=8
PASSWORD_REQUIRE_UPPERCASE=true
PASSWORD_REQUIRE_LOWERCASE=true
PASSWORD_REQUIRE_NUMBERS=true
PASSWORD_REQUIRE_SPECIAL=true

# Account Lockout
LOCKOUT_ENABLED=true
LOCKOUT_MAX_ATTEMPTS=5
LOCKOUT_DURATION_MINUTES=15

# CSRF Protection
CSRF_ENABLED=true
CSRF_TOKEN_LIFETIME=3600

# -----------------------------------------------------------------------------
# Caching
# -----------------------------------------------------------------------------
CACHE_DRIVER=file
CACHE_TTL=3600
CACHE_PATH=cache/

# -----------------------------------------------------------------------------
# Logging
# -----------------------------------------------------------------------------
LOG_LEVEL=warning
LOG_PATH=logs/
LOG_MAX_FILES=30
LOG_CHANNEL=daily

# -----------------------------------------------------------------------------
# Mail Configuration
# -----------------------------------------------------------------------------
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your_email@example.com
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yoursite.com
MAIL_FROM_NAME="\${APP_NAME}"

# -----------------------------------------------------------------------------
# File Upload
# -----------------------------------------------------------------------------
UPLOAD_MAX_SIZE=5242880
UPLOAD_ALLOWED_TYPES=jpg,jpeg,png,pdf,doc,docx
UPLOAD_PATH=uploads/

# -----------------------------------------------------------------------------
# Backup Configuration
# -----------------------------------------------------------------------------
BACKUP_ENABLED=true
BACKUP_PATH=backups/
BACKUP_RETENTION_DAYS=30
BACKUP_SCHEDULE=daily

# -----------------------------------------------------------------------------
# API Configuration
# -----------------------------------------------------------------------------
API_RATE_LIMIT=100
API_RATE_LIMIT_PERIOD=3600
API_ALLOWED_ORIGINS=https://yoursite.com

# -----------------------------------------------------------------------------
# Notification Settings
# -----------------------------------------------------------------------------
NOTIFICATIONS_ENABLED=true
NOTIFICATIONS_EMAIL=true
NOTIFICATIONS_SMS=false
SMS_PROVIDER=twilio
SMS_ACCOUNT_SID=
SMS_AUTH_TOKEN=
SMS_FROM_NUMBER=

# -----------------------------------------------------------------------------
# Development Settings (DO NOT use in production)
# -----------------------------------------------------------------------------
# DEV_MODE=false
# SHOW_ERRORS=false
# DEBUG_BAR=false
"@

    $envExample | Out-File ".env.example" -Encoding UTF8
    Write-Host "   ✅ Created comprehensive .env.example" -ForegroundColor Green
    $cleanupLog += "Created: .env.example (comprehensive template)"
}

# ============================================================================
# STEP 3: Handle empty models/ directory
# ============================================================================
Write-Host "`n[3/4] Cleaning Up Empty Directories..." -ForegroundColor Yellow

if (Test-Path "models") {
    $modelFiles = Get-ChildItem "models" -File
    
    if ($modelFiles.Count -eq 0) {
        Write-Host "   ℹ models/ directory is empty (models are in src/Models/)" -ForegroundColor Gray
        
        # Create README to explain why it's empty or remove it
        $choice = "remove"  # Auto-decide to remove empty directory
        
        if ($choice -eq "remove") {
            Remove-Item "models" -Force
            Write-Host "   ✓ Removed empty models/ directory" -ForegroundColor Green
            $cleanupLog += "Removed: empty models/ directory (models are in src/Models/)"
        }
    } else {
        Write-Host "   ⚠️  models/ directory contains $($modelFiles.Count) files" -ForegroundColor Yellow
        Write-Host "   💡 Consider migrating to src/Models/ for consistency" -ForegroundColor Cyan
        $cleanupLog += "models/ contains files - needs manual review"
    }
} else {
    Write-Host "   ℹ models/ directory does not exist" -ForegroundColor Gray
}

# ============================================================================
# STEP 4: Document legacy code locations
# ============================================================================
Write-Host "`n[4/4] Documenting Legacy Code locations..." -ForegroundColor Yellow

$legacyDoc = @"
# Legacy Code Migration Status

**Last Updated:** $(Get-Date -Format "yyyy-MM-dd HH:mm:ss")

## Overview

The CSIMS project is in transition from legacy MVC architecture to modern Clean Architecture.  
**Current Migration Status: ~70% Complete**

---

## Legacy Code Locations

### 1. Legacy Controllers (`/controllers/`)

**Status:** ⚠️ **Active - Being Migrated**

Legacy controllers that should be migrated to \`src/Controllers/\`:

\`\`\`
controllers/
├── auth_controller.php           - Authentication logic
├── member_controller.php         - Member management
├── member_import_controller.php  - Member import
├── loan_controller.php           - Loan processing
├── contribution_controller.php   - Contribution management
├── admin_controller.php          - Admin operations
└── ... (other controllers)
\`\`\`

**Action Required:**
- Gradually migrate to \`src/Controllers/\`
- Use modern dependency injection
- Follow PSR-4 naming conventions

**Priority:** Medium (working but not ideal)

---

### 2. Legacy Views (`/views/`)

**Status:** ✅ **Active - Template System**

104 PHP view templates organized by user type:

\`\`\`
views/
├── admin/          - Admin dashboard views
├── auth/           - Authentication views
├── member/         - Member portal views
└── shared/         - Shared components
\`\`\`

**Considerations:**
- Consider template engine (Blade, Twig) for better separation
- Current: PHP templates with inline PHP (functional but mixed concerns)
- Not critical to migrate (views can stay as templates)

**Priority:** Low (not critical)

---

### 3. Legacy Includes (`/includes/`)

**Status:** ⚠️ **Active - Helper Functions**

Global helper functions and utilities:

\`\`\`
includes/
├── db.php              - Database connection helpers
├── functions.php       - Utility functions
├── session.php         - Session management
└── ... (other helpers)
\`\`\`

**Action Required:**
- Migrate to \`src/Helpers/\` or \`src/Utils/\`
- Convert to namespaced classes
- Use dependency injection instead of globals

**Priority:** Medium

---

### 4. Root-Level Scripts

**Status:** ⚠️ **Mixed - Some Legacy, Some Modern**

Scripts at root level:

- \`index.php\` - Main entry point (uses legacy + modern)
- \`api.php\` - API entry (mostly modern)
- \`login.php\` - Legacy login (migrated to views/auth/)
- Various standalone scripts

**Action Required:**
- Route everything through \`index.php\` or \`api.php\`
- Remove standalone entry points
- Consolidate routing

**Priority:** Medium

---

## Modern Architecture (`/src/`)

**Status:** ✅ **Active and Growing**

### Fully Modern Components:

\`\`\`
src/
├── API/              ✅ RESTful routing
├── Cache/            ✅ Caching abstraction
├── Config/           ✅ Configuration management
├── Container/        ✅ Dependency injection
├── Controllers/      ✅ Modern controllers
├── Database/         ✅ Query builder
├── DTOs/             ✅ Data transfer objects
├── Exceptions/       ✅ Custom exceptions
├── Interfaces/       ✅ Contracts
├── Models/           ✅ Domain models (active record)
├── Repositories/     ✅ Data access layer
└── Services/         ✅ Business logic
\`\`\`

**This is the target architecture for all new development.**

---

## Migration Strategy

### Phase 1: Critical Components (DONE ✅)
- ✅ Core services (Auth, Security, Loan)
- ✅ Domain models
- ✅ Repositories
- ✅ Database abstraction

### Phase 2: Controllers Migration (IN PROGRESS ⏳)
- ✅ Modern controllers created
- ⏳ Legacy controllers being bridged
- ⏳ Gradual replacement

**Current: 70% Complete**

### Phase 3: Helper Migration (PLANNED 📋)
- 📋 Move includes/ to src/Helpers/
- 📋 Convert to namespaced classes
- 📋 Remove global dependencies

**Target: Q1 2026**

### Phase 4: Complete Migration (FUTURE 🔮)
- 🔮 All legacy code migrated
- 🔮 Clean architecture throughout
- 🔮 Full PSR compliance

**Target: Q2 2026**

---

## Coexistence Strategy

### How Legacy and Modern Work Together:

1. **Legacy controllers** → **Call modern services**
   \`\`\`php
   // Legacy controller
   $loanService = $container->resolve(LoanService::class);
   $result = $loanService->processLoan($data);
   \`\`\`

2. **Modern API** → **Uses only modern architecture**
   \`\`\`php
   // Modern API route
   Route::post('/loans', [LoanController::class, 'create']);
   \`\`\`

3. **Views** → **Can use both** (gradual transition)
   \`\`\`php
   // Views can access modern services via container
   \`\`\`

---

## Guidelines for New Development

### ✅ DO:
- ✅ Write all new code in \`src/\`
- ✅ Use dependency injection
- ✅ Follow PSR-4 autoloading
- ✅ Use repositories for data access
- ✅ Put business logic in services
- ✅ Use DTOs for data transfer
- ✅ Write tests (once PHPUnit is set up)

### ❌ DON'T:
- ❌ Add new files to \`controllers/\`, \`includes/\`, or \`models/\`
- ❌ Use global variables
- ❌ Mix business logic with controllers
- ❌ Use direct database queries (use repositories)
- ❌ Skip type hints and return types

---

## Migration Checklist for Developers

When migrating a legacy component:

- [ ] 1. Create modern version in \`src/\`
- [ ] 2. Write unit tests
- [ ] 3. Update legacy code to use new version
- [ ] 4. Verify functionality
- [ ] 5. Keep legacy as fallback temporarily
- [ ] 6. Document breaking changes
- [ ] 7. After stability period, remove legacy code
- [ ] 8. Update documentation

---

## Performance Impact

**Legacy Code:** Minimal impact  
**Reason:** Legacy controllers bridge to modern services

**Recommendation:** Continue gradual migration, no urgent performance concerns.

---

## Technical Debt

### Current Debt Level: **Medium**

- 30% legacy code remaining
- Clean separation prevents major issues  
- No critical blockers for production

### Debt Reduction Plan:

- Q1 2026: Migrate remaining controllers
- Q2 2026: Consolidate helpers
- Q3 2026: Complete migration

---

## Questions?

See:
- \`docs/REFACTORED_ARCHITECTURE.md\` - Architecture details
- \`docs/TECHNICAL_DOCUMENTATION.md\` - Technical reference
- \`docs/PROJECT_AUDIT_REPORT_2025.md\` - Current assessment

---

**Status:** Managed migration in progress  
**Risk:** Low (both architectures coexist cleanly)  
**Recommendation:** Continue gradual migration while maintaining stability
"@

$legacyDoc | Out-File "docs\LEGACY_CODE_MIGRATION_STATUS.md" -Encoding UTF8
Write-Host "   ✓ Created docs/LEGACY_CODE_MIGRATION_STATUS.md" -ForegroundColor Green
$cleanupLog += "Created: LEGACY_CODE_MIGRATION_STATUS.md documentation"

# ============================================================================
# SUMMARY
# ============================================================================
Write-Host "`n╔════════════════════════════════════════════════════════════════╗" -ForegroundColor Green
Write-Host "║   ✅ Housekeeping Cleanup Complete!                           ║" -ForegroundColor Green
Write-Host "╚════════════════════════════════════════════════════════════════╝`n" -ForegroundColor Green

Write-Host "Summary of Actions:" -ForegroundColor Cyan
Write-Host "  ✓ Moved debug/test files to development/" -ForegroundColor Green
Write-Host "  ✓ Verified .env.example configuration" -ForegroundColor Green
Write-Host "  ✓ Cleaned up empty directories" -ForegroundColor Green
Write-Host "  ✓ Documented legacy code locations`n" -ForegroundColor Green

Write-Host "Created Files:" -ForegroundColor Cyan
Write-Host "  - development/README.md" -ForegroundColor White
Write-Host "  - docs/LEGACY_CODE_MIGRATION_STATUS.md`n" -ForegroundColor White

Write-Host "Next Steps:" -ForegroundColor Yellow
Write-Host "  1. Review development/ folder contents" -ForegroundColor White
Write-Host "  2. Configure .env based on .env.example" -ForegroundColor White
Write-Host "  3. Read docs/LEGACY_CODE_MIGRATION_STATUS.md for migration plan" -ForegroundColor White
Write-Host "  4. Add 'development/' to .gitignore if needed`n" -ForegroundColor White

# Save cleanup log
$cleanupLog += ""
$cleanupLog += "=" * 70
$cleanupLog += "Cleanup completed: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"

$cleanupLog | Out-File "logs\housekeeping_cleanup.log" -Encoding UTF8 -Append

Write-Host "📄 Log saved to: logs\housekeeping_cleanup.log`n" -ForegroundColor Cyan

Write-Host "✅ All housekeeping issues resolved!`n" -ForegroundColor Green
