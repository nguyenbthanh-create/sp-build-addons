<?php

declare(strict_types=1);

namespace SpCompta\Media;

final class AttachmentLink
{
    /**
     * Rend un lien cliquable vers le fichier si $value est un id
     * d'attachment WordPress valide, sinon affiche $value tel quel
     * (degradation gracieuse pour les references texte libres saisies
     * avant l'upload reel, ex. "ticket-2026-05") ou une chaine vide si
     * rien n'est enregistre.
     */
    public static function render(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (ctype_digit($value) && get_post_type((int) $value) === 'attachment') {
            $url = wp_get_attachment_url((int) $value);
            $filename = basename((string) get_attached_file((int) $value));

            return '<a href="' . esc_url((string) $url) . '" target="_blank" rel="noopener noreferrer">'
                . esc_html($filename !== '' ? $filename : 'Voir le fichier')
                . '</a>';
        }

        return esc_html($value);
    }

    private function __construct()
    {
    }
}
