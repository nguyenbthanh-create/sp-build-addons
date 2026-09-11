<?php

declare(strict_types=1);

namespace SpCompta\Admin;

use SpCompta\Accounting\Categories;
use SpCompta\Capabilities;
use SpCompta\Entity\Recette;
use SpCompta\Media\AttachmentLink;
use SpCompta\Media\AttachmentUploader;
use SpCompta\Repository\ClientRepository;
use SpCompta\Repository\ExerciceRepository;
use SpCompta\Repository\RecetteRepository;

final class RecetteScreen implements AdminScreen
{
    private const SLUG = 'sp-compta-recettes';
    private const ACTION_SAVE = 'sp_compta_save_recette';
    private const ACTION_DELETE = 'sp_compta_delete_recette';
    private const NONCE = 'sp_compta_recette_nonce';
    private const MODES_PAIEMENT = ['CB', 'cheque', 'especes', 'virement'];

    public function __construct(
        private RecetteRepository $repository,
        private ExerciceRepository $exerciceRepository,
        private ClientRepository $clientRepository,
        private AttachmentUploader $attachmentUploader
    ) {
    }

    public function registerHooks(): void
    {
        add_action('admin_post_' . self::ACTION_SAVE, [$this, 'handleSave']);
        add_action('admin_post_' . self::ACTION_DELETE, [$this, 'handleDelete']);
    }

    /**
     * @return list<string>
     */
    public static function modesPaiement(): array
    {
        return self::MODES_PAIEMENT;
    }

    public function slug(): string
    {
        return self::SLUG;
    }

    public function title(): string
    {
        return 'Recettes';
    }

    public function menuLabel(): string
    {
        return 'Recettes';
    }

    public function render(): void
    {
        if (!current_user_can(Capabilities::MANAGE_COMPTA)) {
            wp_die(esc_html__('Acces non autorise.', 'sp-compta'));
        }

        echo '<div class="wrap"><h1>Recettes</h1>';

        $exerciceActif = $this->exerciceRepository->active();

        if ($exerciceActif === null) {
            echo '<p>Aucun exercice actif. Creez et activez un exercice dans la page Parametres avant de saisir des recettes.</p></div>';

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
        echo '<input type="search" name="s" value="' . esc_attr($term) . '" placeholder="Rechercher une recette...">';
        echo ' <button type="submit" class="button">Rechercher</button>';
        if ($term !== '') {
            echo ' <a class="button" href="' . esc_url(admin_url('admin.php?page=' . self::SLUG)) . '">Reinitialiser</a>';
        }
        echo '</form>';
    }

    private function renderForm(?Recette $recette, int $defaultExerciceId): void
    {
        $id = $recette !== null ? (string) $recette->id() : '';
        $exerciceId = $recette !== null ? $recette->exerciceId() : $defaultExerciceId;
        $date = $recette !== null ? $recette->date() : gmdate('Y-m-d');
        $montant = $recette !== null ? (string) $recette->montant() : '';
        $provenance = $recette !== null ? $recette->provenance() : '';
        $clientId = $recette !== null ? $recette->clientId() : null;
        $sousCategorie = $recette !== null ? $recette->sousCategorie() : '';
        $detail = $recette !== null ? (string) $recette->detail() : '';
        $modePaiement = $recette !== null ? $recette->modePaiement() : '';
        $justificatif = $recette !== null ? $recette->justificatif() : '';

        echo '<h2>' . ($recette !== null ? 'Modifier' : 'Ajouter') . ' une recette</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data">';
        wp_nonce_field(self::NONCE);
        echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION_SAVE) . '">';
        echo '<input type="hidden" name="id" value="' . esc_attr($id) . '">';
        echo '<input type="hidden" name="exercice_id" value="' . esc_attr((string) $exerciceId) . '">';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th><label for="sp-compta-date">Date</label></th>';
        echo '<td><input type="date" id="sp-compta-date" name="date" value="' . esc_attr($date) . '" required></td></tr>';
        echo '<tr><th><label for="sp-compta-montant">Montant</label></th>';
        echo '<td><input type="number" step="0.01" id="sp-compta-montant" name="montant" value="' . esc_attr($montant) . '" required></td></tr>';
        echo '<tr><th><label for="sp-compta-provenance">Provenance</label></th>';
        echo '<td><input type="text" id="sp-compta-provenance" name="provenance" value="' . esc_attr($provenance) . '" class="regular-text" placeholder="Cotisation, buvette..."></td></tr>';
        echo '<tr><th><label for="sp-compta-client">Client lie (optionnel)</label></th><td><select id="sp-compta-client" name="client_id">';
        echo '<option value="">-- Aucun --</option>';
        foreach ($this->clientRepository->all() as $client) {
            $selected = $clientId === $client->id() ? ' selected' : '';
            echo '<option value="' . esc_attr((string) $client->id()) . '"' . $selected . '>' . esc_html($client->nom()) . '</option>';
        }
        echo '</select></td></tr>';
        echo '<tr><th><label for="sp-compta-sous-categorie">Categorie</label></th><td><select id="sp-compta-sous-categorie" name="sous_categorie">';
        echo '<option value="">--</option>';
        foreach (Categories::RECETTE as $code => $definition) {
            echo '<optgroup label="' . esc_attr(Categories::libelleCategorie(Categories::RECETTE, $code)) . '">';
            foreach ($definition['sous_categories'] as $key => $label) {
                $selected = $key === $sousCategorie ? ' selected' : '';
                echo '<option value="' . esc_attr($key) . '"' . $selected . '>' . esc_html($label) . '</option>';
            }
            echo '</optgroup>';
        }
        echo '</select></td></tr>';
        echo '<tr><th><label for="sp-compta-detail">Detail</label></th>';
        echo '<td><textarea id="sp-compta-detail" name="detail" class="large-text" rows="2">' . esc_textarea($detail) . '</textarea></td></tr>';
        echo '<tr><th><label for="sp-compta-mode">Mode de paiement</label></th><td><select id="sp-compta-mode" name="mode_paiement">';
        echo '<option value="">--</option>';
        foreach (self::MODES_PAIEMENT as $mode) {
            $selected = $mode === $modePaiement ? ' selected' : '';
            echo '<option value="' . esc_attr($mode) . '"' . $selected . '>' . esc_html($mode) . '</option>';
        }
        echo '</select></td></tr>';
        echo '<tr><th><label for="sp-compta-justificatif">Justificatif</label></th>';
        echo '<td><input type="file" id="sp-compta-justificatif" name="justificatif">';
        $justificatifLink = AttachmentLink::render($justificatif);
        if ($justificatifLink !== '') {
            echo '<p class="description">Fichier actuel : ' . $justificatifLink . '</p>';
        }
        echo '</td></tr>';
        echo '</tbody></table>';
        submit_button($recette !== null ? 'Mettre a jour' : 'Ajouter');
        echo '</form>';
    }

    private function renderList(int $exerciceId, string $searchTerm): void
    {
        echo '<h2>Recettes de l\'exercice en cours</h2>';
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>Date</th><th>Montant</th><th>Provenance</th><th>Categorie</th><th>Mode</th><th>Justificatif</th><th></th>';
        echo '</tr></thead><tbody>';

        foreach ($this->repository->forExercice($exerciceId) as $recette) {
            $categorieLabel = $recette->categorie() !== ''
                ? Categories::libelleCategorie(Categories::RECETTE, $recette->categorie())
                    . ' · ' . Categories::libelleSousCategorie(Categories::RECETTE, $recette->categorie(), $recette->sousCategorie())
                : '';

            if (!Search::matches($searchTerm, [
                $recette->date(),
                $recette->montant(),
                $recette->provenance(),
                $categorieLabel,
                $recette->detail(),
            ])) {
                continue;
            }

            $editUrl = add_query_arg(['page' => self::SLUG, 'edit' => $recette->id()], admin_url('admin.php'));
            $deleteUrl = wp_nonce_url(
                add_query_arg(
                    ['action' => self::ACTION_DELETE, 'id' => $recette->id()],
                    admin_url('admin-post.php')
                ),
                self::NONCE
            );

            echo '<tr>';
            echo '<td>' . esc_html($recette->date()) . '</td>';
            echo '<td>' . esc_html(number_format($recette->montant(), 2)) . '</td>';
            echo '<td>' . esc_html($recette->provenance()) . '</td>';
            echo '<td>' . esc_html($categorieLabel) . '</td>';
            echo '<td>' . esc_html($recette->modePaiement()) . '</td>';
            echo '<td>' . AttachmentLink::render($recette->justificatif()) . '</td>';
            echo '<td><a href="' . esc_url($editUrl) . '">Modifier</a> | ';
            echo '<a href="' . esc_url($deleteUrl) . '" onclick="return confirm(\'Supprimer cette recette ?\');">Supprimer</a></td>';
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
    public function saveFromRequest(array $request, array $files = []): Recette
    {
        $id = isset($request['id']) && $request['id'] !== '' ? (int) $request['id'] : null;
        $clientId = isset($request['client_id']) && $request['client_id'] !== ''
            ? (int) $request['client_id']
            : null;
        $modePaiement = in_array($request['mode_paiement'] ?? '', self::MODES_PAIEMENT, true)
            ? $request['mode_paiement']
            : '';
        $sousCategorieKey = sanitize_text_field(wp_unslash($request['sous_categorie'] ?? ''));
        $categorieCode = Categories::categorieDeSousCategorie(Categories::RECETTE, $sousCategorieKey);
        $sousCategorie = $categorieCode !== null ? $sousCategorieKey : '';
        $categorie = $categorieCode ?? '';

        $existingJustificatif = '';
        if ($id !== null) {
            $existing = $this->repository->find($id);
            if ($existing !== null) {
                $existingJustificatif = $existing->justificatif();
            }
        }
        $justificatif = $this->attachmentUploader->handle($files, 'justificatif', $existingJustificatif);

        $recette = new Recette(
            $id,
            (int) ($request['exercice_id'] ?? 0),
            sanitize_text_field(wp_unslash($request['date'] ?? '')),
            isset($request['montant']) ? (float) $request['montant'] : 0.0,
            sanitize_text_field(wp_unslash($request['provenance'] ?? '')),
            $clientId,
            $categorie,
            sanitize_textarea_field(wp_unslash($request['detail'] ?? '')),
            $modePaiement,
            $justificatif,
            $sousCategorie
        );

        return $this->repository->save($recette);
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
