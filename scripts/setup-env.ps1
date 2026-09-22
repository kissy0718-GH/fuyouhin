$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$destination = Join-Path $projectRoot '.env'
if (Test-Path -LiteralPath $destination) { throw '.env already exists. Existing credentials were not changed.' }
function New-LocalPassword {
    $bytes = [byte[]]::new(32)
    [System.Security.Cryptography.RandomNumberGenerator]::Fill($bytes)
    return [Convert]::ToHexString($bytes)
}
$lines = @(
    ('DB_PASSWORD=' + (New-LocalPassword))
    ('DB_ROOT_PASSWORD=' + (New-LocalPassword))
)
[System.IO.File]::WriteAllLines($destination, $lines, [System.Text.UTF8Encoding]::new($false))
Write-Output 'Created .env with random local database credentials. Values are not printed.'
