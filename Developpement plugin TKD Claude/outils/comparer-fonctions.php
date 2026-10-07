<?php
// Restructuration, étape 3 (07/10/2026) — vérification indépendante de deplacer-methodes.php.
// Compare les fonctions (nom + code exact, blancs de bord ignorés) d'un ensemble de fichiers
// AVANT et APRÈS un déplacement. Usage : php comparer-fonctions.php "<avant1;avant2>" "<apres1;apres2;…>"
// (version « avant » : git show HEAD:includes/fichier.php > avant.php)
function fonctions( string $fichier ): array {
	// Fins de ligne normalisées : une copie tirée de Git (\n) et le fichier de travail sous
	// Windows (\r\n) ne doivent pas apparaître comme « modifiés ».
	$code = str_replace( "\r\n", "\n", file_get_contents( $fichier ) ); $t = token_get_all( $code ); $out = [];
	$n = count( $t );
	for ( $i = 0; $i < $n; $i++ ) {
		if ( ! is_array( $t[ $i ] ) || $t[ $i ][0] !== T_FUNCTION ) continue;
		$j = $i + 1; while ( is_array( $t[ $j ] ) && $t[ $j ][0] === T_WHITESPACE ) $j++;
		if ( ! is_array( $t[ $j ] ) || $t[ $j ][0] !== T_STRING ) continue; // fonction anonyme : incluse dans sa parente
		$nom = $t[ $j ][1]; $txt = ''; $p = 0; $vu = false;
		for ( $k = $i; $k < $n; $k++ ) {
			$s = is_array( $t[ $k ] ) ? $t[ $k ][1] : $t[ $k ];
			$txt .= $s;
			if ( $s === ';' && ! $vu ) break; // méthode abstraite
			if ( $s === '{' || ( is_array( $t[ $k ] ) && in_array( $t[ $k ][0], [ T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ], true ) ) ) { $p++; $vu = true; }
			if ( $s === '}' ) { $p--; if ( $vu && $p === 0 ) break; }
		}
		$out[ $nom ][] = md5( trim( $txt ) );
		$i = $k;
	}
	return $out;
}
$avant = []; foreach ( explode( ';', $argv[1] ) as $f ) foreach ( fonctions( $f ) as $n => $h ) foreach ( $h as $x ) $avant[] = "$n:$x";
$apres = []; foreach ( explode( ';', $argv[2] ) as $f ) foreach ( fonctions( $f ) as $n => $h ) foreach ( $h as $x ) $apres[] = "$n:$x";
sort( $avant ); sort( $apres );
$disparues = array_diff( $avant, $apres ); $nouvelles = array_diff( $apres, $avant );
echo 'Fonctions avant : ' . count( $avant ) . ' — après : ' . count( $apres ) . "\n";
echo $disparues ? 'DISPARUES OU MODIFIÉES : ' . implode( ', ', array_map( fn( $x ) => explode( ':', $x )[0], $disparues ) ) . "\n" : "Aucune fonction disparue ni modifiée.\n";
echo $nouvelles ? 'NOUVELLES : ' . implode( ', ', array_map( fn( $x ) => explode( ':', $x )[0], $nouvelles ) ) . "\n" : "Aucune fonction ajoutée.\n";
