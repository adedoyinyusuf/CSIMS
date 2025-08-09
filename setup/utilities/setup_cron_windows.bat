@echo off
REM Windows Cron Job Setup for CSIMS Notification System
REM This script helps set up Windows Task Scheduler for automated notifications

echo ========================================
echo CSIMS Notification System - Windows Setup
echo ========================================
echo.

REM Get current directory
set SCRIPT_DIR=%~dp0
set PHP_SCRIPT=%SCRIPT_DIR%cron\notification_trigger_runner.php

REM Find PHP executable
set PHP_PATH=
for %%i in (php.exe) do set PHP_PATH=%%~$PATH:i

if "%PHP_PATH%"=="" (
    echo ERROR: PHP not found in PATH
    echo Please install PHP or add it to your system PATH
    echo Common PHP locations:
    echo   - C:\xampp\php\php.exe
    echo   - C:\wamp\bin\php\php8.x\php.exe
    echo   - C:\php\php.exe
    pause
    exit /b 1
)

echo Found PHP at: %PHP_PATH%
echo Script location: %PHP_SCRIPT%
echo.

REM Test PHP script
echo Testing PHP script...
"%PHP_PATH%" -f "%PHP_SCRIPT%" --test
if errorlevel 1 (
    echo ERROR: PHP script test failed
    pause
    exit /b 1
)

echo PHP script test successful!
echo.

echo Creating Windows Task Scheduler task...
echo.

REM Create the scheduled task
schtasks /create /tn "CSIMS Notification System" /tr "\"%PHP_PATH%\" \"%PHP_SCRIPT%\"" /sc minute /mo 5 /f

if errorlevel 1 (
    echo ERROR: Failed to create scheduled task
    echo Please run this script as Administrator
    pause
    exit /b 1
)

echo.
echo ========================================
echo SUCCESS: Scheduled task created!
echo ========================================
echo.
echo Task Name: CSIMS Notification System
echo Schedule: Every 5 minutes
echo Command: "%PHP_PATH%" "%PHP_SCRIPT%"
echo.
echo To view the task:
echo   schtasks /query /tn "CSIMS Notification System"
echo.
echo To delete the task:
echo   schtasks /delete /tn "CSIMS Notification System" /f
echo.
echo To run the task manually:
echo   schtasks /run /tn "CSIMS Notification System"
echo.
echo Log files will be created in: %SCRIPT_DIR%logs\
echo.
echo Press any key to exit...
pause >nul