<?php

declare(strict_types=1);

namespace SpCompta\Admin;

use SpCompta\Capabilities;
use SpCompta\Entity\Client;
use SpCompta\Repository\ClientRepository;

final class ClientScreen implements AdminScreen
{
    private const SLUG = 'sp-compta-clients';
    private const ACTION_SAVE = 'sp_compta_save_client';
    private const ACTION_DELETE = 'sp_compta_delete_client';
    private const NONCE = 'sp_compta_client_nonce';
    private const TYPES = ['particulier', 'collectivite'];

    public function __construct(private ClientRepository $repository)
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
        return 'Clients';
    }

    public function menuLabel(): string
    {
        return 'Clients';
    }

    public function render(): void
    {
        if (!current_user_can(Capabilities::MANAGE_COMPTA)) {
            wp_die(esc_html__('Acces non autorise.', 'sp-compta'));
        }

        $editing = isset($_GET['edit']) ? $this->repository->find((int) $_GET['edit']) : null;
        $searchTerm = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

        echo '<div class="wrap">';
        echo '<h1>Clients</h1>';
        $this->renderForm($editing);
        $this->renderSearchBox($searchTerm);
        $this->renderList($searchTerm);
        echo '</div>';
    }

    private function renderSearchBox(string $term): void
    {
        echo '<form method="get" style="margin:1em 0;">';
        echo '<input type="hidden" name="page" value="' . esc_attr(self::SLUG) . '">';
        echo '<input type="search" name="s" value="' . esc_attr($term) . '" placeholder="Rechercher un client...">';
        echo ' <button type="submit" class="button">Rechercher</button>';
        if ($term !== '') {
            echo ' <a class="button" href="' . esc_url(admin_url('admin.php?page=' . self::SLUG)) . '">Reinitialiser</a>';
        }
        echo '</form>';
    }

    private function renderForm(?Client $client): void
    {
        $id = $client !== null ? (string) $client->id() : '';
        $type = $client !== null ? $client->type() : 'particulier';
        $nom = $client !== null ? $client->nom() : '';
        $adresse = $client !== null ? $client->adresse() : '';
        $codePostal = $client !== null ? $client->codePostal() : '';
        $ville = $client !== null ? $client->ville() : '';
        $email = $client !== null ? $client->email() : '';
        $telephone = $client !== null ? $client->telephone() : '';
        $notes = $client !== null ? (string) $client->notes() : '';

        echo '<h2>' . ($client !== null ? 'Modifier' : 'Ajouter') . ' un client</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field(self::NONCE);
        echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION_SAVE) . '">';
        echo '<input type="hidden" name="id" value="' . esc_attr($id) . '">';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th><label for="sp-compta-type">Type</label></th><td><select id="sp-compta-type" name="type">';
        foreach (self::TYPES as $option) {
            $selected = $option === $type ? ' selected' : '';
            $label = $option === 'particulier' ? 'Particulier' : 'Collectivite';
            echo '<option value="' . esc_attr($option) . '"' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr>';
        echo '<tr><th><label for="sp-compta-nom">Nom</label></th>';
        echo '<td><input type="text" id="sp-compta-nom" name="nom" value="' . esc_attr($nom) . '" class="regular-text" required></td></tr>';
        echo '<tr><th><label for="sp-compta-adresse">Adresse</label></th>';
        echo '<td><input type="text" id="sp-compta-adresse" name="adresse" value="' . esc_attr($adresse) . '" class="regular-text"></td></tr>';
        echo '<tr><th><label for="sp-compta-code-postal">Code postal</label></th>';
        echo '<td><input type="text" id="sp-compta-code-postal" name="code_postal" value="' . esc_attr($codePostal) . '" class="small-text"></td></tr>';
        echo '<tr><th><label for="sp-compta-ville">Ville</label></th>';
        echo '<td><input type="text" id="sp-compta-ville" name="ville" value="' . esc_attr($ville) . '" class="regular-text"></td></tr>';
        echo '<tr><th><label for="sp-compta-email">Email</label></th>';
        echo '<td><input type="email" id="sp-compta-email" name="email" value="' . esc_attr($email) . '" class="regular-text"></td></tr>';
        echo '<tr><th><label for="sp-compta-telephone">Telephone</label></th>';
        echo '<td><input type="text" id="sp-compta-telephone" name="telephone" value="' . esc_attr($telephone) . '" class="regular-text"></td></tr>';
        echo '<tr><th><label for="sp-compta-notes">Notes</label></th>';
        echo '<td><textarea id="sp-compta-notes" name="notes" class="large-text" rows="3">' . esc_textarea($notes) . '</textarea></td></tr>';
        echo '</tbody></table>';
        submit_button($client !== null ? 'Mettre a jour' : 'Ajouter');
        echo '</form>';
    }

    private function renderList(string $searchTerm): void
    {
        echo '<h2>Liste des clients</h2>';
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>Type</th><th>Nom</th><th>Ville</th><th>Email</th><th>Telephone</th><th></th>';
        echo '</tr></thead><tbody>';

        foreach ($this->repository->all() as $client) {
            if (!Search::matches($searchTerm, [
                $client->nom(),
                $client->ville(),
                $client->email(),
                $client->telephone(),
            ])) {
                continue;
            }

            $editUrl = add_query_arg(['page' => self::SLUG, 'edit' => $client->id()], admin_url('admin.php'));
            $deleteUrl = wp_nonce_url(
                add_query_arg(
                    ['action' => self::ACTION_DELETE, 'id' => $client->id()],
                    admin_url('admin-post.php')
                ),
                self::NONCE
            );

            echo '<tr>';
            echo '<td>' . esc_html($client->type() === 'collectivite' ? 'Collectivite' : 'Particulier') . '</td>';
            echo '<td>' . esc_html($client->nom()) . '</td>';
            echo '<td>' . esc_html($client->ville()) . '</td>';
            echo '<td>' . esc_html($client->email()) . '</td>';
            echo '<td>' . esc_html($client->telephone()) . '</td>';
            echo '<td><a href="' . esc_url($editUrl) . '">Modifier</a> | ';
            echo '<a href="' . esc_url($deleteUrl) . '" onclick="return confirm(\'Supprimer ce client ?\');">Supprimer</a></td>';
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
     * @param array<string, mixed> $request
     */
    public function saveFromRequest(array $request): Client
    {
        $id = isset($request['id']) && $request['id'] !== '' ? (int) $request['id'] : null;
        $type = in_array($request['type'] ?? '', self::TYPES, true) ? $request['type'] : 'particulier';

        $client = new Client(
            $id,
            sanitize_text_field(wp_unslash($request['nom'] ?? '')),
            $type,
            sanitize_text_field(wp_unslash($request['adresse'] ?? '')),
            sanitize_text_field(wp_unslash($request['code_postal'] ?? '')),
            sanitize_text_field(wp_unslash($request['ville'] ?? '')),
            sanitize_email(wp_unslash($request['email'] ?? '')),
            sanitize_text_field(wp_unslash($request['telephone'] ?? '')),
            sanitize_textarea_field(wp_unslash($request['notes'] ?? ''))
        );

        return $this->repository->save($client);
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
