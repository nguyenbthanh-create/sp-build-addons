<?php

declare(strict_types=1);

namespace SpCompta\Admin;

use SpCompta\Billing\ExerciceDeletionGuard;
use SpCompta\Capabilities;
use SpCompta\Entity\Exercice;
use SpCompta\Entity\Parametres;
use SpCompta\Repository\ExerciceRepository;
use SpCompta\Repository\ParametresRepository;

final class ParametresScreen implements AdminScreen
{
    private const SLUG = 'sp-compta-parametres';
    private const ACTION_SAVE_PARAMETRES = 'sp_compta_save_parametres';
    private const ACTION_SAVE_EXERCICE = 'sp_compta_save_exercice';
    private const ACTION_ACTIVATE_EXERCICE = 'sp_compta_activate_exercice';
    private const ACTION_DELETE_EXERCICE = 'sp_compta_delete_exercice';
    private const ACTION_SAVE_ACCES_BUREAU = 'sp_compta_save_acces_bureau';
    private const NONCE = 'sp_compta_parametres_nonce';

    public function __construct(
        private ParametresRepository $parametresRepository,
        private ExerciceRepository $exerciceRepository,
        private Capabilities $capabilities,
        private ExerciceDeletionGuard $exerciceDeletionGuard
    ) {
    }

    public function registerHooks(): void
    {
        add_action('admin_post_' . self::ACTION_SAVE_PARAMETRES, [$this, 'handleSaveParametres']);
        add_action('admin_post_' . self::ACTION_SAVE_EXERCICE, [$this, 'handleSaveExercice']);
        add_action('admin_post_' . self::ACTION_ACTIVATE_EXERCICE, [$this, 'handleActivateExercice']);
        add_action('admin_post_' . self::ACTION_DELETE_EXERCICE, [$this, 'handleDeleteExercice']);
        add_action('admin_post_' . self::ACTION_SAVE_ACCES_BUREAU, [$this, 'handleSaveAccesBureau']);
    }

    public function slug(): string
    {
        return self::SLUG;
    }

    public function title(): string
    {
        return 'Parametres';
    }

    public function menuLabel(): string
    {
        return 'Parametres';
    }

    public function render(): void
    {
        if (!current_user_can(Capabilities::MANAGE_COMPTA)) {
            wp_die(esc_html__('Acces non autorise.', 'sp-compta'));
        }

        $editingExercice = isset($_GET['edit_exercice'])
            ? $this->exerciceRepository->find((int) $_GET['edit_exercice'])
            : null;

        echo '<div class="wrap">';
        echo '<h1>Parametres</h1>';
        $this->renderParametresForm();
        $this->renderExerciceSection($editingExercice);
        $this->renderAccesBureau();
        echo '</div>';
    }

    private function renderAccesBureau(): void
    {
        $bureauUserIds = $this->capabilities->bureauUsers();

        echo '<hr>';
        echo '<h2>Acces bureau</h2>';
        echo '<p>Comptes WordPress ayant acces au plugin, quel que soit leur role (en plus des administrateurs, qui y ont toujours acces).</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field(self::NONCE);
        echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION_SAVE_ACCES_BUREAU) . '">';

        foreach (get_users(['orderby' => 'display_name']) as $user) {
            $checked = in_array((int) $user->ID, $bureauUserIds, true) ? ' checked' : '';
            echo '<label style="display:block;margin:4px 0;">';
            echo '<input type="checkbox" name="bureau_users[]" value="' . esc_attr((string) $user->ID) . '"' . $checked . '> ';
            echo esc_html($user->display_name) . ' (' . esc_html($user->user_email) . ')';
            echo '</label>';
        }

        submit_button('Enregistrer les acces');
        echo '</form>';
    }

    private function renderParametresForm(): void
    {
        $parametres = $this->parametresRepository->get();
        $siret = $parametres !== null ? $parametres->siret() : '';
        $siegeSocial = $parametres !== null ? $parametres->siegeSocial() : '';
        $nomAssociation = $parametres !== null ? $parametres->nomAssociation() : '';
        $logoUrl = $parametres !== null ? $parametres->logoUrl() : '';

        echo '<h2>Association</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field(self::NONCE);
        echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION_SAVE_PARAMETRES) . '">';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th><label for="sp-compta-nom-association">Nom de l\'association</label></th>';
        echo '<td><input type="text" id="sp-compta-nom-association" name="nom_association" value="' . esc_attr($nomAssociation) . '" class="regular-text"></td></tr>';
        echo '<tr><th><label for="sp-compta-siret">SIRET</label></th>';
        echo '<td><input type="text" id="sp-compta-siret" name="siret" value="' . esc_attr($siret) . '" class="regular-text"></td></tr>';
        echo '<tr><th><label for="sp-compta-siege">Siege social</label></th>';
        echo '<td><input type="text" id="sp-compta-siege" name="siege_social" value="' . esc_attr($siegeSocial) . '" class="regular-text"></td></tr>';
        echo '<tr><th><label for="sp-compta-logo">URL du logo</label></th>';
        echo '<td><input type="text" id="sp-compta-logo" name="logo_url" value="' . esc_attr($logoUrl) . '" class="regular-text"></td></tr>';
        echo '</tbody></table>';
        submit_button('Enregistrer les parametres');
        echo '</form>';
    }

    private function renderExerciceSection(?Exercice $editing = null): void
    {
        echo '<hr>';
        echo '<h2>Exercices comptables</h2>';

        $locked = $editing !== null && $this->exerciceDeletionGuard->hasData((int) $editing->id());
        $dateDebut = $editing !== null ? $editing->dateDebut() : '';
        $dateFin = $editing !== null ? $editing->dateFin() : '';
        $soldeInitial = $editing !== null ? (string) $editing->soldeInitial() : '0';

        echo '<h3>' . ($editing !== null ? 'Modifier l\'exercice' : 'Creer un nouvel exercice') . '</h3>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field(self::NONCE);
        echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION_SAVE_EXERCICE) . '">';
        echo '<input type="hidden" name="exercice_id" value="' . esc_attr($editing !== null ? (string) $editing->id() : '') . '">';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th><label for="sp-compta-date-debut">Date de debut</label></th>';
        echo '<td><input type="date" id="sp-compta-date-debut" name="date_debut" value="' . esc_attr($dateDebut) . '"'
            . ($locked ? ' readonly' : '') . ' required></td></tr>';
        echo '<tr><th><label for="sp-compta-date-fin">Date de fin</label></th>';
        echo '<td><input type="date" id="sp-compta-date-fin" name="date_fin" value="' . esc_attr($dateFin) . '"'
            . ($locked ? ' readonly' : '') . ' required></td></tr>';
        if ($locked) {
            echo '<tr><td></td><td><span class="description">';
            echo 'Dates verrouillees : des donnees (depenses, recettes...) sont deja rattachees a cet exercice.';
            echo '</span></td></tr>';
        }
        echo '<tr><th><label for="sp-compta-solde-initial">Solde initial du compte</label></th>';
        echo '<td><input type="number" step="0.01" id="sp-compta-solde-initial" name="solde_initial" value="' . esc_attr($soldeInitial) . '"></td></tr>';
        echo '</tbody></table>';
        submit_button($editing !== null ? 'Mettre a jour l\'exercice' : 'Creer un nouvel exercice');
        if ($editing !== null) {
            echo ' <a class="button" href="' . esc_url(remove_query_arg('edit_exercice')) . '">Annuler</a>';
        }
        echo '</form>';

        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>Debut</th><th>Fin</th><th>Solde initial</th><th>Statut</th><th>Actions</th>';
        echo '</tr></thead><tbody>';

        foreach ($this->exerciceRepository->all() as $exercice) {
            echo '<tr>';
            echo '<td>' . esc_html($exercice->dateDebut()) . '</td>';
            echo '<td>' . esc_html($exercice->dateFin()) . '</td>';
            echo '<td>' . esc_html((string) $exercice->soldeInitial()) . '</td>';

            echo '<td>';
            if ($exercice->actif()) {
                echo '<strong>Actif</strong>';
            } else {
                $activateUrl = wp_nonce_url(
                    add_query_arg(
                        ['action' => self::ACTION_ACTIVATE_EXERCICE, 'id' => $exercice->id()],
                        admin_url('admin-post.php')
                    ),
                    self::NONCE
                );
                echo 'Inactif <a href="' . esc_url($activateUrl) . '">Activer</a>';
            }
            echo '</td>';

            echo '<td>';
            $editUrl = add_query_arg(
                ['page' => self::SLUG, 'edit_exercice' => $exercice->id()],
                admin_url('admin.php')
            );
            echo '<a href="' . esc_url($editUrl) . '">Modifier</a>';

            if ($this->exerciceDeletionGuard->hasData((int) $exercice->id())) {
                echo ' | <span class="description">Non supprimable (donnees presentes)</span>';
            } else {
                $deleteUrl = wp_nonce_url(
                    add_query_arg(
                        ['action' => self::ACTION_DELETE_EXERCICE, 'id' => $exercice->id()],
                        admin_url('admin-post.php')
                    ),
                    self::NONCE
                );
                echo ' | <a href="' . esc_url($deleteUrl) . '" onclick="return confirm(\'Supprimer cet exercice ?\');">Supprimer</a>';
            }
            echo '</td>';

            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    public function handleSaveParametres(): void
    {
        check_admin_referer(self::NONCE);

        if (!current_user_can(Capabilities::MANAGE_COMPTA)) {
            wp_die(esc_html__('Acces non autorise.', 'sp-compta'));
        }

        $this->saveParametresFromRequest($_POST);

        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'saved' => 1], admin_url('admin.php')));
        exit;
    }

    public function handleSaveExercice(): void
    {
        check_admin_referer(self::NONCE);

        if (!current_user_can(Capabilities::MANAGE_COMPTA)) {
            wp_die(esc_html__('Acces non autorise.', 'sp-compta'));
        }

        $this->saveExerciceFromRequest($_POST);

        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'exercice_saved' => 1], admin_url('admin.php')));
        exit;
    }

    public function handleActivateExercice(): void
    {
        check_admin_referer(self::NONCE);

        if (!current_user_can(Capabilities::MANAGE_COMPTA)) {
            wp_die(esc_html__('Acces non autorise.', 'sp-compta'));
        }

        $this->activateExerciceFromRequest($_GET);

        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'exercice_activated' => 1], admin_url('admin.php')));
        exit;
    }

    /**
     * @param array<string, mixed> $request
     */
    public function saveParametresFromRequest(array $request): Parametres
    {
        $parametres = new Parametres(
            null,
            sanitize_text_field(wp_unslash($request['siret'] ?? '')),
            sanitize_text_field(wp_unslash($request['siege_social'] ?? '')),
            sanitize_text_field(wp_unslash($request['nom_association'] ?? '')),
            esc_url_raw(wp_unslash($request['logo_url'] ?? ''))
        );

        return $this->parametresRepository->save($parametres);
    }

    /**
     * Cree un nouvel exercice, ou met a jour un exercice existant si
     * exercice_id est fourni (formulaire "Modifier l'exercice") - le statut
     * actif de l'exercice existant est toujours preserve, une mise a jour ne
     * doit jamais desactiver silencieusement l'exercice en cours.
     *
     * @param array<string, mixed> $request
     */
    public function saveExerciceFromRequest(array $request): Exercice
    {
        $id = isset($request['exercice_id']) && $request['exercice_id'] !== '' ? (int) $request['exercice_id'] : null;
        $existing = $id !== null ? $this->exerciceRepository->find($id) : null;

        $exercice = new Exercice(
            $id,
            sanitize_text_field(wp_unslash($request['date_debut'] ?? '')),
            sanitize_text_field(wp_unslash($request['date_fin'] ?? '')),
            isset($request['solde_initial']) ? (float) $request['solde_initial'] : 0.0,
            $existing !== null ? $existing->actif() : false
        );

        return $this->exerciceRepository->save($exercice);
    }

    /**
     * @param array<string, mixed> $request
     */
    public function activateExerciceFromRequest(array $request): void
    {
        $id = isset($request['id']) ? (int) $request['id'] : 0;

        if ($id > 0) {
            $this->exerciceRepository->activate($id);
        }
    }

    public function handleDeleteExercice(): void
    {
        check_admin_referer(self::NONCE);

        if (!current_user_can(Capabilities::MANAGE_COMPTA)) {
            wp_die(esc_html__('Acces non autorise.', 'sp-compta'));
        }

        $this->deleteExerciceFromRequest($_GET);

        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'exercice_deleted' => 1], admin_url('admin.php')));
        exit;
    }

    /**
     * Ne supprime que si ExerciceDeletionGuard confirme qu'aucune donnee
     * n'est rattachee (voir ExerciceDeletionGuard.md) - retourne false
     * sans rien faire sinon, plutot que d'echouer silencieusement.
     *
     * @param array<string, mixed> $request
     */
    public function deleteExerciceFromRequest(array $request): bool
    {
        $id = isset($request['id']) ? (int) $request['id'] : 0;

        if ($id <= 0) {
            return false;
        }

        return $this->exerciceDeletionGuard->delete($id);
    }

    public function handleSaveAccesBureau(): void
    {
        check_admin_referer(self::NONCE);

        if (!current_user_can(Capabilities::MANAGE_COMPTA)) {
            wp_die(esc_html__('Acces non autorise.', 'sp-compta'));
        }

        $this->saveAccesBureauFromRequest($_POST);

        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'acces_bureau_saved' => 1], admin_url('admin.php')));
        exit;
    }

    /**
     * @param array<string, mixed> $request
     */
    public function saveAccesBureauFromRequest(array $request): void
    {
        $userIds = isset($request['bureau_users']) && is_array($request['bureau_users'])
            ? array_map('intval', $request['bureau_users'])
            : [];

        $this->capabilities->syncBureauUsers($userIds);
    }
}
