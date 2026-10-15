$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$extensionRoot = Join-Path $projectRoot 'extensions/drives'
$downloadRoot = Join-Path $projectRoot 'public/downloads'
New-Item -ItemType Directory -Force -Path $downloadRoot | Out-Null
$sourceFiles = Get-ChildItem -LiteralPath $extensionRoot -File | Select-Object -ExpandProperty FullName
Compress-Archive -LiteralPath $sourceFiles -DestinationPath (Join-Path $downloadRoot 'cuistot-drives.zip') -Force
Write-Output 'Extension empaquetée : public/downloads/cuistot-drives.zip'
