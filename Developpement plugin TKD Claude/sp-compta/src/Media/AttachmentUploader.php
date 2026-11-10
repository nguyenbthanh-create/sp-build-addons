<?php

declare(strict_types=1);

namespace SpCompta\Media;

final class AttachmentUploader
{
    /**
     * Traite un upload envoye depuis $files[$fieldName] (typiquement
     * $_FILES) et retourne l'id d'attachment WordPress cree, sous forme de
     * chaine (coherent avec les colonnes VARCHAR justificatif/fichier_contrat).
     * Retourne $existingValue inchange si aucun fichier n'a ete choisi
     * (champ laisse vide en modification) ou si l'upload echoue - un envoi
     * rate ne doit jamais faire perdre un fichier deja enregistre.
     *
     * @param array<string, mixed> $files
     */
    public function handle(array $files, string $fieldName, string $existingValue): string
    {
        if (!isset($files[$fieldName]) || ($files[$fieldName]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $existingValue;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        // Range dans uploads/sp-compta/AAAA/MM (ferme au web) au lieu de la
        // mediatheque publique - voir JustificatifStorage.md (10/11/2026).
        if (!JustificatifStorage::protegerDossier()) {
            return $existingValue; // jamais de justificatif depose dans un dossier public
        }

        $filtre = [JustificatifStorage::class, 'filtreUploadDir'];
        add_filter('upload_dir', $filtre);
        try {
            $attachmentId = media_handle_upload($fieldName, 0);
        } finally {
            remove_filter('upload_dir', $filtre);
        }

        if (is_wp_error($attachmentId)) {
            return $existingValue;
        }

        JustificatifStorage::marquer((int) $attachmentId);

        return (string) $attachmentId;
    }
}
