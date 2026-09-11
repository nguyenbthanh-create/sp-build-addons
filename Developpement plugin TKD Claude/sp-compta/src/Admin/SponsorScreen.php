<?php

declare(strict_types=1);

namespace SpCompta\Admin;

use SpCompta\Billing\SponsorPaiementSync;
use SpCompta\Capabilities;
use SpCompta\Entity\Sponsor;
use SpCompta\Media\AttachmentLink;
use SpCompta\Media\AttachmentUploader;
use SpCompta\Repository\ExerciceRepository;
use SpCompta\Repository\SponsorRepository;

final class SponsorScreen implements AdminScreen
{
    private const SLUG = 'sp-compta-sponsors';
    private const ACTION_SAVE = 'sp_compta_save_sponsor';
    private const ACTION_DELETE = 'sp_compta_delete_sponsor';
    private const NONCE = 'sp_compta_sponsor_nonce';
    private const TYPES_PAIEMENT = ['numeraire', 'nature', 'competence'];

    public function __construct(
        private SponsorRepository $repository,
        private ExerciceRepository $exerciceRepository,
        private SponsorPaiementSync $paiementSync,
        private AttachmentUploader $attachmentUploader
    ) {
    }

    public function registerHooks(): void
    {
        add_action('admin_post_' . self::ACTION_SAVE, [$this, 'handleSave']);
        add_action('admin_post_' . self::ACTION_DELETE, [$this, 'handleDelete']);
    }

    public function slug(): string
    {
        return self::SLUG;
    }

    public function title(): string
    {
        return 'Sponsors';
    }

    public function menuLabel(): string
    {
        return 'Sponsors';
    }

    public function render(): void
    {
        if (!current_user_can(Capabilities::MANAGE_COMPTA)) {
            wp_die(esc_html__('Acces non autorise.', 'sp-compta'));
        }

        echo '<div class="wrap"><h1>Sponsors</h1>';

        $exerciceActif = $this->exerciceRepository->active();

        if ($exerciceActif === null) {
            echo '<p>Aucun exercice actif. Creez et activez un exercice dans la page Parametres avant de saisir des sponsors.</p></div>';

            return;
        }

        $editing = isset($_GET['edit']) ? $this->repository->find((int) $_GET['edit']) : null;
        $searchTerm = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

        $this->renderForm($editing, (int) $exerciceActif->id());
        $this->renderSearchBox($searchTerm);
        $this->renderList((int) $exerciceActif->id(), $searchTerm);
        echo '</div>';
    }

    private function renderSearchBox(string $term): void
    {
        echo '<form method="get" style="margin:1em 0;">';
        echo '<input type="hidden" name="page" value="' . esc_attr(self::SLUG) . '">';
        echo '<input type="search" name="s" value="' . esc_attr($term) . '" placeholder="Rechercher un sponsor...">';
        echo ' <button type="submit" class="button">Rechercher</button>';
        if ($term !== '') {
            echo ' <a class="button" href="' . esc_url(admin_url('admin.php?page=' . self::SLUG)) . '">Reinitialiser</a>';
        }
        echo '</form>';
    }

    private function renderForm(?Sponsor $sponsor, int $defaultExerciceId): void
    {
        $id = $sponsor !== null ? (string) $sponsor->id() : '';
        $exerciceId = $sponsor !== null ? $sponsor->exerciceId() : $defaultExerciceId;
        $recetteId = $sponsor !== null ? $sponsor->recetteId() : null;
        $nom = $sponsor !== null ? $sponsor->nom() : '';
        $montant = $sponsor !== null ? (string) $sponsor->montant() : '';
        $date = $sponsor !== null ? $sponsor->date() : gmdate('Y-m-d');
        $typePaiement = $sponsor !== null ? $sponsor->typePaiement() : 'numeraire';
        $contratSigne = $sponsor !== null && $sponsor->contratSigne();
        $contratPaye = $sponsor !== null && $sponsor->contratPaye();
        $fichierContrat = $sponsor !== null ? $sponsor->fichierContrat() : '';

        echo '<h2>' . ($sponsor !== null ? 'Modifier' : 'Ajouter') . ' un sponsor</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data">';
        wp_nonce_field(self::NONCE);
        echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION_SAVE) . '">';
        echo '<input type="hidden" name="id" value="' . esc_attr($id) . '">';
        echo '<input type="hidden" name="exercice_id" value="' . esc_attr((string) $exerciceId) . '">';
        echo '<input type="hidden" name="recette_id" value="' . esc_attr($recetteId !== null ? (string) $recetteId : '') . '">';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th><label for="sp-compta-nom">Nom</label></th>';
        echo '<td><input type="text" id="sp-compta-nom" name="nom" value="' . esc_attr($nom) . '" class="regular-text" required></td></tr>';
        echo '<tr><th><label for="sp-compta-montant">Montant</label></th>';
        echo '<td><input type="number" step="0.01" id="sp-compta-montant" name="montant" value="' . esc_attr($montant) . '" required></td></tr>';
        echo '<tr><th><label for="sp-compta-date">Date</label></th>';
        echo '<td><input type="date" id="sp-compta-date" name="date" value="' . esc_attr($date) . '" required></td></tr>';
        echo '<tr><th><label for="sp-compta-type-paiement">Type de paiement</label></th><td><select id="sp-compta-type-paiement" name="type_paiement">';
        foreach (self::TYPES_PAIEMENT as $type) {
            $selected = $type === $typePaiement ? ' selected' : '';
            echo '<option value="' . esc_attr($type) . '"' . $selected . '>' . esc_html($type) . '</option>';
        }
        echo '</select></td></tr>';
        echo '<tr><th><label for="sp-compta-contrat-signe">Contrat signe</label></th>';
        echo '<td><input type="checkbox" id="sp-compta-contrat-signe" name="contrat_signe" value="1"' . ($contratSigne ? ' checked' : '') . '></td></tr>';
        echo '<tr><th><label for="sp-compta-contrat-paye">Contrat paye</label></th>';
        echo '<td><input type="checkbox" id="sp-compta-contrat-paye" name="contrat_paye" value="1"' . ($contratPaye ? ' checked' : '') . '>';
        echo ' <span class="description">Coche : bascule automatiquement le montant dans les Recettes.</span></td></tr>';
        echo '<tr><th><label for="sp-compta-fichier-contrat">Contrat signe (fichier)</label></th>';
        echo '<td><input type="file" id="sp-compta-fichier-contrat" name="fichier_contrat">';
        $fichierContratLink = AttachmentLink::render($fichierContrat);
        if ($fichierContratLink !== '') {
            echo '<p class="description">Fichier actuel : ' . $fichierContratLink . '</p>';
        }
        echo '</td></tr>';
        echo '</tbody></table>';
        submit_button($sponsor !== null ? 'Mettre a jour' : 'Ajouter');
        echo '</form>';
    }

    private function renderList(int $exerciceId, string $searchTerm): void
    {
        echo '<h2>Sponsors de l\'exercice en cours</h2>';
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>Nom</th><th>Montant</th><th>Type</th><th>Contrat</th><th>Paye</th><th>Fichier</th><th></th>';
        echo '</tr></thead><tbody>';

        foreach ($this->repository->forExercice($exerciceId) as $sponsor) {
            if (!Search::matches($searchTerm, [
                $sponsor->nom(),
                $sponsor->montant(),
                $sponsor->date(),
                $sponsor->typePaiement(),
            ])) {
                continue;
            }

            $editUrl = add_query_arg(['page' => self::SLUG, 'edit' => $sponsor->id()], admin_url('admin.php'));
            $deleteUrl = wp_nonce_url(
                add_query_arg(
                    ['action' => self::ACTION_DELETE, 'id' => $sponsor->id()],
                    admin_url('admin-post.php')
                ),
                self::NONCE
            );

            echo '<tr>';
            echo '<td>' . esc_html($sponsor->nom()) . '</td>';
            echo '<td>' . esc_html(number_format($sponsor->montant(), 2)) . '</td>';
            echo '<td>' . esc_html($sponsor->typePaiement()) . '</td>';
            echo '<td>' . ($sponsor->contratSigne() ? 'Oui' : 'Non') . '</td>';
            echo '<td>' . ($sponsor->contratPaye() ? 'Oui' : 'Non') . '</td>';
            echo '<td>' . AttachmentLink::render($sponsor->fichierContrat()) . '</td>';
            echo '<td><a href="' . esc_url($editUrl) . '">Modifier</a> | ';
            echo '<a href="' . esc_url($deleteUrl) . '" onclick="return confirm(\'Supprimer ce sponsor ?\');">Supprimer</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    public function handleSave(): void
    {
        check_admin_referer(self::NONCE);

        if (!current_user_can(Capabilities::MANAGE_COMPTA)) {
            wp_die(esc_html__('Acces non autorise.', 'sp-compta'));
        }

        $this->saveFromRequest($_POST, $_FILES);

        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'saved' => 1], admin_url('admin.php')));
        exit;
    }

    public function handleDelete(): void
    {
        check_admin_referer(self::NONCE);

        if (!current_user_can(Capabilities::MANAGE_COMPTA)) {
            wp_die(esc_html__('Acces non autorise.', 'sp-compta'));
        }

        $this->deleteFromRequest($_GET);

        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'deleted' => 1], admin_url('admin.php')));
        exit;
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $files
     */
    public function saveFromRequest(array $request, array $files = []): Sponsor
    {
        $id = isset($request['id']) && $request['id'] !== '' ? (int) $request['id'] : null;
        $recetteId = isset($request['recette_id']) && $request['recette_id'] !== ''
            ? (int) $request['recette_id']
            : null;
        $typePaiement = in_array($request['type_paiement'] ?? '', self::TYPES_PAIEMENT, true)
            ? $request['type_paiement']
            : 'numeraire';

        $existingFichierContrat = '';
        if ($id !== null) {
            $existing = $this->repository->find($id);
            if ($existing !== null) {
                $existingFichierContrat = $existing->fichierContrat();
            }
        }
        $fichierContrat = $this->attachmentUploader->handle($files, 'fichier_contrat', $existingFichierContrat);

        $sponsor = new Sponsor(
            $id,
            (int) ($request['exercice_id'] ?? 0),
            sanitize_text_field(wp_unslash($request['nom'] ?? '')),
            isset($request['montant']) ? (float) $request['montant'] : 0.0,
            sanitize_text_field(wp_unslash($request['date'] ?? '')),
            $typePaiement,
            !empty($request['contrat_signe']),
            $fichierContrat,
            !empty($request['contrat_paye']),
            $recetteId
        );

        $saved = $this->repository->save($sponsor);

        return $this->paiementSync->sync($saved);
    }

    /**
     * @param array<string, mixed> $request
     */
    public function deleteFromRequest(array $request): bool
    {
        $id = isset($request['id']) ? (int) $request['id'] : 0;

        if ($id <= 0) {
            return false;
        }

        return $this->repository->delete($id);
    }
}
