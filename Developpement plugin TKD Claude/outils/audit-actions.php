<?php
/**
 * Audit : pour chaque action AJAX (wp_ajax_*, wp_ajax_nopriv_*) et formulaire admin-post
 * (admin_post_*) déclarés par les plugins, la méthode appelée vérifie-t-elle un jeton (nonce)
 * et les droits (current_user_can) ? Analyse statique approximative (texte de la méthode +
 * méthodes privées qu'elle appelle sur $this / self).
 */
// Usage (depuis le dossier outils) :  php audit-actions.php ..
// Limite : ne couvre pas les routes REST (register_rest_route), à vérifier à part.
$racine  = rtrim( realpath( $argv[1] ?? '..' ), '\\/' );
$fichiers = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $racine, FilesystemIterator::SKIP_DOTS ) );
$methodes = [];   // [classe::methode] => texte
$fichier_de = [];
$hooks    = [];   // [ hook, cible, fichier ]

foreach ( $fichiers as $f ) {
	$p = $f->getPathname();
	if ( substr( $p, -4 ) !== '.php' || preg_match( '#[\\\\/](vendor|tests|node_modules)[\\\\/]#', $p ) ) continue;
	$src    = file_get_contents( $p );
	$tokens = token_get_all( $src );
	$classe = '';
	$n      = count( $tokens );
	for ( $i = 0; $i < $n; $i++ ) {
		$t = $tokens[ $i ];
		if ( is_array( $t ) && $t[0] === T_CLASS ) {
			for ( $j = $i + 1; $j < $n; $j++ ) if ( is_array( $tokens[ $j ] ) && $tokens[ $j ][0] === T_STRING ) { $classe = $tokens[ $j ][1]; break; }
		}
		if ( is_array( $t ) && $t[0] === T_FUNCTION ) {
			$nom = '';
			for ( $j = $i + 1; $j < $n; $j++ ) {
				if ( is_array( $tokens[ $j ] ) && $tokens[ $j ][0] === T_STRING ) { $nom = $tokens[ $j ][1]; break; }
				if ( $tokens[ $j ] === '(' ) break;
			}
			if ( $nom === '' ) continue;
			// corps : de la première { à l'accolade fermante correspondante
			for ( $j = $i; $j < $n && $tokens[ $j ] !== '{' && $tokens[ $j ] !== ';'; $j++ );
			if ( $j >= $n || $tokens[ $j ] === ';' ) continue;
			$prof = 0; $texte = '';
			for ( $k = $j; $k < $n; $k++ ) {
				$tk = $tokens[ $k ];
				$s  = is_array( $tk ) ? $tk[1] : $tk;
				if ( $s === '{' || ( is_array( $tk ) && in_array( $tk[0], [ T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ], true ) ) ) $prof++;
				if ( $s === '}' ) $prof--;
				$texte .= $s;
				if ( $prof === 0 ) break;
			}
			$methodes[ $classe . '::' . $nom ] = $texte;
			$fichier_de[ $classe . '::' . $nom ] = $p;
		}
	}
	if ( preg_match_all( "#add_action\(\s*'((?:wp_ajax_nopriv_|wp_ajax_|admin_post_nopriv_|admin_post_)[a-z0-9_]+)'\s*,\s*(?:array\(|\[)\s*(\\\$this|__CLASS__|self::class|'[A-Za-z_]+')\s*,\s*'([a-z0-9_]+)'#i", $src, $m, PREG_SET_ORDER ) ) {
		// classe courante du fichier (première classe déclarée)
		preg_match( '#\bclass\s+([A-Za-z_][A-Za-z0-9_]*)#', $src, $mc );
		foreach ( $m as $h ) {
			$cl = in_array( $h[2], [ '$this', '__CLASS__', 'self::class' ], true ) ? ( $mc[1] ?? '' ) : trim( $h[2], "'" );
			$hooks[] = [ $h[1], $cl . '::' . $h[3], $p ];
		}
	}
	// Actions déclarées en boucle (ex. passages : foreach ( [ 'creer', … ] as $a ) add_action( 'admin_post_sp_passage_' . $a …
	if ( preg_match_all( "#foreach\s*\(\s*\[([^\]]+)\]\s*as\s*\\\$(\w+)\s*\)\s*\{?\s*add_action\(\s*'([a-z_]+)'\s*\.\s*\\\$\\2\s*,\s*\[\s*\\\$this\s*,\s*'([a-z_]+)'\s*\.\s*\\\$\\2#i", $src, $m2, PREG_SET_ORDER ) ) {
		preg_match( '#\bclass\s+([A-Za-z_][A-Za-z0-9_]*)#', $src, $mc );
		foreach ( $m2 as $h ) {
			preg_match_all( "#'([a-z0-9_]+)'#", $h[1], $noms );
			foreach ( $noms[1] as $a ) $hooks[] = [ $h[3] . $a, ( $mc[1] ?? '' ) . '::' . $h[4] . $a, $p ];
		}
	}
}

/** Texte de la méthode + méthodes appelées via $this-> / self:: (2 niveaux). */
function texte_etendu( string $cle, array $methodes, int $niveau = 2 ): string {
	$t = $methodes[ $cle ] ?? '';
	if ( $niveau === 0 || $t === '' ) return $t;
	[ $cl ] = explode( '::', $cle );
	preg_match_all( '#(?:\$this->|self::|static::)([a-zA-Z_][a-zA-Z0-9_]*)\s*\(#', $t, $m );
	foreach ( array_unique( $m[1] ) as $appel ) {
		if ( isset( $methodes[ $cl . '::' . $appel ] ) && $cl . '::' . $appel !== $cle ) $t .= "\n" . texte_etendu( $cl . '::' . $appel, $methodes, $niveau - 1 );
	}
	return $t;
}

$nonce_re = '#check_ajax_referer|check_admin_referer|wp_verify_nonce#';
$cap_re   = '#current_user_can|user_can\(#';
$lignes   = [];
foreach ( $hooks as [ $hook, $cible, $p ] ) {
	$t     = texte_etendu( $cible, $methodes );
	$trouve = $t !== '';
	$nonce = $trouve && preg_match( $nonce_re, $t );
	$cap   = $trouve && preg_match( $cap_re, $t );
	$nopriv = strpos( $hook, '_nopriv_' ) !== false;
	$etat  = ! $trouve ? 'METHODE INTROUVABLE' : ( $nonce && ( $cap || $nopriv ) ? 'ok' : ( $nonce ? 'nonce sans droits' : ( $cap ? 'DROITS SANS NONCE' : 'NI NONCE NI DROITS' ) ) );
	$lignes[] = sprintf( "%-20s %-52s %-48s %s", $etat, $hook, $cible, str_replace( $racine, '', $p ) );
}
sort( $lignes );
echo implode( "\n", $lignes ), "\n\nTotal : ", count( $lignes ), " actions\n";
