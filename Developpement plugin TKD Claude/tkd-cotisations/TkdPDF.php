<?php
/**
 * TkdPDF v2 — Generateur PDF recus TKD Claira
 * PHP pur, zlib, support JPEG + PNG (via GD si dispo).
 * Version corrigée : Gestion des accents, alignement parfait et prénom.
 */
class TkdPDF {

    private $W  = 595.28;
    private $H  = 841.89;
    private $M  = 45;
    private $buf = '';
    private $off = array();
    private $n   = 0;

    // Convertir UTF-8 vers Windows-1252 pour que les accents marchent en PDF (WinAnsi)
    private function t($s) {
        if (function_exists('mb_convert_encoding')) {
            $out = @mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
            if ($out !== false) return addcslashes($out, '()\\');
        }
        if (function_exists('iconv')) {
            $out = @iconv('UTF-8', 'windows-1252//TRANSLIT', $s);
            if ($out !== false) return addcslashes($out, '()\\');
        }
        $out = utf8_decode($s); // Fallback natif PHP
        return addcslashes($out, '()\\');
    }

    // Calculer la largeur réelle d'une chaine en points PDF (Helvetica proportionnel)
    private function str_width($str, $sz, $bold=false) {
        $clean = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str) : $str;
        if (!$clean) $clean = $str;
        
        $w = 0;
        $len = strlen($clean);
        for ($i=0; $i<$len; $i++) {
            $c = $clean[$i];
            if (ctype_upper($c)) $w += ($bold ? 0.72 : 0.66);
            elseif (ctype_lower($c)) {
                if ($c==='i' || $c==='l' || $c==='t' || $c==='f' || $c==='j') $w += ($bold ? 0.35 : 0.28);
                elseif ($c==='m' || $c==='w') $w += ($bold ? 0.85 : 0.80);
                else $w += ($bold ? 0.60 : 0.55);
            }
            elseif (ctype_digit($c)) $w += 0.55;
            elseif ($c === ' ') $w += 0.28;
            else $w += ($bold ? 0.60 : 0.55);
        }
        return $w * $sz;
    }

    // Ajouter un objet PDF
    private function obj($content) {
        $this->n++;
        $this->off[$this->n] = strlen($this->buf);
        $this->buf .= $this->n . " 0 obj\n" . $content . "endobj\n";
        return $this->n;
    }

    // Objet stream
    private function stream_obj($dict, $data) {
        $this->n++;
        $this->off[$this->n] = strlen($this->buf);
        $this->buf .= $this->n . " 0 obj\n<< $dict /Length " . strlen($data) . " >>\nstream\n";
        $this->buf .= $data . "\nendstream\nendobj\n";
        return $this->n;
    }

    // Charger une image JPEG
    private function load_jpeg($path) {
        $raw = file_get_contents($path);
        if (!$raw) return null;
        $info = getimagesize($path);
        if (!$info) return null;
        return array('w'=>$info[0], 'h'=>$info[1], 'data'=>$raw, 'type'=>'jpeg');
    }

    // Charger PNG via GD (converti en JPEG) ou skip
    private function load_png($path) {
        if (!function_exists('imagecreatefrompng') || !function_exists('imagejpeg')) return null;
        $im = @imagecreatefrompng($path);
        if (!$im) return null;
        $w = imagesx($im); $h = imagesy($im);
        $bg = imagecreatetruecolor($w, $h);
        imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
        imagecopy($bg, $im, 0, 0, 0, 0, $w, $h);
        ob_start(); imagejpeg($bg, null, 85); $raw = ob_get_clean();
        imagedestroy($im); imagedestroy($bg);
        return array('w'=>$w, 'h'=>$h, 'data'=>$raw, 'type'=>'jpeg');
    }

    // Injecter une image dans le PDF, retourner l'objet id
    private function add_image($img) {
        if (!$img) return null;
        $cs = ($img['type'] === 'jpeg') ? '/DeviceRGB' : '/DeviceRGB';
        $flt = '/DCTDecode';
        return $this->stream_obj(
            "/Type /XObject /Subtype /Image /Width {$img['w']} /Height {$img['h']} /ColorSpace $cs /BitsPerComponent 8 /Filter $flt",
            $img['data']
        );
    }

    public function generate($d) {
        $this->buf = "%PDF-1.4\n%\xe2\xe3\xcf\xd3\n";
        $this->n = 0;

        $W = $this->W; $H = $this->H; $M = $this->M;

        $logo_img = null;
        $sig_img  = null;
        if (!empty($d['logo_path'])) {
            $dir  = dirname($d['logo_path']) . '/';
            $jpgs = array($dir . 'logo.jpg', $dir . 'logo.jpeg', $d['logo_path']);
            foreach ($jpgs as $try) {
                $ext = strtolower(pathinfo($try, PATHINFO_EXTENSION));
                if ( file_exists($try) && in_array($ext, array('jpg','jpeg')) ) {
                    $logo_img = $this->load_jpeg($try);
                    if ($logo_img) break;
                }
            }
        }
        if (!empty($d['sig_path']) && file_exists($d['sig_path'])) {
            $sig_img = $this->load_jpeg($d['sig_path']);
        }

        // Fonts
        $fR = $this->obj("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\n");
        $fB = $this->obj("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>\n");

        $logo_id = $logo_img ? $this->add_image($logo_img) : null;
        $sig_id  = $sig_img  ? $this->add_image($sig_img)  : null;

        $s = '';

        $R  = function($x,$y,$w,$h,$r,$g,$b,$fill=true) {
            $op = $fill ? 'f' : 'S';
            return sprintf("%.3f %.3f %.3f %s %.2f %.2f %.2f %.2f re %s\n",
                $r/255,$g/255,$b/255,($fill?'rg':'RG'),$x,$y,$w,$h,$op);
        };
        $T  = function($x,$y,$str,$sz,$r,$g,$b,$bold=false) use ($fR,$fB) {
            $f = $bold ? "/Fb" : "/F1";
            return sprintf("BT %s %d Tf %.3f %.3f %.3f rg %.2f %.2f Td (%s) Tj ET\n",
                $f,$sz,$r/255,$g/255,$b/255,$x,$y,$str);
        };
        $L  = function($x1,$y1,$x2,$y2,$r,$g,$b,$lw=0.5) {
            return sprintf("%.2f w %.3f %.3f %.3f RG %.2f %.2f m %.2f %.2f l S\n",
                $lw,$r/255,$g/255,$b/255,$x1,$y1,$x2,$y2);
        };

        // ── HEADER fond bleu marine allégé ───────────────────────
        $hH = 110;
        $s .= $R(0, $H-$hH, $W, $hH, 30,58,95);

        if ($logo_id && $logo_img) {
            $lh = 60; 
            $lw = intval($lh * $logo_img['w'] / $logo_img['h']);
            $lx = $M;
            $ly = $H - $hH + ($hH - $lh) / 2;
            $s .= sprintf("q %.2f 0 0 %.2f %.2f %.2f cm /Im%d Do Q\n", $lw, $lh, $lx, $ly, $logo_id);
        }

        // "REÇU DE PAIEMENT" — centré
        $label = 'REÇU DE PAIEMENT';
        $lbl_w = $this->str_width($label, 14, true);
        $s .= $T(($W - $lbl_w) / 2, $H-42, $this->t($label), 14, 255,255,255, true);

        // Nom club — centré
        $club_raw = strtoupper($d['club_nom']);
        $club_w   = $this->str_width($club_raw, 10, false);
        $s .= $T(($W - $club_w) / 2, $H-60, $this->t($club_raw), 10, 200,215,235);

        // Badge N recu — centré et ajusté
        $badge   = 'N° ' . $d['recu_num'];
        $badge_w = 120; $badge_x = ($W - $badge_w) / 2;
        $s .= $R($badge_x, $H-90, $badge_w, 20, 50,80,120);
        $s .= $T($badge_x + 10, $H-84, $this->t($badge), 11, 255,255,255, true);

        // ── CORPS ────────────────────────────────────────────────
        $cy = $H - $hH - 45;

        // Préparation Nom & Prénom
        $dest_prenom = !empty($d['prenom']) ? trim($d['prenom']) . ' ' : '';
        $dest_nom    = !empty($d['nom']) ? trim($d['nom']) : '';
        $full_name   = trim($dest_prenom . $dest_nom);
        if (empty($full_name)) $full_name = 'Adhérent';

        $bon = 'Bonjour ' . $full_name . ',';
        $s .= $T($M, $cy, $this->t($bon), 12, 17,24,39, true);
        $cy -= 22;
        $s .= $T($M, $cy, $this->t('Nous vous confirmons la bonne réception de votre paiement.'), 10, 107,114,128);
        $cy -= 45;

        // Montant
        $box_h = 75;
        $s .= $R($M, $cy-$box_h, $W-2*$M, $box_h, 244,248,252);
        $s .= "0.5 w 0.118 0.227 0.373 RG ";
        $s .= sprintf("%.2f %.2f %.2f %.2f re S\n", $M, $cy-$box_h, $W-2*$M, $box_h);

        $mstr = number_format($d['montant'], 2, ',', ' ') . ' EUR';
        $mx = $W/2 - ($this->str_width($mstr, 28, true) / 2);
        $s .= $T($mx, $cy - 42, $this->t($mstr), 28, 30,58,95, true);
        
        $mrx = $W/2 - ($this->str_width('Montant reçu', 10, false) / 2);
        $s .= $T($mrx, $cy - 60, $this->t('Montant reçu'), 10, 107,114,128);
        $cy -= $box_h + 35;

        // Tableau aéré
        $rows = array(
            array('Objet',            $d['objet']),
            array('Adhérent',         $full_name), // Intègre désormais correctement le prénom + nom
            array('Mode de paiement', $d['mode_label']),
            array('Date du paiement', $d['date_paie']),
        );
        $rh = 35; $th = count($rows)*$rh;
        $s .= $R($M, $cy-$th, $W-2*$M, $th, 248,250,252);
        $s .= "0.5 w 0.886 0.910 0.941 RG ";
        $s .= sprintf("%.2f %.2f %.2f %.2f re S\n", $M, $cy-$th, $W-2*$M, $th);
        
        $col2 = $W - $M - 15;
        foreach ($rows as $i => $row) {
            $ry = $cy - ($i+1)*$rh + 12;
            $s .= $T($M+15, $ry, $this->t($row[0]), 10, 107,114,128);
            
            // Alignement dynamique parfait à droite
            $vlen = $this->str_width($row[1], 10, true); 
            $s .= $T($col2 - $vlen, $ry, $this->t($row[1]), 10, 17,24,39, true);
            
            if ($i > 0) $s .= $L($M, $cy-$i*$rh, $W-$M, $cy-$i*$rh, 226,232,240);
        }
        $cy -= $th + 35;

        // Fait à
        $fait = 'Fait à ' . $d['club_ville'] . ', le ' . date('d/m/Y');
        $fait_w = $this->str_width($fait, 10, false);
        $s .= $T($W - $M - $fait_w - 5, $cy, $this->t($fait), 10, 107,114,128);
        $cy -= 40;

        // Signature + tampon allégé
        $bloc_h = 85;
        $bloc_y = $cy - $bloc_h;

        // Zone signature à gauche
        $sig_zone_w = 140;
        if ($sig_id && $sig_img) {
            $sw2 = 120;
            $sh2 = intval($sw2 * $sig_img['h'] / $sig_img['w']);
            if ($sh2 > $bloc_h) {
                $sh2 = $bloc_h;
                $sw2 = intval($sh2 * $sig_img['w'] / $sig_img['h']);
            }
            $sig_x = $M + ($sig_zone_w - $sw2) / 2;
            $sig_y = $bloc_y + ($bloc_h - $sh2) / 2;
            $s .= sprintf("q %.2f 0 0 %.2f %.2f %.2f cm /Im%d Do Q\n",
                $sw2, $sh2, $sig_x, $sig_y, $sig_id);
        }

        // Zone tampon à droite
        $tw = 180;
        $tx = $W - $M - $tw;
        $s .= $R($tx, $bloc_y, $tw, $bloc_h, 244,248,252);
        $s .= "0.5 w 0.118 0.227 0.373 RG ";
        $s .= sprintf("%.2f %.2f %.2f %.2f re S\n", $tx, $bloc_y, $tw, $bloc_h);
        
        $line_h = 13;
        $lines_total = 5 * $line_h;
        $text_start_y = $bloc_y + $bloc_h - ($bloc_h - $lines_total) / 2 - $line_h + 2;
        $s .= $T($tx+10, $text_start_y,              $this->t(strtoupper($d['club_nom'])), 8, 30,58,95, true);
        $s .= $T($tx+10, $text_start_y - $line_h,    $this->t($d['club_adresse']), 7, 107,114,128);
        $s .= $T($tx+10, $text_start_y - 2*$line_h,  $this->t($d['club_email']), 7, 107,114,128);
        $s .= $T($tx+10, $text_start_y - 3*$line_h,  $this->t($d['club_tel']), 7, 107,114,128);
        $s .= $T($tx+10, $text_start_y - 4*$line_h,  $this->t('SIREN : ' . $d['club_siren']), 7, 107,114,128);

        // ── FOOTER ───────────────────────────────────────────────
        $s .= $R(0, 0, $W, 55, 248,250,252);
        $s .= $L(0, 55, $W, 55, 226,232,240);
        
        $footer1 = $d['club_adresse'] . '  |  ' . $d['club_email'] . '  |  ' . $d['club_tel'];
        $footer2 = 'SIREN : ' . $d['club_siren'] . '  |  Association de Loi 1901';
        
        $f1_w = $this->str_width($footer1, 8, false);
        $f2_w = $this->str_width($footer2, 8, false);

        $s .= $T(($W - $f1_w)/2, 32, $this->t($footer1), 8, 148,163,184); 
        $s .= $T(($W - $f2_w)/2, 18, $this->t($footer2), 8, 148,163,184);

        // ── ASSEMBLAGE PDF ───────────────────────────────────────
        $xobj = '';
        if ($logo_id) $xobj .= "/Im$logo_id $logo_id 0 R ";
        if ($sig_id)  $xobj .= "/Im$sig_id $sig_id 0 R ";
        $xobj_dict = $xobj ? "/XObject << $xobj >>" : '';

        $content_id = $this->stream_obj('', $s);

        $page_id = $this->obj(
            "<< /Type /Page /Parent 2 0 R\n" .
            "   /MediaBox [0 0 595.28 841.89]\n" .
            "   /Resources << /Font << /F1 $fR 0 R /Fb $fB 0 R >> $xobj_dict >>\n" .
            "   /Contents $content_id 0 R >>\n"
        );

        $pages_id = $this->obj("<< /Type /Pages /Kids [$page_id 0 R] /Count 1 >>\n");
        $cat_id = $this->obj("<< /Type /Catalog /Pages $pages_id 0 R >>\n");

        $xref_off = strlen($this->buf);
        $this->buf .= "xref\n0 " . ($this->n+1) . "\n";
        $this->buf .= "0000000000 65535 f \n";
        for ($i=1; $i<=$this->n; $i++) {
            $this->buf .= sprintf("%010d 00000 n \n", $this->off[$i]);
        }
        $this->buf .= "trailer\n<< /Size " . ($this->n+1) . " /Root $cat_id 0 R >>\n";
        $this->buf .= "startxref\n$xref_off\n%%EOF\n";

        return $this->buf;
    }
}