<?php

declare(strict_types=1);

namespace SpCompta\Media;

use SpCompta\Capabilities;

/**
 * Rangement protege des justificatifs (10/11/2026) : factures, tickets et
 * contrats ne vont plus dans la mediatheque publique (uploads/AAAA/MM/,
 * adresse devinable, liste publique via /wp-json/wp/v2/media) mais dans
 * uploads/sp-compta/AAAA/MM/, ferme au web par un .htaccess. Ils restent des
 * attachments WordPress (id stocke dans justificatif / fichier_contrat) et ne
 * s'ouvrent que par url() → admin-post.php?action=sp_compta_fichier, reserve
 * a la capacite sp_compta_manager. Voir JustificatifStorage.md.
 */
final class JustificatifStorage
{
    public const DOSSIER = 'sp-compta';

    public const ACTION = 'sp_compta_fichier';

    /** Marque posee sur l'attachment : exclu de la mediatheque. */
    public const META = '_sp_compta_protege';

    public function __construct(private Capabilities $capabilities)
    {
    }

    public function registerHooks(): void
    {
        add_action('admin_post_' . self::ACTION, [$this, 'servir']);
        add_action('admin_post_nopriv_' . self::ACTION, [$this, 'servir']);

        // Justificatifs masques de la mediatheque (grille, liste, REST) : leurs
        // vignettes y seraient cassees (dossier ferme) et ils n'ont rien a y faire.
        add_filter('ajax_query_attachments_args', [$this, 'exclureDeLaRequete']);
        add_filter('rest_attachment_query', [$this, 'exclureDeLaRequete']);
        add_action('pre_get_posts', [$this, 'exclureDeLaListe']);
    }

    // ─── Chemins ────────────────────────────────────────────────────────────

    public static function base(): string
    {
        return trailingslashit(wp_upload_dir(null, false)['basedir']) . self::DOSSIER;
    }

    /** Cree le dossier ferme (idempotent). */
    public static function protegerDossier(): bool
    {
        $base = self::base();

        if (!is_dir($base) && !wp_mkdir_p($base)) {
            return false;
        }

        $htaccess = $base . '/.htaccess';
        if (!is_file($htaccess)) {
            file_put_contents(
                $htaccess,
                "# SP Compta : justificatifs servis uniquement par l'extension (admin-post.php?action=sp_compta_fichier)\n"
                . "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n"
                . "<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n"
            );
        }

        if (!is_file($base . '/index.php')) {
            file_put_contents($base . '/index.php', "<?php\n// Silence.\n");
        }

        return is_file($htaccess);
    }

    /**
     * Filtre upload_dir le temps d'un envoi : uploads/sp-compta/AAAA/MM.
     *
     * @param array<string, mixed> $dirs
     * @return array<string, mixed>
     */
    public static function filtreUploadDir(array $dirs): array
    {
        $sous = '/' . self::DOSSIER . (string) $dirs['subdir'];
        $dirs['path'] = $dirs['basedir'] . $sous;
        $dirs['url'] = $dirs['baseurl'] . $sous;
        $dirs['subdir'] = $sous;

        return $dirs;
    }

    public static function estProtege(int $attachmentId): bool
    {
        $rel = (string) get_post_meta($attachmentId, '_wp_attached_file', true);

        return str_starts_with($rel, self::DOSSIER . '/');
    }

    public static function marquer(int $attachmentId): void
    {
        update_post_meta($attachmentId, self::META, '1');
    }

    /** Adresse de consultation (comptes tresorerie seulement). */
    public static function url(int $attachmentId): string
    {
        return add_query_arg(['action' => self::ACTION, 'id' => $attachmentId], admin_url('admin-post.php'));
    }

    // ─── Deplacement d'un justificatif deja dans la mediatheque publique ───

    /**
     * Deplace le fichier d'un attachment (et ses tailles d'image / apercus PDF)
     * de uploads/AAAA/MM/ vers uploads/sp-compta/AAAA/MM/ et met a jour ses
     * metadonnees. Renvoie 'deplace', 'deja' (deja protege), 'absent' (fichier
     * introuvable sur le disque, rien a proteger) ou 'echec'.
     */
    public static function deplacer(int $attachmentId): string
    {
        if (get_post_type($attachmentId) !== 'attachment') {
            return 'absent';
        }

        if (self::estProtege($attachmentId)) {
            self::marquer($attachmentId);
            return 'deja';
        }

        $rel = (string) get_post_meta($attachmentId, '_wp_attached_file', true);
        $uploads = trailingslashit(wp_upload_dir(null, false)['basedir']);
        $source = $uploads . $rel;

        if ($rel === '' || !is_file($source)) {
            return 'absent';
        }

        if (!self::protegerDossier()) {
            return 'echec';
        }

        $relDossier = dirname($rel) === '.' ? '' : dirname($rel) . '/';
        $nouveauRelDossier = self::DOSSIER . '/' . $relDossier;
        $cible = $uploads . $nouveauRelDossier;

        if (!is_dir($cible) && !wp_mkdir_p($cible)) {
            return 'echec';
        }

        // Fichier principal, puis tailles intermediaires et image d'origine
        // (meme dossier, noms relatifs dans les metadonnees).
        $noms = [basename($rel)];
        $meta = wp_get_attachment_metadata($attachmentId);
        if (is_array($meta)) {
            foreach ((array) ($meta['sizes'] ?? []) as $taille) {
                if (!empty($taille['file'])) {
                    $noms[] = (string) $taille['file'];
                }
            }
            if (!empty($meta['original_image'])) {
                $noms[] = (string) $meta['original_image'];
            }
        }

        $nomPrincipal = basename($rel);
        if (is_file($cible . $nomPrincipal)) {
            return 'echec'; // ne jamais ecraser un fichier deja range
        }

        foreach (array_unique($noms) as $nom) {
            $de = $uploads . $relDossier . $nom;
            if (!is_file($de)) {
                continue;
            }
            if (!@rename($de, $cible . $nom)) {
                if ($nom === $nomPrincipal) {
                    return 'echec';
                }
            }
        }

        update_post_meta($attachmentId, '_wp_attached_file', $nouveauRelDossier . $nomPrincipal);
        if (is_array($meta) && isset($meta['file'])) {
            $meta['file'] = $nouveauRelDossier . $nomPrincipal;
            wp_update_attachment_metadata($attachmentId, $meta);
        }
        // guid = ancienne adresse publique : remplace pour ne plus la publier.
        global $wpdb;
        $wpdb->update($wpdb->posts, ['guid' => self::url($attachmentId)], ['ID' => $attachmentId]);
        clean_post_cache($attachmentId);
        self::marquer($attachmentId);

        return 'deplace';
    }

    // ─── Consultation ───────────────────────────────────────────────────────

    public function servir(): void
    {
        if (!is_user_logged_in()) {
            auth_redirect();
        }

        if (!$this->capabilities->currentUserCanManage()) {
            wp_die('Accès réservé aux comptes trésorerie.', 'Accès refusé', ['response' => 403]);
        }

        $id = isset($_GET['id']) ? absint(wp_unslash($_GET['id'])) : 0;
        $chemin = $id > 0 && get_post_type($id) === 'attachment' ? (string) get_attached_file($id) : '';

        // Seuls les fichiers ranges dans uploads/sp-compta/ passent par ici.
        $base = wp_normalize_path(self::base()) . '/';
        $reel = $chemin !== '' ? wp_normalize_path((string) realpath($chemin)) : '';
        if ($reel === '' || !str_starts_with($reel, $base) || !is_file($reel)) {
            wp_die('Fichier introuvable.', 'Introuvable', ['response' => 404]);
        }

        $type = wp_check_filetype($reel);
        $mime = (string) ($type['type'] ?: 'application/octet-stream');
        $enLigne = in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp'], true);

        nocache_headers();
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($reel));
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: ' . ($enLigne ? 'inline' : 'attachment') . '; filename="' . str_replace('"', '', basename($reel)) . '"');
        readfile($reel);
        exit;
    }

    // ─── Exclusion de la mediatheque ────────────────────────────────────────

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public function exclureDeLaRequete(array $args): array
    {
        $metaQuery = isset($args['meta_query']) && is_array($args['meta_query']) ? $args['meta_query'] : [];
        $metaQuery[] = ['key' => self::META, 'compare' => 'NOT EXISTS'];
        $args['meta_query'] = $metaQuery;

        return $args;
    }

    public function exclureDeLaListe(\WP_Query $query): void
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        global $pagenow;
        if ($pagenow !== 'upload.php') {
            return;
        }

        $metaQuery = (array) $query->get('meta_query');
        $metaQuery[] = ['key' => self::META, 'compare' => 'NOT EXISTS'];
        $query->set('meta_query', $metaQuery);
    }
}
