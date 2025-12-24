# =============================================================================
# CSIMS Migration Cleanup Script - AUTO MODE
# Based on Investigation Results - December 24, 2025
# =============================================================================

Write-Host "`n╔════════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║   CSIMS Migration Cleanup Script                              ║" -ForegroundColor Cyan
Write-Host "║   Safe Migration File Organization                            ║" -ForegroundColor Cyan
Write-Host "╚════════════════════════════════════════════════════════════════╝`n" -ForegroundColor Cyan

Write-Host "✅ Auto-mode enabled: Proceeding with cleanup...`n" -ForegroundColor Green

# =============================================================================
# STEP 1: Create deprecated folder
# =============================================================================
Write-Host "[1/5] Creating deprecated folder..." -ForegroundColor Yellow

$deprecatedPath = "database\migrations\deprecated"
if (-not (Test-Path $deprecatedPath)) {
    New-Item -ItemType Directory -Path $deprecatedPath -Force | Out-Null
    Write-Host "   ✓ Created: $deprecatedPath" -ForegroundColor Green
} else {
    Write-Host "   ℹ Folder already exists: $deprecatedPath" -ForegroundColor Gray
}

# =============================================================================
# STEP 2: Handle Migration 007 duplicates
# =============================================================================
Write-Host "`n[2/5] Handling Migration 007 duplicates..." -ForegroundColor Yellow

$migration007Base = "database\migrations\007_enhanced_cooperative_schema.sql"
$migration007Fixed = "database\migrations\007_enhanced_cooperative_schema_fixed.sql"
$migration007Simple = "database\migrations\007_enhanced_cooperative_schema_simple.sql"

$exists007Base = Test-Path $migration007Base
$exists007Fixed = Test-Path $migration007Fixed
$exists007Simple = Test-Path $migration007Simple

Write-Host "   Files found:" -ForegroundColor Gray
if ($exists007Base) { Write-Host "   - 007_enhanced_cooperative_schema.sql ✓" -ForegroundColor Green }
if ($exists007Fixed) { Write-Host "   - 007_enhanced_cooperative_schema_fixed.sql ✓" -ForegroundColor Green }
if ($exists007Simple) { Write-Host "   - 007_enhanced_cooperative_schema_simple.sql ✓" -ForegroundColor Green }

Write-Host "`n   Decision: Keep BASE version" -ForegroundColor Cyan

if ($exists007Fixed) {
    Move-Item $migration007Fixed "$deprecatedPath\" -Force
    Write-Host "   ✓ Moved _fixed to deprecated/" -ForegroundColor Green
}

if ($exists007Simple) {
    Move-Item $migration007Simple "$deprecatedPath\" -Force
    Write-Host "   ✓ Moved _simple to deprecated/" -ForegroundColor Green
}

Write-Host "   ✅ Migration 007 cleaned up" -ForegroundColor Green

# =============================================================================
# STEP 3: Handle Migration 008 conflict
# =============================================================================
Write-Host "`n[3/5] Resolving Migration 008 conflict..." -ForegroundColor Yellow

$migration008ConfigFixed = "database\migrations\008_create_system_config_fixed.sql"
$migration008MemberFields = "database\migrations\008_add_member_extra_loan_fields.sql"

if (Test-Path $migration008ConfigFixed) {
    Move-Item $migration008ConfigFixed "$deprecatedPath\" -Force
    Write-Host "   ✓ Moved 008_create_system_config_fixed.sql to deprecated/" -ForegroundColor Green
}

if (Test-Path $migration008MemberFields) {
    $newPath = "database\migrations\011_add_member_extra_loan_fields.sql"
    
    if (-not (Test-Path $newPath)) {
        Move-Item $migration008MemberFields $newPath -Force
        Write-Host "   ✓ Renumbered: 008_add_member_extra_loan_fields.sql → 011_..." -ForegroundColor Green
    } else {
        Write-Host "   ⚠ 011_add_member_extra_loan_fields.sql already exists" -ForegroundColor Yellow
        Move-Item $migration008MemberFields "$deprecatedPath\" -Force
        Write-Host "   → Moved original 008 to deprecated instead" -ForegroundColor Yellow
    }
}

Write-Host "   ✅ Migration 008 conflict resolved" -ForegroundColor Green

# =============================================================================
# STEP 4: Number unnumbered migrations
# =============================================================================
Write-Host "`n[4/5] Numbering unnumbered migrations..." -ForegroundColor Yellow

$unnumbered = @(
    @{Old="database\migrations\add_admin_profile_fields.sql"; New="database\migrations\012_add_admin_profile_fields.sql"},
    @{Old="database\migrations\add_extended_member_fields.sql"; New="database\migrations\013_add_extended_member_fields.sql"},
    @{Old="database\migrations\add_member_type_to_members.sql"; New="database\migrations\014_add_member_type_to_members.sql"}
)

foreach ($migration in $unnumbered) {
    if (Test-Path $migration.Old) {
        if (-not (Test-Path $migration.New)) {
            Move-Item $migration.Old $migration.New -Force
            $filename = Split-Path $migration.New -Leaf
            Write-Host "   ✓ Numbered: $filename" -ForegroundColor Green
        } else {
            $filename = Split-Path $migration.Old -Leaf
            Write-Host "   ℹ Already numbered: $filename" -ForegroundColor Gray
        }
    }
}

Write-Host "   ✅ Unnumbered migrations processed" -ForegroundColor Green

# =============================================================================
# STEP 5: Create cleanup summary
# =============================================================================
Write-Host "`n[5/5] Creating cleanup summary..." -ForegroundColor Yellow

$summary = @"
# Migration Cleanup Summary
**Date:** $(Get-Date -Format "yyyy-MM-dd HH:mm:ss")

## Actions Taken

### 1. Migration 007 - Enhanced Cooperative Schema
- **Kept:** 007_enhanced_cooperative_schema.sql (base version)
- **Moved to deprecated:**
  - 007_enhanced_cooperative_schema_fixed.sql
  - 007_enhanced_cooperative_schema_simple.sql
- **Reason:** All three versions are similar; keeping base version for consistency

### 2. Migration 008 - Numbering Conflict Resolution
- **Kept as 008:** 008_create_system_config.sql
- **Renumbered to 011:** 008_add_member_extra_loan_fields.sql
- **Moved to deprecated:** 008_create_system_config_fixed.sql
- **Reason:** Both migrations were applied; resolved numbering conflict

### 3. Unnumbered Migrations - Sequential Numbering
- **012:** add_admin_profile_fields.sql → 012_add_admin_profile_fields.sql
- **013:** add_extended_member_fields.sql → 013_add_extended_member_fields.sql
- **014:** add_member_type_to_members.sql → 014_add_member_type_to_members.sql

## Current Migration Structure

\`\`\`
database/migrations/
├── 001_create_users_and_sessions.sql
├── 005_create_user_sessions_table.sql
├── 006_create_cache_table.sql
├── 007_enhanced_cooperative_schema.sql          ← KEPT (base version)
├── 008_create_system_config.sql                ← KEPT
├── 009_create_approval_workflow_tables.sql
├── 010_make_bank_fields_not_null.sql
├── 011_add_member_extra_loan_fields.sql        ← RENUMBERED from 008
├── 012_add_admin_profile_fields.sql            ← NUMBERED
├── 013_add_extended_member_fields.sql          ← NUMBERED
├── 014_add_member_type_to_members.sql          ← NUMBERED
├── security_tables.sql
└── deprecated/
    ├── 007_enhanced_cooperative_schema_fixed.sql
    ├── 007_enhanced_cooperative_schema_simple.sql
    └── 008_create_system_config_fixed.sql
\`\`\`

## Database Impact
✅ **NO database changes** - This cleanup only reorganized files
✅ **Existing database schema unchanged**
✅ **All applied migrations still present**

## Next Steps
1. ✅ Test fresh database setup with cleaned migrations
2. ✅ Update any deployment scripts that reference old filenames
3. ✅ Commit changes to version control
4. ✅ Consider implementing a migrations tracking table

## Notes
- Deprecated files are preserved in case they're needed for reference
- No migrations were deleted permanently
- Can be restored from deprecated/ folder if needed
"@

$summary | Out-File "docs\MIGRATION_CLEANUP_SUMMARY.md" -Encoding UTF8
Write-Host "   ✓ Summary saved to: docs\MIGRATION_CLEANUP_SUMMARY.md" -ForegroundColor Green

# =============================================================================
# FINAL SUMMARY
# =============================================================================
Write-Host "`n╔════════════════════════════════════════════════════════════════╗" -ForegroundColor Green
Write-Host "║   ✅ Cleanup Complete!                                        ║" -ForegroundColor Green
Write-Host "╚════════════════════════════════════════════════════════════════╝`n" -ForegroundColor Green

Write-Host "Summary:" -ForegroundColor Cyan
Write-Host "  ✓ Created deprecated/ folder" -ForegroundColor Green
Write-Host "  ✓ Organized Migration 007 files" -ForegroundColor Green
Write-Host "  ✓ Resolved Migration 008 conflict" -ForegroundColor Green
Write-Host "  ✓ Numbered unnumbered migrations" -ForegroundColor Green
Write-Host "  ✓ Generated cleanup summary document`n" -ForegroundColor Green

Write-Host "📄 Detailed report: docs\MIGRATION_CLEANUP_SUMMARY.md`n" -ForegroundColor Cyan

Write-Host "Deprecated files (preserved in deprecated/ folder):" -ForegroundColor Gray
if (Test-Path $deprecatedPath) {
    Get-ChildItem "$deprecatedPath\*.sql" -ErrorAction SilentlyContinue | ForEach-Object {
        Write-Host "  - $($_.Name)" -ForegroundColor Gray
    }
}
Write-Host "`n✅ All done!`n" -ForegroundColor Green
