# Migration 007 Comparison Script
# Analyzes which version matches the current database

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "Migration 007 File Comparison Analysis" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

$files = @(
    "database\migrations\007_enhanced_cooperative_schema.sql",
    "database\migrations\007_enhanced_cooperative_schema_fixed.sql",
    "database\migrations\007_enhanced_cooperative_schema_simple.sql"
)

$results = @{}

foreach ($file in $files) {
    if (Test-Path $file) {
        $filename = Split-Path $file -Leaf
        Write-Host "Analyzing: $filename" -ForegroundColor Yellow
       
        # Get file size
        $size = (Get-Item $file).Length
        Write-Host "  File Size: $size bytes" -ForegroundColor Gray
        
        # Search for loan_types table creation
        $hasLoanTypes = Select-String -Path $file -Pattern "CREATE TABLE.*loan_types" -Quiet
        
        # Count CREATE TABLE statements
        $tableCount = (Select-String -Path $file -Pattern "CREATE TABLE" | Measure-Object).Count
        
        Write-Host "  Tables Created: $tableCount" -ForegroundColor Gray
        Write-Host "  Creates loan_types: $(if ($hasLoanTypes) { 'YES' } else { 'NO' })" -ForegroundColor $(if ($hasLoanTypes) { 'Red' } else { 'Green' })
        
        # Check for specific expected tables
        $hasWorkflowApprovals = Select-String -Path $file -Pattern "workflow_approvals" -Quiet
        $hasLoanGuarantors = Select-String -Path $file -Pattern "loan_guarantors" -Quiet
        $hasSavingsAccounts = Select-String -Path $file -Pattern "savings_accounts" -Quiet
        $hasMemberTypes = Select-String -Path $file -Pattern "member_types" -Quiet
        
        Write-Host "  Has workflow_approvals: $(if ($hasWorkflowApprovals) { 'YES' } else { 'NO' })" -ForegroundColor Gray
        Write-Host "  Has loan_guarantors: $(if ($hasLoanGuarantors) { 'YES' } else { 'NO' })" -ForegroundColor Gray
        Write-Host "  Has savings_accounts: $(if ($hasSavingsAccounts) { 'YES' } else { 'NO' })" -ForegroundColor Gray
        Write-Host "  Has member_types: $(if ($hasMemberTypes) { 'YES' } else { 'NO' })" -ForegroundColor Gray
        
        $results[$filename] = @{
            Size = $size
            TableCount = $tableCount
            HasLoanTypes = $hasLoanTypes
            MatchesDB = ($hasWorkflowApprovals -and $hasLoanGuarantors -and $hasSavingsAccounts -and $hasMemberTypes -and -not $hasLoanTypes)
        }
        
        Write-Host ""
    } else {
        Write-Host "File not found: $file" -ForegroundColor Red
    }
}

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "RECOMMENDATION" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

Write-Host "Current Database State (from investigation):" -ForegroundColor Yellow
Write-Host "  ✓ workflow_approvals - EXISTS" -ForegroundColor Green
Write-Host "  ✓ loan_guarantors - EXISTS" -ForegroundColor Green
Write-Host "  ✓ savings_accounts - EXISTS" -ForegroundColor Green
Write-Host "  ✓ member_types - EXISTS" -ForegroundColor Green
Write-Host "  ✗ loan_types - MISSING`n" -ForegroundColor Red

$matchFound = $false
foreach ($file in $results.Keys) {
    if ($results[$file].MatchesDB) {
        Write-Host "✅ MATCH FOUND: $file" -ForegroundColor Green
        Write-Host "   This file matches your current database (creates 4/5 tables, no loan_types)" -ForegroundColor Green
        Write-Host "`n   ACTION: KEEP this file" -ForegroundColor White -BackgroundColor Green
        $matchFound = $true
    } elseif ($results[$file].HasLoanTypes) {
        Write-Host "❌ NO MATCH: $file" -ForegroundColor Red
        Write-Host "   This file creates loan_types (not in current DB)" -ForegroundColor Red
        Write-Host "`n   ACTION: Move to deprecated/" -ForegroundColor White -BackgroundColor Red
    }
    Write-Host ""
}

if (-not $matchFound) {
    Write-Host "⚠️  WARNING: No exact match found." -ForegroundColor Yellow
    Write-Host "   Files may have been modified or database state is different.`n" -ForegroundColor Yellow
}

Write-Host "========================================`n" -ForegroundColor Cyan
