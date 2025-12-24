@echo off
echo ========================================
echo Migration 007 File Comparison
echo ========================================
echo.

echo FILE 1: 007_enhanced_cooperative_schema.sql
echo ----------------------------------------
findstr /I "CREATE TABLE" database\migrations\007_enhanced_cooperative_schema.sql | findstr /I "IF NOT EXISTS" > temp_007_base.txt
type temp_007_base.txt
echo.
echo Table count:
find /C "CREATE TABLE" temp_007_base.txt
echo.

echo FILE 2: 007_enhanced_cooperative_schema_fixed.sql
echo ----------------------------------------
findstr /I "CREATE TABLE" database\migrations\007_enhanced_cooperative_schema_fixed.sql | findstr /I "IF NOT EXISTS" > temp_007_fixed.txt
type temp_007_fixed.txt
echo.
echo Table count:
find /C "CREATE TABLE" temp_007_fixed.txt
echo.

echo FILE 3: 007_enhanced_cooperative_schema_simple.sql
echo ----------------------------------------
findstr /I "CREATE TABLE" database\migrations\007_enhanced_cooperative_schema_simple.sql | findstr /I "IF NOT EXISTS" > temp_007_simple.txt
type temp_007_simple.txt
echo.
echo Table count:
find /C "CREATE TABLE" temp_007_simple.txt
echo.

echo ========================================
echo Searching for loan_types specifically:
echo ========================================
echo.

echo In BASE file:
findstr /I "loan_types" database\migrations\007_enhanced_cooperative_schema.sql | findstr /I "CREATE TABLE"
echo.

echo In FIXED file:
findstr /I "loan_types" database\migrations\007_enhanced_cooperative_schema_fixed.sql | findstr /I "CREATE TABLE"
echo.

echo In SIMPLE file:
findstr /I "loan_types" database\migrations\007_enhanced_cooperative_schema_simple.sql | findstr /I "CREATE TABLE"
echo.

del temp_007_*.txt
pause
