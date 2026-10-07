<?php
/**
 * Déplace des méthodes d'une classe vers un trait, SANS modifier leur code (restructuration,
 * étape 3 — 07/10/2026). La classe réintègre les méthodes par « use NomDuTrait; » : même
 * comportement, mêmes appels ($this->…, méthodes privées), seul le fichier change.
 *
 * Usage (depuis le dossier outils) :
 *   php deplacer-methodes.php <fichier source> <Classe> <NomDuTrait> <fichier trait> "<description>" methode1 methode2 …
 *
 * Le découpage se fait au caractère près avec l'analyseur PHP (token_get_all) : chaque méthode
 * est prise avec ses commentaires qui la précèdent, jusqu'à son accolade fermante. Vérification
 * intégrée : le texte de chaque méthode dans le trait est identique à l'original, et l'ensemble
 * source + trait contient exactement les mêmes caractères que la source d'origine (hors lignes
 * ajoutées : en-tête du trait, « use », require_once).
 */

[ , $src, $classe, $trait, $dest, $description ] = array_pad( $argv, 6, null );
$methodes = array_slice( $argv, 6 );
if ( ! $src || ! $classe || ! $trait || ! $dest || ! $methodes ) {
	fwrite( STDERR, "Usage : php deplacer-methodes.php <source> <Classe> <Trait> <fichier trait> \"<description>\" methode…\n" );
	exit( 1 );
}
$code   = file_get_contents( $src );
$tokens = token_get_all( $code );

// Positions (octets) de chaque jeton.
$pos = []; $o = 0;
foreach ( $tokens as $i => $t ) { $pos[ $i ] = $o; $o += strlen( is_array( $t ) ? $t[1] : $t ); }
$txt = static fn( $t ) => is_array( $t ) ? $t[1] : $t;

// Repérer la classe et sa première accolade.
$debut_classe = null;
foreach ( $tokens as $i => $t ) {
	if ( is_array( $t ) && $t[0] === T_CLASS ) {
		for ( $j = $i + 1; isset( $tokens[ $j ] ); $j++ ) {
			if ( is_array( $tokens[ $j ] ) && $tokens[ $j ][0] === T_STRING ) {
				if ( $tokens[ $j ][1] === $classe ) $debut_classe = $i;
				break;
			}
		}
		if ( $debut_classe !== null ) break;
	}
}
if ( $debut_classe === null ) { fwrite( STDERR, "Classe $classe introuvable.\n" ); exit( 1 ); }
for ( $acc = $debut_classe; $txt( $tokens[ $acc ] ) !== '{'; $acc++ );

// Parcours du corps de classe : profondeur 1 = membres de la classe.
$segments = [];
$prof = 0; $fin_membre_prec = $acc; // index du dernier jeton terminant un membre (';' ou '}')
for ( $i = $acc; isset( $tokens[ $i ] ); $i++ ) {
	$s = $txt( $tokens[ $i ] );
	if ( $s === '{' || ( is_array( $tokens[ $i ] ) && in_array( $tokens[ $i ][0], [ T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ], true ) ) ) { $prof++; continue; }
	if ( $s === '}' ) { $prof--; if ( $prof === 1 ) $fin_membre_prec = $i; if ( $prof === 0 ) break; continue; }
	if ( $prof === 1 && $s === ';' ) { $fin_membre_prec = $i; continue; }
	if ( $prof === 1 && is_array( $tokens[ $i ] ) && $tokens[ $i ][0] === T_FUNCTION ) {
		for ( $j = $i + 1; is_array( $tokens[ $j ] ) && $tokens[ $j ][0] === T_WHITESPACE; $j++ );
		$nom = $txt( $tokens[ $j ] );
		if ( ! in_array( $nom, $methodes, true ) ) continue;
		// Début : juste après le membre précédent, en sautant le retour à la ligne qui le suit.
		$debut = $pos[ $fin_membre_prec ] + 1;
		$nl    = strpos( $code, "\n", $debut );
		if ( $nl !== false && trim( substr( $code, $debut, $nl - $debut ) ) === '' ) $debut = $nl + 1;
		// Fin : accolade fermante correspondante.
		$k = $j; while ( $txt( $tokens[ $k ] ) !== '{' ) $k++;
		$p = 0;
		for ( ; isset( $tokens[ $k ] ); $k++ ) {
			$x = $txt( $tokens[ $k ] );
			if ( $x === '{' || ( is_array( $tokens[ $k ] ) && in_array( $tokens[ $k ][0], [ T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ], true ) ) ) $p++;
			if ( $x === '}' ) { $p--; if ( $p === 0 ) break; }
		}
		$fin = $pos[ $k ] + 1;
		$nl  = strpos( $code, "\n", $fin );
		if ( $nl !== false && trim( substr( $code, $fin, $nl - $fin ) ) === '' ) $fin = $nl + 1;
		$segments[ $nom ] = [ $debut, $fin ];
		$i = $k; $fin_membre_prec = $k;
	}
}
$manquantes = array_diff( $methodes, array_keys( $segments ) );
if ( $manquantes ) { fwrite( STDERR, 'Méthode(s) introuvable(s) : ' . implode( ', ', $manquantes ) . "\n" ); exit( 1 ); }

// Construire le trait (méthodes dans leur ordre d'origine) et la nouvelle source.
uasort( $segments, static fn( $a, $b ) => $a[0] <=> $b[0] );
$corps = ''; $nouvelle = ''; $curseur = 0;
foreach ( $segments as [ $d, $f ] ) {
	$corps    .= substr( $code, $d, $f - $d );
	$nouvelle .= substr( $code, $curseur, $d - $curseur );
	$curseur   = $f;
}
$nouvelle .= substr( $code, $curseur );

$fichier_trait = basename( $dest );
$entete = "<?php\n/**\n * " . str_replace( "\n", "\n * ", trim( $description ) ) . "\n *\n"
	. " * Méthodes de $classe déplacées telles quelles depuis " . basename( $src ) . " (restructuration,\n"
	. " * étape 3 — " . date( 'd/m/Y' ) . ", outils/deplacer-methodes.php). La classe les réintègre par « use $trait; » :\n"
	. " * même comportement, mêmes appels (\$this->db, méthodes privées de la classe).\n *\n * @package SP_Build\n */\n\n"
	. "if ( ! defined( 'ABSPATH' ) ) exit;\n\ntrait $trait {\n\n";
file_put_contents( $dest, $entete . trim( $corps, "\n" ) . "\n}\n" );

// « use Trait; » en tête du corps de classe, require_once avant la déclaration de classe.
$apres_acc = $pos[ $acc ] + 1;
$nouvelle  = substr( $nouvelle, 0, $apres_acc ) . "\n    use $trait; // " . $fichier_trait . "\n" . substr( $nouvelle, $apres_acc );
$ligne_classe = strrpos( substr( $nouvelle, 0, $pos[ $debut_classe ] ), "\n" ) + 1;
// Remonter avant un éventuel « if ( ! class_exists(...) ) : » ou commentaire collé à la classe.
$avant = substr( $nouvelle, 0, $ligne_classe );
if ( preg_match( '/\n(if \( ! class_exists[^\n]*\n\s*)$/', $avant, $m ) ) $ligne_classe -= strlen( $m[1] );
$nouvelle = substr( $nouvelle, 0, $ligne_classe ) . "require_once plugin_dir_path( __FILE__ ) . '$fichier_trait';\n" . substr( $nouvelle, $ligne_classe );
file_put_contents( $src, $nouvelle );

// Vérification : chaque méthode est identique, et rien d'autre n'a disparu.
$trait_code = file_get_contents( $dest );
$ok = true;
foreach ( $segments as $nom => [ $d, $f ] ) {
	if ( strpos( $trait_code, trim( substr( $code, $d, $f - $d ), "\n" ) ) === false ) { $ok = false; fwrite( STDERR, "ÉCART sur $nom\n" ); }
}
$somme_avant = strlen( $code );
$somme_apres = strlen( $nouvelle ) - strlen( "\n    use $trait; // " . $fichier_trait . "\n" ) - strlen( "require_once plugin_dir_path( __FILE__ ) . '$fichier_trait';\n" )
	+ strlen( $corps ); // le trait contient $corps (à des blancs de fin près)
if ( $somme_avant !== $somme_apres ) { $ok = false; fwrite( STDERR, "Taille incohérente : $somme_avant octets avant, $somme_apres après.\n" ); }
printf( "%s : %d méthode(s) déplacée(s) (%s), %d lignes → %s (%d lignes). Source : %d → %d lignes. Vérification : %s\n",
	basename( $src ), count( $segments ), implode( ', ', array_keys( $segments ) ), substr_count( $corps, "\n" ),
	$fichier_trait, substr_count( $trait_code, "\n" ), substr_count( $code, "\n" ), substr_count( $nouvelle, "\n" ), $ok ? 'OK' : 'ÉCHEC' );
exit( $ok ? 0 : 1 );
