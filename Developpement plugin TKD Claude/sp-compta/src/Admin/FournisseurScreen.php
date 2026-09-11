<?php

declare(strict_types=1);

namespace SpCompta\Admin;

use SpCompta\Capabilities;
use SpCompta\Entity\Fournisseur;
use SpCompta\Repository\FournisseurRepository;

final class FournisseurScreen implements AdminScreen
{
    private const SLUG = 'sp-compta-fournisseurs';
    private const ACTION_SAVE = 'sp_compta_save_fournisseur';
    private const ACTION_DELETE = 'sp_compta_delete_fournisseur';
    private const NONCE = 'sp_compta_fournisseur_nonce';

    public function __construct(private FournisseurRepository $repository)
    {
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
        return 'Fournisseurs';
    }

    public function menuLabel(): string
    {
        return 'Fournisseurs';
    }

    public function render(): void
    {
        if (!current_user_can(Capabilities::MANAGE_COMPTA)) {
            wp_die(esc_html__('Acces non autorise.', 'sp-compta'));
        }

        $editing = isset($_GET['edit']) ? $this->repository->find((int) $_GET['edit']) : null;
        $searchTerm = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

        echo '<div class="wrap">';
        echo '<h1>Fournisseurs</h1>';
        $this->renderForm($editing);
        $this->renderSearchBox($searchTerm);
        $this->renderList($searchTerm);
        echo '</div>';
    }

    private function renderSearchBox(string $term): void
    {
        echo '<form method="get" style="margin:1em 0;">';
        echo '<input type="hidden" name="page" value="' . esc_attr(self::SLUG) . '">';
        echo '<input type="search" name="s" value="' . esc_attr($term) . '" placeholder="Rechercher un fournisseur...">';
        echo ' <button type="submit" class="button">Rechercher</button>';
        if ($term !== '') {
            echo ' <a class="button" href="' . esc_url(admin_url('admin.php?page=' . self::SLUG)) . '">Reinitialiser</a>';
        }
        echo '</form>';
    }

    private function renderForm(?Fournisseur $fournisseur): void
    {
        $id = $fournisseur !== null ? (string) $fournisseur->id() : '';
        $nom = $fournisseur !== null ? $fournisseur->nom() : '';
        $adresse = $fournisseur !== null ? $fournisseur->adresse() : '';
        $contact = $fournisseur !== null ? $fournisseur->contact() : '';
        $email = $fournisseur !== null ? $fournisseur->email() : '';
        $telephone = $fournisseur !== null ? $fournisseur->telephone() : '';
        $notes = $fournisseur !== null ? (string) $fournisseur->notes() : '';

        echo '<h2>' . ($fournisseur !== null ? 'Modifier' : 'Ajouter') . ' un fournisseur</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field(self::NONCE);
        echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION_SAVE) . '">';
        echo '<input type="hidden" name="id" value="' . esc_attr($id) . '">';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th><label for="sp-compta-nom">Nom</label></th>';
        echo '<td><input type="text" id="sp-compta-nom" name="nom" value="' . esc_attr($nom) . '" class="regular-text" required></td></tr>';
        echo '<tr><th><label for="sp-compta-adresse">Adresse</label></th>';
        echo '<td><input type="text" id="sp-compta-adresse" name="adresse" value="' . esc_attr($adresse) . '" class="regular-text"></td></tr>';
        echo '<tr><th><label for="sp-compta-contact">Contact</label></th>';
        echo '<td><input type="text" id="sp-compta-contact" name="contact" value="' . esc_attr($contact) . '" class="regular-text"></td></tr>';
        echo '<tr><th><label for="sp-compta-email">Email</label></th>';
        echo '<td><input type="email" id="sp-compta-email" name="email" value="' . esc_attr($email) . '" class="regular-text"></td></tr>';
        echo '<tr><th><label for="sp-compta-telephone">Telephone</label></th>';
        echo '<td><input type="text" id="sp-compta-telephone" name="telephone" value="' . esc_attr($telephone) . '" class="regular-text"></td></tr>';
        echo '<tr><th><label for="sp-compta-notes">Notes</label></th>';
        echo '<td><textarea id="sp-compta-notes" name="notes" class="large-text" rows="3">' . esc_textarea($notes) . '</textarea></td></tr>';
        echo '</tbody></table>';
        submit_button($fournisseur !== null ? 'Mettre a jour' : 'Ajouter');
        echo '</form>';
    }

    private function renderList(string $searchTerm): void
    {
        echo '<h2>Liste des fournisseurs</h2>';
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>Nom</th><th>Contact</th><th>Email</th><th>Telephone</th><th></th>';
        echo '</tr></thead><tbody>';

        foreach ($this->repository->all() as $fournisseur) {
            if (!Search::matches($searchTerm, [
                $fournisseur->nom(),
                $fournisseur->contact(),
                $fournisseur->email(),
                $fournisseur->telephone(),
            ])) {
                continue;
            }

            $editUrl = add_query_arg(
                ['page' => self::SLUG, 'edit' => $fournisseur->id()],
                admin_url('admin.php')
            );
            $deleteUrl = wp_nonce_url(
                add_query_arg(
                    ['action' => self::ACTION_DELETE, 'id' => $fournisseur->id()],
                    admin_url('admin-post.php')
                ),
                self::NONCE
            );

            echo '<tr>';
            echo '<td>' . esc_html($fournisseur->nom()) . '</td>';
            echo '<td>' . esc_html($fournisseur->contact()) . '</td>';
            echo '<td>' . esc_html($fournisseur->email()) . '</td>';
            echo '<td>' . esc_html($fournisseur->telephone()) . '</td>';
            echo '<td><a href="' . esc_url($editUrl) . '">Modifier</a> | ';
            echo '<a href="' . esc_url($deleteUrl) . '" onclick="return confirm(\'Supprimer ce fournisseur ?\');">Supprimer</a></td>';
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

        $this->saveFromRequest($_POST);

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
     * Sanitise et persiste les donnees d'un formulaire fournisseur.
     * Separee de handleSave() pour rester testable sans passer par
     * le nonce/redirect/exit de WordPress.
     *
     * @param array<string, mixed> $request
     */
    public function saveFromRequest(array $request): Fournisseur
    {
        $id = isset($request['id']) && $request['id'] !== '' ? (int) $request['id'] : null;

        $fournisseur = new Fournisseur(
            $id,
            sanitize_text_field(wp_unslash($request['nom'] ?? '')),
            sanitize_text_field(wp_unslash($request['adresse'] ?? '')),
            sanitize_text_field(wp_unslash($request['contact'] ?? '')),
            sanitize_email(wp_unslash($request['email'] ?? '')),
            sanitize_text_field(wp_unslash($request['telephone'] ?? '')),
            sanitize_textarea_field(wp_unslash($request['notes'] ?? ''))
        );

        return $this->repository->save($fournisseur);
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
