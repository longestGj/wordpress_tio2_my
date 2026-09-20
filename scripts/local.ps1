param([ValidateSet('Start','Status','Stop')][string]$Action='Status')
$ErrorActionPreference='Stop'
$projectRoot=Split-Path $PSScriptRoot -Parent
Set-Location -LiteralPath $projectRoot
if($Action -eq 'Start') {
  if(!(Test-Path -LiteralPath '.env')) {
    $randomSecret={ -join ((1..48)|ForEach-Object { [char](Get-Random -InputObject (48..57 + 65..90 + 97..122)) }) }
    @("DB_PASSWORD=$(& $randomSecret)","DB_ROOT_PASSWORD=$(& $randomSecret)","D32_ADMIN_PASSWORD=$(& $randomSecret)") | Set-Content -LiteralPath '.env' -Encoding utf8
  }
  docker compose up -d db wordpress
  if($LASTEXITCODE -ne 0) { throw 'Local stack did not start' }
} elseif($Action -eq 'Stop') { docker compose stop }
else { docker compose ps }
