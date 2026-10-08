# Vérification du code des plugins TKD Claira avant déploiement (06/10/2026).
#
# Usage (depuis ce dossier « outils ») :
#   .\verifier.ps1                 tout vérifier
#   .\verifier.ps1 -Rapide         seulement la syntaxe PHP et JavaScript (quelques secondes)
#
# Prérequis : PHP 8.4, Node.js et les dépendances installées par « composer install » ici.
# Rien de ce dossier n'est jamais copié sur le site.

param( [switch] $Rapide )

$ErrorActionPreference = 'Continue'
$env:Path   = [Environment]::GetEnvironmentVariable('Path', 'Machine') + ';' + [Environment]::GetEnvironmentVariable('Path', 'User')
$racine     = Split-Path $PSScriptRoot -Parent
$plugins    = 'sp_build', 'sp-compta', 'tkd-cotisations', 'claira-tkd-parcours' | ForEach-Object { Join-Path $racine $_ }
$exclus     = '\\(vendor|node_modules|tests)\\'
$problemes  = 0

function Titre( $t ) { Write-Host "`n=== $t ===" -ForegroundColor Cyan }

Titre 'Syntaxe PHP (php -l)'
$fichiers = Get-ChildItem -Recurse -Filter *.php -Path $plugins | Where-Object { $_.FullName -notmatch $exclus }
$ko = 0
foreach ( $f in $fichiers ) {
    $sortie = php -l $f.FullName 2>&1
    if ( $LASTEXITCODE -ne 0 ) { $ko++; Write-Host ( $sortie | Out-String ) -ForegroundColor Red }
}
Write-Host "$($fichiers.Count) fichiers, $ko en erreur"
$problemes += $ko

Titre 'Syntaxe JavaScript (node --check)'
$fichiers = Get-ChildItem -Recurse -Filter *.js -Path $plugins | Where-Object { $_.FullName -notmatch $exclus -and $_.Name -notlike '*.min.js' }
$ko = 0
foreach ( $f in $fichiers ) {
    $sortie = node --check $f.FullName 2>&1
    if ( $LASTEXITCODE -ne 0 ) { $ko++; Write-Host ( $sortie | Out-String ) -ForegroundColor Red }
}
Write-Host "$($fichiers.Count) fichiers, $ko en erreur"
$problemes += $ko

if ( -not $Rapide ) {
    Push-Location $PSScriptRoot
    Titre 'PHPStan (erreurs franches : code inexistant, variables non définies…)'
    & .\vendor\bin\phpstan.bat analyse --no-progress --memory-limit=1G
    if ( $LASTEXITCODE -ne 0 ) { $problemes++ }

    Titre 'Tests automatiques des calculs (verdict des passages, IK, catégories d''âge) — tests\'
    & .\vendor\bin\phpunit.bat --no-progress
    if ( $LASTEXITCODE -ne 0 ) { $problemes++ }

    Titre 'PHP_CodeSniffer (sécurité + compatibilité PHP 8.4) — résumé par fichier'
    & .\vendor\bin\phpcs.bat --report=summary
    if ( $LASTEXITCODE -ne 0 ) { $problemes++ }
    Pop-Location
}

Titre 'Bilan'
if ( $problemes -eq 0 ) { Write-Host 'Aucun problème détecté.' -ForegroundColor Green }
else { Write-Host 'Des problèmes ont été détectés (détail ci-dessus).' -ForegroundColor Yellow }
