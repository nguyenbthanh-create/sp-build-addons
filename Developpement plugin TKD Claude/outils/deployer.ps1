# Envoie un dossier de déploiement sp_build sur le site de test ou la prod, par FTP (29/10/2026).
#
# Remplace l'envoi FTP à la main. Utilise la session WinSCP enregistrée par l'utilisateur
# (« tkdclad@ftp.cluster021.hosting.ovh.net », mot de passe gardé par WinSCP : ce script ne le
# voit ni ne le demande). SSH n'est pas utilisé : le port 22 est bloqué sur le réseau habituel.
#
# Étapes : (1) état des fichiers sur le serveur ; (2) sauvegarde locale des fichiers qui vont être
# remplacés, dans <dossier>\sauvegarde-<cible>-<date>\ ; (3) envoi en BINAIRE ; (4) contrôle de la
# taille de chaque fichier sur le serveur. Journal : outils\deploiements.log.
#
# Usage (depuis ce dossier « outils ») :
#   .\deployer.ps1 -Cible test                         dernier dossier DEPLOIEMENT_*_sp_build_*
#   .\deployer.ps1 -Cible test -Dossier ..\DEPLOIEMENT_…
#   .\deployer.ps1 -Cible prod -Prod                   la prod exige -Prod (feu vert de l'utilisateur)
#   .\deployer.ps1 -Cible test -Verifier               compare seulement les tailles, n'envoie rien
#   .\deployer.ps1 -Cible prod -Prod -Restaurer <dossier de sauvegarde>   retour arrière
#
# Les fichiers à SUPPRIMER éventuels (LISTE.txt) ne sont jamais supprimés par ce script.

param(
    [Parameter( Mandatory = $true )] [ValidateSet( 'test', 'prod' )] [string] $Cible,
    [string] $Dossier,
    [switch] $Prod,
    [switch] $Verifier,
    [string] $Restaurer
)

$ErrorActionPreference = 'Stop'
$winscp  = 'C:\Program Files (x86)\WinSCP\WinSCP.com'
$session = 'tkdclad@ftp.cluster021.hosting.ovh.net'
$racine  = Split-Path $PSScriptRoot -Parent
# Chemins complets : la session enregistrée s'ouvre par défaut dans le dossier de la PROD.
$base    = @{ test = '/dev.tkdclaira.fr/wp-content/plugins/sp_build/'; prod = '/www/wp-content/plugins/sp_build/' }[ $Cible ]

if ( $Cible -eq 'prod' -and -not $Prod ) { Write-Host 'La prod exige -Prod (feu vert explicite de l''utilisateur).' -ForegroundColor Red; exit 1 }
if ( -not ( Test-Path $winscp ) ) { Write-Host "WinSCP introuvable : $winscp" -ForegroundColor Red; exit 1 }

if ( $Restaurer ) {
    $source = ( Resolve-Path $Restaurer ).Path
} elseif ( $Dossier ) {
    $source = ( Resolve-Path $Dossier ).Path
} else {
    $d = Get-ChildItem $racine -Directory -Filter 'DEPLOIEMENT_*_sp_build_*' | Sort-Object Name | Select-Object -Last 1
    if ( -not $d ) { Write-Host 'Aucun dossier DEPLOIEMENT_*_sp_build_* trouvé.' -ForegroundColor Red; exit 1 }
    $source = $d.FullName
}

# Fichiers à envoyer : tout le dossier sauf LISTE.txt et les sauvegardes, chemins relatifs « a/b.php ».
$fichiers = Get-ChildItem $source -Recurse -File |
    Where-Object { $_.Name -ne 'LISTE.txt' -and $_.FullName.Substring( $source.Length ) -notmatch '^\\sauvegarde-' } |
    ForEach-Object { [pscustomobject]@{ Rel = $_.FullName.Substring( $source.Length + 1 ).Replace( '\', '/' ); Local = $_.FullName; Taille = $_.Length } }
if ( -not $fichiers ) { Write-Host "Aucun fichier à envoyer dans $source" -ForegroundColor Red; exit 1 }

function Invoke-Ftp( [string[]] $commandes, [switch] $Continuer ) {
    $script = [IO.Path]::GetTempFileName()
    $lignes = @(
        'option batch continue', 'option confirm off',
        "open ""$session"" -rawsettings LocalDirectory=""$env:USERPROFILE""",
        $( if ( $Continuer ) { 'option batch continue' } else { 'option batch abort' } )
    ) + $commandes + 'exit'
    [IO.File]::WriteAllLines( $script, $lignes, ( New-Object Text.UTF8Encoding $false ) )
    $sortie = & $winscp /script="$script" 2>&1 | ForEach-Object { "$_" }
    $code = $LASTEXITCODE
    Remove-Item $script -ErrorAction SilentlyContinue
    return [pscustomobject]@{ Sortie = $sortie; Code = $code }
}

# Tailles sur le serveur : un « ls » par dossier, repéré par une ligne « ###DIR <dossier> ».
function Get-TaillesServeur {
    $dirs = $fichiers | ForEach-Object { $base + ( Split-Path $_.Rel -Parent ).Replace( '\', '/' ) } | Sort-Object -Unique
    $r = Invoke-Ftp -Continuer ( $dirs | ForEach-Object { "echo ###DIR $_"; "ls ""$_""" } )
    $tailles = @{}; $dir = $null
    foreach ( $l in $r.Sortie ) {
        if ( $l -match '^###DIR (.+)$' ) { $dir = $Matches[1].TrimEnd( '/' ); continue }
        if ( $dir -and $l -match '^-\S+\s+\S+\s+\S+\s+\S+\s+(\d+)\s+\S+\s+\d+\s+\S+\s+\d{4}\s+(.+)$' ) {
            $tailles[ "$dir/$( $Matches[2].Trim() )" ] = [long] $Matches[1]
        }
    }
    return $tailles
}

function Distant( $f ) { return ( $base + $f.Rel ).Replace( '//', '/' ) }

Write-Host "Cible : $Cible ($base)" -ForegroundColor Cyan
Write-Host "Source : $source"
$avant = Get-TaillesServeur

if ( -not $Verifier ) {
    $stamp = Get-Date -Format 'yyyy-MM-dd_HHmmss'
    $commandes = @()
    # (2) Sauvegarde des fichiers qui existent déjà sur le serveur (pas pour une restauration).
    if ( -not $Restaurer ) {
        $sauvegarde = Join-Path $source "sauvegarde-$Cible-$stamp"
        foreach ( $f in $fichiers ) {
            if ( $avant.ContainsKey( ( Distant $f ) ) ) {
                $loc = Join-Path $sauvegarde $f.Rel.Replace( '/', '\' )
                New-Item -ItemType Directory -Path ( Split-Path $loc -Parent ) -Force | Out-Null
                $commandes += "get -transfer=binary ""$( Distant $f )"" ""$loc"""
            }
        }
    }
    # (3) Envoi. Un dossier absent du serveur est créé d'abord.
    $dirsServeur = $avant.Keys | ForEach-Object { $_.Substring( 0, $_.LastIndexOf( '/' ) ) } | Sort-Object -Unique
    $fichiers | ForEach-Object { ( Distant $_ ).Substring( 0, ( Distant $_ ).LastIndexOf( '/' ) ) } | Sort-Object -Unique |
        Where-Object { $dirsServeur -notcontains $_ } | ForEach-Object { $commandes += "mkdir ""$_""" }
    # -nopreservetime : date du serveur = heure de l'envoi, comme un envoi FTP à la main. Sinon un
    # retour arrière remettrait l'ancienne date et le numéro de version des CSS/JS (date du fichier)
    # redeviendrait l'ancien : un navigateur garderait la version plus récente en cache.
    foreach ( $f in $fichiers ) { $commandes += "put -transfer=binary -nopreservetime ""$( $f.Local )"" ""$( Distant $f )""" }
    $r = Invoke-Ftp $commandes
    if ( $r.Code -ne 0 ) {
        $r.Sortie | Select-Object -Last 15 | ForEach-Object { Write-Host $_ }
        Write-Host 'ÉCHEC de l''envoi : vérifier l''état ci-dessous.' -ForegroundColor Red
    }
}

# (4) Contrôle des tailles
$apres = Get-TaillesServeur
$ko = 0
foreach ( $f in $fichiers ) {
    $t = $apres[ ( Distant $f ) ]
    $ok = $t -eq $f.Taille
    if ( -not $ok ) { $ko++ }
    Write-Host ( '{0} {1,-45} local {2,9:N0}   serveur {3,9}' -f ( $( if ( $ok ) { 'OK' } else { 'KO' } ) ), $f.Rel, $f.Taille, $( if ( $null -ne $t ) { '{0:N0}' -f $t } else { 'absent' } ) ) -ForegroundColor $( if ( $ok ) { 'Green' } else { 'Red' } )
}
$liste = Join-Path $source 'LISTE.txt'
if ( ( Test-Path $liste ) -and ( Select-String -Path $liste -Pattern 'SUPPRIMER' -Quiet ) ) {
    Write-Host 'LISTE.txt demande des SUPPRESSIONS sur le serveur : non faites par ce script.' -ForegroundColor Yellow
}
$bilan = $( if ( $ko ) { "$ko fichier(s) KO" } else { 'tout est conforme' } )
if ( -not $Verifier -and -not $Restaurer -and $sauvegarde -and ( Test-Path $sauvegarde ) ) { Write-Host "Sauvegarde des anciens fichiers : $sauvegarde" }
$action = $( if ( $Verifier ) { 'vérification' } elseif ( $Restaurer ) { 'restauration' } else { 'envoi' } )
Add-Content -Path ( Join-Path $PSScriptRoot 'deploiements.log' ) -Encoding UTF8 -Value ( '{0}  {1,-5} {2,-12} {3}  ({4} fichiers) : {5}' -f ( Get-Date -Format 'yyyy-MM-dd HH:mm' ), $Cible, $action, ( Split-Path $source -Leaf ), @( $fichiers ).Count, $bilan )
Write-Host "Bilan : $bilan" -ForegroundColor $( if ( $ko ) { 'Red' } else { 'Green' } )
if ( $ko ) { exit 1 }
