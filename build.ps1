# Builds the install package with 7-Zip (never Compress-Archive):
#   dist\plg_system_cstemplatecacheclear_v<version>_<YYYYMMDD>_<HHMM>.zip
# The file list for the integrity check is written into the source folder first.

$ErrorActionPreference = 'Stop'

$root     = $PSScriptRoot
$src      = Join-Path $root 'plg_system_cstemplatecacheclear'
$manifest = [xml](Get-Content (Join-Path $src 'cstemplatecacheclear.xml') -Raw -Encoding UTF8)
$version  = $manifest.extension.version

$integrityTool = @(
    (Join-Path $root '.joomla-brain\tools\integrity\New-IntegrityList.ps1'),
    (Join-Path (Split-Path $root -Parent) 'Joomla-Brain\tools\integrity\New-IntegrityList.ps1')
) | Where-Object { Test-Path $_ } | Select-Object -First 1
if (-not $integrityTool) { throw 'Joomla-Brain tools/integrity not found.' }

& $integrityTool -ExtensionDir $src

$sevenZip = @(
    'C:\Program Files\7-Zip\7z.exe',
    'C:\Program Files (x86)\7-Zip\7z.exe'
) | Where-Object { Test-Path $_ } | Select-Object -First 1
if (-not $sevenZip) { throw '7-Zip not found.' }

$dist = Join-Path $root 'dist'
New-Item -ItemType Directory -Force -Path $dist | Out-Null

$zip = Join-Path $dist ('plg_system_cstemplatecacheclear_v{0}_{1}.zip' -f $version, (Get-Date -Format 'yyyyMMdd_HHmm'))
if (Test-Path $zip) { Remove-Item $zip }

Push-Location $src
try {
    & $sevenZip a -tzip -mx=9 $zip '*' | Out-Null
    if ($LASTEXITCODE -ne 0) { throw "7-Zip failed with exit code $LASTEXITCODE" }
} finally {
    Pop-Location
}

Write-Host "Built $zip" -ForegroundColor Green
