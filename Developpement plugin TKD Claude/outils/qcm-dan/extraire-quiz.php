<?php
/**
 * Extraction des quiz Module B FFTDA (PDF Apple Numbers).
 * La bonne réponse est la cellule écrite en vert (0.09 0.2 0.016 rg).
 * Usage : php extract.php <dossier_pdf> <sortie.json>
 */
require __DIR__ . '/vendor/autoload.php';

const GREEN = '0.09 0.2 0.016';
const BS    = "\x5c";

function page_stream( $page ) {
	$c = $page->get( 'Contents' );
	if ( $c instanceof \Smalot\PdfParser\PDFObject ) {
		return $c->getContent();
	}
	$raw = '';
	foreach ( $c->getContent() as $o ) {
		$raw .= $o->getContent();
	}
	return $raw;
}

function pdf_str( $s ) {
	$out = '';
	$n   = strlen( $s );
	$map = array( 'n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\x0c", '(' => '(', ')' => ')', BS => BS );
	for ( $i = 0; $i < $n; $i++ ) {
		$ch = $s[ $i ];
		if ( BS !== $ch ) {
			$out .= $ch;
			continue;
		}
		$nx = $s[ ++$i ] ?? '';
		if ( isset( $map[ $nx ] ) ) {
			$out .= $map[ $nx ];
		} elseif ( ctype_digit( $nx ) ) {
			$o = $nx;
			while ( strlen( $o ) < 3 && isset( $s[ $i + 1 ] ) && ctype_digit( $s[ $i + 1 ] ) ) {
				$o .= $s[ ++$i ];
			}
			$out .= chr( octdec( $o ) );
		} elseif ( "\n" !== $nx && "\r" !== $nx ) {
			$out .= $nx;
		}
	}
	return $out;
}

/** Événements texte : clip (x,y,w,h), couleur, ligne (y texte), texte. */
function events( $raw ) {
	$ev    = array();
	$color = '';
	$clip  = null;
	$stk   = array();
	$ty    = 0.0;
	$len   = strlen( $raw );
	$i     = 0;
	$ops   = array();
	$last  = null;
	while ( $i < $len ) {
		$c = $raw[ $i ];
		if ( ctype_space( $c ) ) {
			$i++;
			continue;
		}
		if ( '(' === $c ) {
			$d   = 1;
			$j   = $i + 1;
			$buf = '';
			while ( $j < $len ) {
				$ch = $raw[ $j ];
				if ( BS === $ch ) {
					$buf .= $ch . $raw[ $j + 1 ];
					$j   += 2;
					continue;
				}
				if ( '(' === $ch ) {
					$d++;
				}
				if ( ')' === $ch && 0 === --$d ) {
					break;
				}
				$buf .= $ch;
				$j++;
			}
			$ops[] = array( 's', pdf_str( $buf ) );
			$i     = $j + 1;
			continue;
		}
		if ( '[' === $c || ']' === $c ) {
			$i++;
			continue;
		}
		if ( '/' === $c ) {
			preg_match( '/\/[^\s\/\[\]\(\)<>]*/A', $raw, $m, 0, $i );
			$ops[] = array( 'n', $m[0] );
			$i    += strlen( $m[0] );
			continue;
		}
		if ( ( '<' === $c && '<' === ( $raw[ $i + 1 ] ?? '' ) ) || ( '>' === $c && '>' === ( $raw[ $i + 1 ] ?? '' ) ) ) {
			$i += 2;
			continue;
		}
		if ( '<' === $c ) {
			$j     = strpos( $raw, '>', $i );
			$ops[] = array( 's', hex2bin( preg_replace( '/\s/', '', substr( $raw, $i + 1, $j - $i - 1 ) ) ) );
			$i     = $j + 1;
			continue;
		}
		preg_match( '/[^\s\[\]\(\)\/<>]+/A', $raw, $m, 0, $i );
		$tok = $m[0];
		$i  += strlen( $tok );
		if ( is_numeric( $tok ) ) {
			$ops[] = array( 'v', (float) $tok );
			continue;
		}
		$args = $ops;
		$ops  = array();
		$nums = array();
		foreach ( $args as $a ) {
			if ( 'v' === $a[0] ) {
				$nums[] = $a[1];
			}
		}
		switch ( $tok ) {
			case 'q':
				$stk[] = array( $color, $clip );
				break;
			case 'Q':
				list( $color, $clip ) = array_pop( $stk );
				break;
			case 'rg':
				$color = implode( ' ', array_map( fn( $v ) => rtrim( rtrim( sprintf( '%.3f', $v ), '0' ), '.' ), $nums ) );
				break;
			case 'g':
				$color = 'g' . $nums[0];
				break;
			case 're':
				$last = $nums;
				break;
			case 'W':
				$clip = $last;
				break;
			case 'Tm':
				$ty = $nums[5];
				break;
			case 'Td':
			case 'TD':
				$ty += $nums[1];
				break;
			case 'Tj':
			case 'TJ':
				$t = '';
				foreach ( $args as $a ) {
					if ( 's' === $a[0] ) {
						$t .= $a[1];
					} elseif ( 'v' === $a[0] && 'TJ' === $tok && $a[1] < -150 ) {
						$t .= ' ';
					}
				}
				$ev[] = array( 'clip' => $clip, 'color' => $color, 'y' => round( $ty, 1 ), 'text' => ( preg_match( '/[�-�]/', $t ) && mb_check_encoding( $t, 'UTF-8' ) ) ? $t : iconv( 'MACINTOSH', 'UTF-8//IGNORE', $t ) );
				break;
		}
	}
	return $ev;
}

function clean( $s ) {
	return trim( preg_replace( '/\s+/u', ' ', $s ) );
}

$all    = array();
$report = array();
foreach ( glob( $argv[1] . '/*.pdf' ) as $file ) {
	$pdf    = ( new \Smalot\PdfParser\Parser() )->parseFile( $file );
	$titre  = '';
	$source = '';
	$cols   = array();
	$rows   = array();
	foreach ( $pdf->getPages() as $pi => $page ) {
		$evs = events( page_stream( $page ) );
		// Colonnes : repérées par les en-têtes (page 1).
		if ( ! $cols ) {
			foreach ( $evs as $e ) {
				if ( ! $e['clip'] ) {
					continue;
				}
				$t = clean( $e['text'] );
				$x = (int) round( $e['clip'][0] );
				if ( 'g1' !== $e['color'] ) {
					continue;
				}
				if ( preg_match( '/^N\W?$/u', $t ) ) {
					$cols[ $x ] = 'n';
				} elseif ( 'Question' === $t ) {
					$cols[ $x ] = 'q';
				} elseif ( preg_match( '/^R.ponse ([ABCD])$/u', $t, $m ) ) {
					$cols[ $x ] = $m[1];
				} elseif ( 'Source' === $t ) {
					$cols[ $x ] = 'src';
				}
			}
		}
		$cells = array();
		$banner = $cols ? 0.7 * ( max( array_keys( $cols ) ) - min( array_keys( $cols ) ) ) : 1e9;
		$prevy = array();
		foreach ( $evs as $e ) {
			if ( ! $e['clip'] ) {
				continue;
			}
			list( $x, $y, $w ) = $e['clip'];
			$col = null;
			foreach ( $cols as $cx => $k ) {
				if ( abs( $x - $cx ) < 15 ) {
					$col = $k;
				}
			}
			if ( ! $col || $w > $banner ) {
				if ( 0 === $pi && $w > $banner ) {
					if ( 'g0.533' === $e['color'] ) {
						$source .= $e['text'];
					} else {
						$titre .= $e['text'];
					}
				}
				continue;
			}
			$key = (string) round( $y );
			$id  = $key . $col;
			$sep = ( isset( $prevy[ $id ] ) && $prevy[ $id ] !== $e['y'] && ! preg_match( '/-$/', $cells[ $key ][ $col ]['t'] ?? '' ) ) ? ' ' : '';
			$prevy[ $id ] = $e['y'];
			$cells[ $key ][ $col ]['t'] = ( $cells[ $key ][ $col ]['t'] ?? '' ) . $sep . $e['text'];
			if ( GREEN === $e['color'] ) {
				$cells[ $key ][ $col ]['green'] = true;
			}
		}
		krsort( $cells, SORT_NUMERIC );
		foreach ( $cells as $r ) {
			$rows[] = $r;
		}
	}
	$base = basename( $file, '.pdf' );
	$qs   = array();
	$pb   = array();
	foreach ( $rows as $r ) {
		$n = clean( $r['n']['t'] ?? '' );
		if ( ! ctype_digit( $n ) ) {
			continue;
		}
		$good = array_values( array_filter( array( 'A', 'B', 'C', 'D' ), fn( $k ) => ! empty( $r[ $k ]['green'] ) ) );
		$q    = array(
			'n'   => (int) $n,
			'q'   => clean( $r['q']['t'] ?? '' ),
			'r'   => array( clean( $r['A']['t'] ?? '' ), clean( $r['B']['t'] ?? '' ), clean( $r['C']['t'] ?? '' ), clean( $r['D']['t'] ?? '' ) ),
			'ok'  => 1 === count( $good ) ? ord( $good[0] ) - 65 : null,
			'src' => clean( $r['src']['t'] ?? '' ),
		);
		if ( null === $q['ok'] || '' === $q['q'] || in_array( '', $q['r'], true ) ) {
			$pb[] = $n . '(' . implode( '', $good ) . ')';
		}
		$qs[] = $q;
	}
	$all[]    = array(
		'id'        => $base,
		'titre'     => clean( $titre ),
		'source'    => clean( $source ),
		'pdf'       => basename( $file ),
		'questions' => $qs,
	);
	$report[] = sprintf( '%-38s %3d q  cols:%d  pb:%s', $base, count( $qs ), count( $cols ), implode( ',', $pb ) );
}
file_put_contents( $argv[2], json_encode( $all, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
echo implode( "\n", $report ), "\nTOTAL ", array_sum( array_map( fn( $t ) => count( $t['questions'] ), $all ) ), "\n";
