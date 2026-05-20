# DeployFirefoxLogger.ps1
# Run as Administrator on each workstation
$ErrorActionPreference = "Stop"

$ServerBase = "http://172.16.88.69:4444"

# ----- 1. Create Firefox distribution folder and write policies.json -----
$FFPath = "$env:ProgramFiles\Mozilla Firefox"
if (!(Test-Path $FFPath)) {
    Write-Host "Firefox not found at $FFPath" -ForegroundColor Red
    exit 1
}

$DistFolder = Join-Path $FFPath "distribution"
New-Item -Path $DistFolder -ItemType Directory -Force | Out-Null

$PolicyFile = Join-Path $DistFolder "policies.json"
$PolicyJson = @"
{
  "policies": {
    "ExtensionSettings": {
      "firefox@tampermonkey.net": {
        "installation_mode": "force_installed",
        "install_url": "$ServerBase/static/tampermonkey.xpi"
      }
    }
  }
}
"@
Set-Content -Path $PolicyFile -Value $PolicyJson -Encoding UTF8
Write-Host "policies.json created" -ForegroundColor Green

# ----- 2. Create desktop shortcut to one-click install page -----
$ShortcutPath = [Environment]::GetFolderPath("Desktop") + "\Install_EMR_Logger.url"
$ShortcutContent = @"
[InternetShortcut]
URL=$ServerBase/static/install.html
"@
Set-Content -Path $ShortcutPath -Value $ShortcutContent -Encoding ASCII
Write-Host "Desktop shortcut created" -ForegroundColor Green