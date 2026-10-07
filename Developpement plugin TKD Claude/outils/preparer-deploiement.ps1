# Prépare un dossier de déploiement pour sp_build (07/10/2026).
#
# Copie les fichiers du plugin modifiés depuis une version donnée (celle qui est en ligne)
# dans un dossier DEPLOIEMENT_<date>, avec la même arborescence que sur le serveur, et écrit
# LISTE.txt : chemin sur le serveur, taille exacte en octets, empreinte SHA-256.
# Après l'envoi FTP (mode BINAIRE), comparer la taille de chaque fichier sur le serveur.
#
# Usage (depuis ce dossier « outils ») :
#   .\preparer-deploiement.ps1 -Depuis 0bd62ab      version actuellement en ligne
#
# Vérifie d'abord la syntaxe PHP / JavaScript des fichiers : rien n'est préparé en cas d'erreur.

param( [Parameter( Mandatory = $true )] [string] $Depuis )

$ErrorActionPreference = 'Stop'
$env:Path = [Environment]::GetEnvironmentVariable('Path', 'Machine') + ';' + [Environment]::GetEnvironmentVariable('Path', 'User')
$racine   = Split-Path $PSScriptRoot -Parent
$plugin   = Join-Path $racine 'sp_build'
$version  = ( git -C $plugin rev-parse --short HEAD ).Trim()
$fichiers = git -C $plugin diff --name-only --diff-filter=AM $Depuis HEAD | Where-Object { $_ -notmatch '\.md$' }
$supprimes = git -C $plugin diff --name-only --diff-filter=D $Depuis HEAD

if ( -not $fichiers ) { Write-Host "Aucun fichier modifié depuis $Depuis." -ForegroundColor Yellow; exit }
if ( git -C $plugin status --porcelain -- $fichiers ) {
    Write-Host 'Des fichiers à déployer ont des modifications non commitées : commitez d''abord.' -ForegroundColor Red; exit 1
}

# Vérification de syntaxe
$erreurs = 0
foreach ( $f in $fichiers ) {
    $p = Join-Path $plugin $f
    if ( $f -like '*.php' ) { php -l $p *> $null; if ( $LASTEXITCODE -ne 0 ) { $erreurs++; Write-Host "Erreur de syntaxe : $f" -ForegroundColor Red } }
    if ( $f -like '*.js' -and $f -notlike '*.min.js' ) { node --check $p *> $null; if ( $LASTEXITCODE -ne 0 ) { $erreurs++; Write-Host "Erreur de syntaxe : $f" -ForegroundColor Red } }
}
if ( $erreurs ) { Write-Host 'Rien n''a été préparé.' -ForegroundColor Red; exit 1 }

$dossier = Join-Path $racine ( 'DEPLOIEMENT_' + ( Get-Date -Format 'yyyy-MM-dd_HHmm' ) + "_sp_build_$version" )
New-Item -ItemType Directory -Path $dossier -Force | Out-Null
$lignes = @(
    "Déploiement sp_build — version $version (changements depuis $Depuis)",
    "Préparé le $( Get-Date -Format 'dd/MM/yyyy HH:mm' )",
    '',
    'Copier le contenu de ce dossier dans  wp-content/plugins/sp_build/  sur le serveur,',
    'en mode de transfert BINAIRE, en remplaçant les fichiers existants. Envoyer TOUS les fichiers.',
    'Après l''envoi, vérifier que la taille de chaque fichier sur le serveur est EXACTEMENT celle-ci :',
    ''
)
foreach ( $f in $fichiers ) {
    $src  = Join-Path $plugin $f
    $dest = Join-Path $dossier $f
    New-Item -ItemType Directory -Path ( Split-Path $dest -Parent ) -Force | Out-Null
    Copy-Item $src $dest
    $taille = ( Get-Item $src ).Length
    $hash   = ( Get-FileHash $src -Algorithm SHA256 ).Hash.Substring( 0, 16 ).ToLower()
    $lignes += ( '{0,-45} {1,10:N0} octets   sha256 {2}…' -f ( 'sp_build/' + $f ), $taille, $hash )
}
if ( $supprimes ) {
    $lignes += '', 'Fichiers à SUPPRIMER sur le serveur :'
    $supprimes | ForEach-Object { $lignes += '  sp_build/' + $_ }
}
$lignes += '', "Retour arrière : renvoyer les mêmes fichiers dans leur version $Depuis (git show ${Depuis}:<fichier>)."
$lignes | Set-Content -Path ( Join-Path $dossier 'LISTE.txt' ) -Encoding UTF8
$lignes | ForEach-Object { Write-Host $_ }
Write-Host "`nDossier prêt : $dossier" -ForegroundColor Green
