$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$phpCommand = Get-Command php -ErrorAction SilentlyContinue
$phpExecutable = if ($phpCommand) { $phpCommand.Source } else { 'D:\Logiciel\laragon\bin\php\php-8.4.14-nts-Win32-vs17-x64\php.exe' }
if (-not (Test-Path -LiteralPath $phpExecutable)) { throw 'PHP est introuvable. Ajoutez votre PHP Laragon au PATH.' }
Push-Location $projectRoot
try {
    & $phpExecutable artisan inertia:check-ssr 2>$null
    if ($LASTEXITCODE -eq 0) { Write-Output 'Le rendu SSR est déjà actif.'; exit 0 }
    $logs = Join-Path $projectRoot 'storage/app/private'
    New-Item -ItemType Directory -Force -Path $logs | Out-Null
    $process = Start-Process -FilePath $phpExecutable -ArgumentList @('artisan', 'inertia:start-ssr') -WorkingDirectory $projectRoot -WindowStyle Hidden -RedirectStandardOutput (Join-Path $logs 'ssr.log') -RedirectStandardError (Join-Path $logs 'ssr-error.log') -PassThru
    Set-Content -LiteralPath (Join-Path $logs 'ssr-process.txt') -Value $process.Id
    Write-Output 'Le rendu SSR démarre en arrière-plan. Laragon sert https://idee.test.'
} finally { Pop-Location }
