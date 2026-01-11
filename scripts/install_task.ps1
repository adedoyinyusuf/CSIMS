$TaskName = "CSIMS_Auto_Savings"
$PhpPath = "C:\xampp\php\php.exe"
$ScriptPath = "C:\xampp\htdocs\CSIMS\scripts\auto_post_monthly_savings.php"
$LogPath = "C:\xampp\htdocs\CSIMS\logs\scheduler.log"

# Verify paths
if (-not (Test-Path $PhpPath)) {
    Write-Error "PHP executable not found at $PhpPath"
    exit 1
}
if (-not (Test-Path $ScriptPath)) {
    Write-Error "Script not found at $ScriptPath"
    exit 1
}

# Create Log Directory if not exists
$LogDir = Split-Path $LogPath
if (-not (Test-Path $LogDir)) {
    New-Item -ItemType Directory -Force -Path $LogDir | Out-Null
}

# Define Action
# valid php path and script path, passing 'confirm' argument
# We redirect stdout/stderr to a log file for debugging
$Action = New-ScheduledTaskAction -Execute $PhpPath -Argument "`"$ScriptPath`" confirm >> `"$LogPath`" 2>&1"

# Define Trigger (Monthly on the 28th at 00:00)
$Trigger = New-ScheduledTaskTrigger -Monthly -Days 28 -At 12:00am

# Define Settings (Run whether user is logged in or not is complex without password, 
# so we stick to standard user session or system if run as admin. 
# For dev environment 'Run only when user is logged on' is safer/easier)
$Settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable

# Check if task exists and unregister
if (Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue) {
    Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false
    Write-Host "Replaced existing task: $TaskName"
}

# Register Task
# Note: To run "whether user is logged on or not" requires credentials.
# We will create it to run in the current user's security context.
Register-ScheduledTask -Action $Action -Trigger $Trigger -Settings $Settings -TaskName $TaskName -Description "Runs CSIMS monthly savings posting on the 28th."

Write-Host "Successfully created Windows Scheduled Task: $TaskName"
Write-Host "Next run time: $((Get-ScheduledTaskInfo -TaskName $TaskName).NextRunTime)"
