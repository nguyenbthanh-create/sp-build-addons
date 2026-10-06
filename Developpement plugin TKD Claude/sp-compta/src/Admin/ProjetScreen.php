<?php

declare(strict_types=1);

namespace SpCompta\Admin;

use SpCompta\Accounting\Categories;
use SpCompta\Accounting\ProjetBilan;
use SpCompta\Capabilities;
use SpCompta\Entity\Exercice;
use SpCompta\Entity\Projet;
use SpCompta\Repository\DepenseRepository;
use SpCompta\Repository\ExerciceRepository;
use SpCompta\Repository\ProjetRepository;
use SpCompta\Repository\RecetteRepository;

/**
 * Onglet "Projets" : projets de la saison (fete de Noel, materiel, stage...)
 * avec leur budget prevu, et pour chacun le realise (somme des depenses /
 * recettes rattachees) et l'ecart - voir ProjetScreen.md. Les calculs
 * viennent tous de Accounting\ProjetBilan (seule source, aussi utilisee par
 * le rapport AG).
 */
final class ProjetScreen implements AdminScreen
{
    private const SLUG = 'sp-compta-projets';
    private const ACTION_SAVE = 'sp_compta_save_projet';
    private const ACTION_DELETE = 'sp_compta_delete_projet';
    private const ACTION_RATTACHER = 'sp_compta_rattacher_projet';
    private const NONCE = 'sp_compta_projet_nonce';

    public function __construct(
        private ProjetRepository $repository,
        private ExerciceRepository $exerciceRepository,
        private DepenseRepository $depenseRepository,
        private RecetteRepository $recetteRepository
    ) {
    }

    public function registerHooks(): void
    {
        add_action('admin_post_' . self::ACTION_SAVE, [$this, 'handleSave']);
        add_action('admin_post_' . self::ACTION_DELETE, [$this, 'handleDelete']);
        add_action('admin_post_' . self::ACTION_RATTACHER, [$this, 'handleRattacher']);
    }

    public function slug(): string
    {
        return self::SLUG;
    }

    public function title(): string
    {
        return 'Projets';
    }

    public function menuLabel(): string
    {
        return 'Projets';
    }

    // ── Affichage ────────────────────────────────────────────────────────────

    public function render(): void
    {
        if (!current_user_can(Capabilities::MANAGE_COMPTA)) {
            wp_die(esc_html__('Acces non autorise.', 'sp-compta'));
        }

        echo '<div class="wrap"><h1>Projets de la saison</h1>';
        $this->renderStyles();
        $this->renderNotice();

        $projetVu = isset($_GET['projet']) ? $this->repository->find((int) $_GET['projet']) : null;

        if ($projetVu !== null) {
            $exercice = $this->exerciceRepository->find($projetVu->exerciceId());
            if ($exercice !== null) {
                $this->renderFiche($projetVu, $exercice);
                echo '</div>';

                return;
            }
        }

        $exercice = $this->exerciceChoisi();

        if ($exercice === null) {
            echo '<p>Aucun exercice actif. Creez et activez un exercice dans la page Parametres avant de creer des projets.</p></div>';

            return;
        }

        $this->renderChoixExercice($exercice);

        $editing = isset($_GET['edit']) ? $this->repository->find((int) $_GET['edit']) : null;
        if ($editing !== null && $editing->exerciceId() !== (int) $exercice->id()) {
            $editing = null;
        }

        $this->renderTableau($exercice);
        $this->renderForm($editing, (int) $exercice->id());
        echo '</div>';
    }

    private function exerciceChoisi(): ?Exercice
    {
        if (isset($_GET['exercice_id'])) {
            $exercice = $this->exerciceRepository->find((int) $_GET['exercice_id']);
            if ($exercice !== null) {
                return $exercice;
            }
        }

        return $this->exerciceRepository->active();
    }

    private function renderStyles(): void
    {
        echo '<style>
.sp-projets .num{text-align:right;white-space:nowrap;}
.sp-projets .prevu{color:#64748b;font-size:12px;display:block;}
.sp-projets .pos{color:#15803d;font-weight:600;}
.sp-projets .neg{color:#b91c1c;font-weight:600;}
.sp-projets tfoot td{font-weight:700;background:#f6f7f7;}
.sp-projets-resume{display:flex;gap:12px;flex-wrap:wrap;margin:12px 0 18px;}
.sp-projets-resume div{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:10px 16px;min-width:200px;}
.sp-projets-resume strong{display:block;font-size:18px;margin-top:4px;}
.sp-projets-bilan{border-collapse:collapse;background:#fff;margin:10px 0 20px;}
.sp-projets-bilan th,.sp-projets-bilan td{border:1px solid #dcdcde;padding:8px 14px;}
.sp-projets-bilan th{background:#f6f7f7;text-align:left;}
</style>';
    }

    private function renderNotice(): void
    {
        $messages = [
            'saved' => 'Projet enregistre.',
            'deleted' => 'Projet supprime : ses depenses et recettes sont revenues en fonctionnement courant.',
            'rattache' => 'Mouvements mis a jour.',
            'invalide' => 'Le nom du projet est obligatoire.',
        ];
        $cle = isset($_GET['sp_projet_msg']) ? sanitize_key(wp_unslash($_GET['sp_projet_msg'])) : '';

        if (isset($messages[$cle])) {
            $classe = $cle === 'invalide' ? 'notice-error' : 'notice-success';
            echo '<div class="notice ' . $classe . ' is-dismissible"><p>' . esc_html($messages[$cle]) . '</p></div>';
        }
    }

    private function renderChoixExercice(Exercice $exercice): void
    {
        $exercices = $this->exerciceRepository->all();

        if (count($exercices) < 2) {
            return;
        }

        echo '<form method="get" style="margin:8px 0;"><input type="hidden" name="page" value="' . esc_attr(self::SLUG) . '">';
        echo '<label>Saison : <select name="exercice_id" onchange="this.form.submit()">';
        foreach ($exercices as $e) {
            $selected = $e->id() === $exercice->id() ? ' selected' : '';
            echo '<option value="' . esc_attr((string) $e->id()) . '"' . $selected . '>'
                . esc_html(self::periode($e)) . ($e->actif() ? ' (active)' : '') . '</option>';
        }
        echo '</select></label></form>';
    }

    private function renderTableau(Exercice $exercice): void
    {
        $exerciceId = (int) $exercice->id();
        $projets = $this->repository->forExercice($exerciceId);
        $depenses = $this->depenseRepository->forExercice($exerciceId);
        $recettes = $this->recetteRepository->forExercice($exerciceId);
        $bilans = ProjetBilan::pourTous($projets, $depenses, $recettes);
        $repartition = ProjetBilan::repartition($depenses, $recettes);

        echo '<h2>Saison ' . esc_html(self::periode($exercice)) . '</h2>';
        echo '<div class="sp-projets-resume">';
        echo '<div>Fonctionnement courant (hors projets)<strong class="' . self::classeSigne($repartition['fonctionnement_net']) . '">'
            . esc_html(self::euros($repartition['fonctionnement_net'], true)) . '</strong></div>';
        echo '<div>Projets<strong class="' . self::classeSigne($repartition['projets_net']) . '">'
            . esc_html(self::euros($repartition['projets_net'], true)) . '</strong></div>';
        echo '<div>Résultat de la saison<strong class="' . self::classeSigne($repartition['fonctionnement_net'] + $repartition['projets_net']) . '">'
            . esc_html(self::euros($repartition['fonctionnement_net'] + $repartition['projets_net'], true)) . '</strong></div>';
        echo '</div>';

        if ($projets === []) {
            echo '<p>Aucun projet pour cette saison. Ajoutez-en un ci-dessous : il apparaitra ensuite dans le champ « Projet » des depenses et des recettes.</p>';

            return;
        }

        echo '<table class="widefat striped sp-projets"><thead><tr>';
        echo '<th>Projet</th><th>Nature</th><th>Statut</th><th class="num">Recettes<span class="prevu">réalisé / prévu</span></th>'
            . '<th class="num">Dépenses<span class="prevu">réalisé / prévu</span></th><th class="num">Résultat réalisé</th>'
            . '<th class="num">Résultat prévu</th><th class="num">Écart</th><th></th>';
        echo '</tr></thead><tbody>';

        $tot = ['recettes' => 0.0, 'depenses' => 0.0, 'net' => 0.0, 'budget_net' => 0.0, 'ecart_net' => 0.0];

        foreach ($bilans as $b) {
            /** @var Projet $projet */
            $projet = $b['projet'];
            $ficheUrl = add_query_arg(['page' => self::SLUG, 'projet' => $projet->id()], admin_url('admin.php'));
            $editUrl = add_query_arg(['page' => self::SLUG, 'exercice_id' => $exerciceId, 'edit' => $projet->id()], admin_url('admin.php'));
            $deleteUrl = wp_nonce_url(
                add_query_arg(['action' => self::ACTION_DELETE, 'id' => $projet->id()], admin_url('admin-post.php')),
                self::NONCE
            );

            echo '<tr>';
            echo '<td><a href="' . esc_url($ficheUrl) . '"><strong>' . esc_html($projet->nom()) . '</strong></a>'
                . ($projet->responsable() !== '' ? '<br><span class="prevu">' . esc_html($projet->responsable()) . '</span>' : '') . '</td>';
            echo '<td>' . esc_html($projet->natureLabel()) . '</td>';
            echo '<td>' . esc_html($projet->statutLabel()) . '</td>';
            echo '<td class="num">' . esc_html(self::euros($b['recettes'])) . ($b['a_un_budget'] ? '<span class="prevu">' . esc_html(self::euros($b['budget_recettes'])) . '</span>' : '') . '</td>';
            echo '<td class="num">' . esc_html(self::euros($b['depenses'])) . ($b['a_un_budget'] ? '<span class="prevu">' . esc_html(self::euros($b['budget_depenses'])) . '</span>' : '') . '</td>';
            echo '<td class="num ' . self::classeSigne($b['net']) . '">' . esc_html(self::euros($b['net'], true)) . '</td>';
            echo '<td class="num">' . ($b['a_un_budget'] ? esc_html(self::euros($b['budget_net'], true)) : '—') . '</td>';
            echo '<td class="num' . ($b['a_un_budget'] ? ' ' . self::classeSigne($b['ecart_net']) : '') . '">'
                . ($b['a_un_budget'] ? esc_html(self::euros($b['ecart_net'], true)) : '—') . '</td>';
            echo '<td><a href="' . esc_url($ficheUrl) . '">Détail</a> | <a href="' . esc_url($editUrl) . '">Modifier</a> | ';
            echo '<a href="' . esc_url($deleteUrl) . '" onclick="return confirm(\'Supprimer ce projet ? Ses depenses et recettes sont conservees et reviennent en fonctionnement courant.\');">Supprimer</a></td>';
            echo '</tr>';

            $tot['recettes'] += $b['recettes'];
            $tot['depenses'] += $b['depenses'];
            $tot['net'] += $b['net'];
            if ($b['a_un_budget']) {
                $tot['budget_net'] += $b['budget_net'];
                $tot['ecart_net'] += $b['ecart_net'];
            }
        }

        echo '</tbody><tfoot><tr><td colspan="3">Total des projets</td>';
        echo '<td class="num">' . esc_html(self::euros($tot['recettes'])) . '</td>';
        echo '<td class="num">' . esc_html(self::euros($tot['depenses'])) . '</td>';
        echo '<td class="num ' . self::classeSigne($tot['net']) . '">' . esc_html(self::euros($tot['net'], true)) . '</td>';
        echo '<td class="num">' . esc_html(self::euros($tot['budget_net'], true)) . '</td>';
        echo '<td class="num ' . self::classeSigne($tot['ecart_net']) . '">' . esc_html(self::euros($tot['ecart_net'], true)) . '</td><td></td></tr></tfoot>';
        echo '</table>';
        echo '<p class="description">Résultat = recettes − dépenses (négatif : ce que le projet a coûté au club). Écart = réalisé − prévu, '
            . 'uniquement pour les projets dont un budget a été saisi.</p>';
    }

    private function renderForm(?Projet $projet, int $exerciceId): void
    {
        $v = static fn (?string $s): string => esc_attr((string) $s);
        $montant = static fn (float $m): string => $m > 0.0 ? number_format($m, 2, ',', '') : '';

        echo '<h2>' . ($projet !== null ? 'Modifier le projet « ' . esc_html($projet->nom()) . ' »' : 'Ajouter un projet') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field(self::NONCE);
        echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION_SAVE) . '">';
        echo '<input type="hidden" name="id" value="' . esc_attr($projet !== null ? (string) $projet->id() : '') . '">';
        echo '<input type="hidden" name="exercice_id" value="' . esc_attr((string) $exerciceId) . '">';
        echo '<table class="form-table"><tbody>';

        echo '<tr><th><label for="sp-projet-nom">Nom du projet</label></th><td><input type="text" id="sp-projet-nom" name="nom" class="regular-text" required value="'
            . $v($projet?->nom()) . '" placeholder="ex. Fête de Noël 2026"></td></tr>';

        echo '<tr><th><label for="sp-projet-nature">Nature</label></th><td><select id="sp-projet-nature" name="nature">';
        foreach (Projet::NATURES as $cle => $libelle) {
            $selected = ($projet?->nature() ?? 'evenement') === $cle ? ' selected' : '';
            echo '<option value="' . esc_attr($cle) . '"' . $selected . '>' . esc_html($libelle) . '</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><th>Budget prévu</th><td>'
            . '<label>Dépenses prévues <input type="text" inputmode="decimal" name="budget_depenses" value="' . esc_attr($montant($projet?->budgetDepenses() ?? 0.0)) . '" style="width:110px"> €</label>'
            . ' &nbsp; <label>Recettes prévues <input type="text" inputmode="decimal" name="budget_recettes" value="' . esc_attr($montant($projet?->budgetRecettes() ?? 0.0)) . '" style="width:110px"> €</label>'
            . '<p class="description">Facultatif. Sert à comparer le prévu et le réalisé en fin de saison (rapport AG).</p></td></tr>';

        echo '<tr><th><label for="sp-projet-responsable">Responsable</label></th><td><input type="text" id="sp-projet-responsable" name="responsable" class="regular-text" value="'
            . $v($projet?->responsable()) . '" placeholder="Qui porte le projet (facultatif)"></td></tr>';

        echo '<tr><th>Dates</th><td><label>Du <input type="date" name="date_debut" value="' . $v($projet?->dateDebut()) . '"></label>'
            . ' &nbsp; <label>au <input type="date" name="date_fin" value="' . $v($projet?->dateFin()) . '"></label>'
            . '<p class="description">Facultatif. Sert aussi de filtre pour retrouver les mouvements à rattacher.</p></td></tr>';

        echo '<tr><th><label for="sp-projet-description">Description / objectif</label></th><td><textarea id="sp-projet-description" name="description" class="large-text" rows="2">'
            . esc_textarea((string) $projet?->description()) . '</textarea></td></tr>';

        echo '<tr><th><label for="sp-projet-statut">Statut</label></th><td><select id="sp-projet-statut" name="statut">';
        foreach (Projet::STATUTS as $cle => $libelle) {
            $selected = ($projet?->statut() ?? Projet::STATUT_EN_COURS) === $cle ? ' selected' : '';
            echo '<option value="' . esc_attr($cle) . '"' . $selected . '>' . esc_html($libelle) . '</option>';
        }
        echo '</select><p class="description">Un projet clôturé n\'est plus proposé à la saisie, mais reste dans les bilans et le rapport AG.</p></td></tr>';

        echo '</tbody></table>';
        submit_button($projet !== null ? 'Mettre a jour' : 'Ajouter le projet');
        if ($projet !== null) {
            echo '<a class="button" href="' . esc_url(add_query_arg(['page' => self::SLUG, 'exercice_id' => $exerciceId], admin_url('admin.php'))) . '">Annuler</a>';
        }
        echo '</form>';
    }

    private function renderFiche(Projet $projet, Exercice $exercice): void
    {
        $exerciceId = (int) $exercice->id();
        $depensesSaison = $this->depenseRepository->forExercice($exerciceId);
        $recettesSaison = $this->recetteRepository->forExercice($exerciceId);
        $b = ProjetBilan::pour($projet, $depensesSaison, $recettesSaison);
        $retour = add_query_arg(['page' => self::SLUG, 'exercice_id' => $exerciceId], admin_url('admin.php'));
        $edit = add_query_arg(['page' => self::SLUG, 'exercice_id' => $exerciceId, 'edit' => $projet->id()], admin_url('admin.php'));

        echo '<p><a href="' . esc_url($retour) . '">← Tous les projets</a> | <a href="' . esc_url($edit) . '">Modifier le projet</a></p>';
        echo '<h2>' . esc_html($projet->nom()) . ' <span class="prevu" style="display:inline">— ' . esc_html($projet->natureLabel()) . ' · '
            . esc_html($projet->statutLabel()) . ' · saison ' . esc_html(self::periode($exercice)) . '</span></h2>';
        if ($projet->description()) {
            echo '<p>' . esc_html((string) $projet->description()) . '</p>';
        }

        echo '<table class="sp-projets-bilan sp-projets"><thead><tr><th></th><th class="num">Prévu</th><th class="num">Réalisé</th><th class="num">Écart</th></tr></thead><tbody>';
        foreach ([['Recettes', 'recettes'], ['Dépenses', 'depenses'], ['Résultat', 'net']] as [$libelle, $cle]) {
            $prevu = $cle === 'net' ? $b['budget_net'] : $b['budget_' . $cle];
            $signe = $cle === 'net';
            echo '<tr><th>' . esc_html($libelle) . '</th>';
            echo '<td class="num">' . ($b['a_un_budget'] ? esc_html(self::euros($prevu, $signe)) : '—') . '</td>';
            echo '<td class="num' . ($signe ? ' ' . self::classeSigne($b['net']) : '') . '">' . esc_html(self::euros($b[$cle], $signe)) . '</td>';
            echo '<td class="num">' . ($b['a_un_budget'] ? esc_html(self::euros($b['ecart_' . $cle], true)) : '—') . '</td></tr>';
        }
        echo '</tbody></table>';
        if (!$b['a_un_budget']) {
            echo '<p class="description">Aucun budget prévu saisi : seul le réalisé est affiché. Ajoutez un budget en modifiant le projet.</p>';
        }

        // Mouvements rattaches (avec possibilite de les detacher) + mouvements de la saison a rattacher
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field(self::NONCE);
        echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION_RATTACHER) . '">';
        echo '<input type="hidden" name="projet_id" value="' . esc_attr((string) $projet->id()) . '">';

        echo '<h3>Mouvements rattachés au projet (' . (int) $b['nb_mouvements'] . ')</h3>';
        $rattaches = $this->lignes(
            array_filter($depensesSaison, static fn ($d): bool => $d->projetId() === $projet->id()),
            array_filter($recettesSaison, static fn ($r): bool => $r->projetId() === $projet->id())
        );
        if ($rattaches === []) {
            echo '<p>Aucun mouvement rattaché pour l\'instant.</p>';
        } else {
            $this->tableMouvements($rattaches, 'detacher', 'Détacher');
        }

        $du = isset($_GET['du']) ? sanitize_text_field(wp_unslash($_GET['du'])) : (string) $projet->dateDebut();
        $au = isset($_GET['au']) ? sanitize_text_field(wp_unslash($_GET['au'])) : (string) $projet->dateFin();
        $libres = array_values(array_filter(
            $this->lignes(
                array_filter($depensesSaison, static fn ($d): bool => $d->projetId() === null),
                array_filter($recettesSaison, static fn ($r): bool => $r->projetId() === null)
            ),
            static fn (array $l): bool => ($du === '' || $l['date'] >= $du) && ($au === '' || $l['date'] <= $au)
        ));

        echo '<h3>Rattacher des mouvements déjà saisis</h3>';
        echo '<p class="description">Mouvements de la saison encore en « fonctionnement courant »'
            . ($du !== '' || $au !== '' ? ', du ' . esc_html($du !== '' ? self::dateFr($du) : '…') . ' au ' . esc_html($au !== '' ? self::dateFr($au) : '…') : '')
            . '. Cochez ceux qui relèvent de ce projet.</p>';
        if ($libres === []) {
            echo '<p>Aucun mouvement à rattacher sur cette période.</p>';
        } else {
            $this->tableMouvements($libres, 'rattacher', 'Rattacher');
        }

        if ($rattaches !== [] || $libres !== []) {
            submit_button('Enregistrer les rattachements');
        }
        echo '</form>';

        // Filtre de periode (GET, separe du formulaire POST)
        echo '<form method="get" style="margin-top:6px;"><input type="hidden" name="page" value="' . esc_attr(self::SLUG) . '">';
        echo '<input type="hidden" name="projet" value="' . esc_attr((string) $projet->id()) . '">';
        echo 'Période des mouvements proposés : du <input type="date" name="du" value="' . esc_attr($du) . '"> au <input type="date" name="au" value="' . esc_attr($au) . '"> ';
        echo '<button class="button">Filtrer</button> <a class="button" href="' . esc_url(add_query_arg(['page' => self::SLUG, 'projet' => $projet->id(), 'du' => '', 'au' => ''], admin_url('admin.php'))) . '">Toute la saison</a></form>';
    }

    /**
     * @param iterable<\SpCompta\Entity\Depense> $depenses
     * @param iterable<\SpCompta\Entity\Recette> $recettes
     * @return array<int, array{type: string, id: int, date: string, libelle: string, detail: string, montant: float}>
     */
    private function lignes(iterable $depenses, iterable $recettes): array
    {
        $lignes = [];

        foreach ($depenses as $d) {
            $lignes[] = [
                'type' => 'depense',
                'id' => (int) $d->id(),
                'date' => $d->date(),
                'libelle' => $d->categorie() !== '' ? Categories::libelleSousCategorie(Categories::DEPENSE, $d->categorie(), $d->sousCategorie()) : 'Non categorise',
                'detail' => (string) $d->detail(),
                'montant' => -$d->montant(),
            ];
        }

        foreach ($recettes as $r) {
            $lignes[] = [
                'type' => 'recette',
                'id' => (int) $r->id(),
                'date' => $r->date(),
                'libelle' => trim(($r->provenance() !== '' ? $r->provenance() . ' · ' : '')
                    . ($r->categorie() !== '' ? Categories::libelleSousCategorie(Categories::RECETTE, $r->categorie(), $r->sousCategorie()) : '')),
                'detail' => (string) $r->detail(),
                'montant' => $r->montant(),
            ];
        }

        usort($lignes, static fn (array $a, array $b): int => strcmp($b['date'], $a['date']));

        return $lignes;
    }

    /**
     * @param array<int, array{type: string, id: int, date: string, libelle: string, detail: string, montant: float}> $lignes
     */
    private function tableMouvements(array $lignes, string $prefixe, string $libelleCase): void
    {
        echo '<table class="widefat striped sp-projets" style="margin-bottom:10px;"><thead><tr><th style="width:80px">' . esc_html($libelleCase)
            . '</th><th>Date</th><th>Type</th><th>Catégorie / provenance</th><th>Détail</th><th class="num">Montant</th></tr></thead><tbody>';

        foreach ($lignes as $l) {
            $nom = $prefixe . '_' . $l['type'] . 's[]';
            echo '<tr><td><input type="checkbox" name="' . esc_attr($nom) . '" value="' . esc_attr((string) $l['id']) . '"></td>';
            echo '<td>' . esc_html(self::dateFr($l['date'])) . '</td>';
            echo '<td>' . ($l['type'] === 'depense' ? 'Dépense' : 'Recette') . '</td>';
            echo '<td>' . esc_html($l['libelle']) . '</td>';
            echo '<td>' . esc_html($l['detail']) . '</td>';
            echo '<td class="num ' . self::classeSigne($l['montant']) . '">' . esc_html(self::euros($l['montant'], true)) . '</td></tr>';
        }

        echo '</tbody></table>';
    }

    // ── Actions ──────────────────────────────────────────────────────────────

    public function handleSave(): void
    {
        check_admin_referer(self::NONCE);

        if (!current_user_can(Capabilities::MANAGE_COMPTA)) {
            wp_die(esc_html__('Acces non autorise.', 'sp-compta'));
        }

        $projet = $this->saveFromRequest($_POST);
        $exerciceId = (int) ($_POST['exercice_id'] ?? 0);

        wp_safe_redirect(add_query_arg(
            ['page' => self::SLUG, 'exercice_id' => $exerciceId, 'sp_projet_msg' => $projet !== null ? 'saved' : 'invalide'],
            admin_url('admin.php')
        ));
        exit;
    }

    public function handleDelete(): void
    {
        check_admin_referer(self::NONCE);

        if (!current_user_can(Capabilities::MANAGE_COMPTA)) {
            wp_die(esc_html__('Acces non autorise.', 'sp-compta'));
        }

        $projet = $this->repository->find((int) ($_GET['id'] ?? 0));
        $this->deleteFromRequest($_GET);

        wp_safe_redirect(add_query_arg(
            ['page' => self::SLUG, 'exercice_id' => $projet !== null ? $projet->exerciceId() : 0, 'sp_projet_msg' => 'deleted'],
            admin_url('admin.php')
        ));
        exit;
    }

    public function handleRattacher(): void
    {
        check_admin_referer(self::NONCE);

        if (!current_user_can(Capabilities::MANAGE_COMPTA)) {
            wp_die(esc_html__('Acces non autorise.', 'sp-compta'));
        }

        $projetId = (int) ($_POST['projet_id'] ?? 0);
        $this->rattacherFromRequest($_POST);

        wp_safe_redirect(add_query_arg(
            ['page' => self::SLUG, 'projet' => $projetId, 'sp_projet_msg' => 'rattache'],
            admin_url('admin.php')
        ));
        exit;
    }

    /**
     * Cree ou met a jour un projet a partir du formulaire. Retourne null (rien
     * enregistre) si le nom est vide ou l'exercice inconnu.
     *
     * @param array<string, mixed> $request
     */
    public function saveFromRequest(array $request): ?Projet
    {
        $id = isset($request['id']) && $request['id'] !== '' ? (int) $request['id'] : null;
        $exerciceId = (int) ($request['exercice_id'] ?? 0);
        $nom = sanitize_text_field(wp_unslash((string) ($request['nom'] ?? '')));

        if ($nom === '' || $exerciceId <= 0) {
            return null;
        }

        if ($id !== null) {
            $existant = $this->repository->find($id);
            if ($existant === null) {
                return null;
            }
            // Un projet ne change jamais de saison (ses mouvements y sont rattaches).
            $exerciceId = $existant->exerciceId();
        }

        $nature = sanitize_key((string) ($request['nature'] ?? ''));
        $statut = sanitize_key((string) ($request['statut'] ?? ''));
        $date = static function ($valeur): ?string {
            $d = sanitize_text_field(wp_unslash((string) $valeur));

            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1 ? $d : null;
        };
        $description = sanitize_textarea_field(wp_unslash((string) ($request['description'] ?? '')));

        return $this->repository->save(new Projet(
            $id,
            $exerciceId,
            $nom,
            isset(Projet::NATURES[$nature]) ? $nature : 'autre',
            ProjetBilan::montantSaisi($request['budget_depenses'] ?? ''),
            ProjetBilan::montantSaisi($request['budget_recettes'] ?? ''),
            sanitize_text_field(wp_unslash((string) ($request['responsable'] ?? ''))),
            $date($request['date_debut'] ?? ''),
            $date($request['date_fin'] ?? ''),
            $description !== '' ? $description : null,
            isset(Projet::STATUTS[$statut]) ? $statut : Projet::STATUT_EN_COURS
        ));
    }

    /**
     * Supprime le projet ; ses depenses et recettes sont conservees et
     * reviennent en fonctionnement courant (jamais de perte de montant).
     *
     * @param array<string, mixed> $request
     */
    public function deleteFromRequest(array $request): bool
    {
        $id = isset($request['id']) ? (int) $request['id'] : 0;

        if ($id <= 0 || $this->repository->find($id) === null) {
            return false;
        }

        $this->depenseRepository->detacherDuProjet($id);
        $this->recetteRepository->detacherDuProjet($id);

        return $this->repository->delete($id);
    }

    /**
     * Rattache les mouvements coches au projet, et detache ceux decoches de la
     * liste "rattaches". Toujours limite a la saison du projet.
     *
     * @param array<string, mixed> $request
     * @return array{rattaches: int, detaches: int}
     */
    public function rattacherFromRequest(array $request): array
    {
        $projet = $this->repository->find((int) ($request['projet_id'] ?? 0));

        if ($projet === null) {
            return ['rattaches' => 0, 'detaches' => 0];
        }

        $exerciceId = $projet->exerciceId();
        $ids = static fn (string $cle): array => array_map('intval', (array) ($request[$cle] ?? []));

        $rattaches = $this->depenseRepository->rattacherAuProjet($ids('rattacher_depenses'), (int) $projet->id(), $exerciceId)
            + $this->recetteRepository->rattacherAuProjet($ids('rattacher_recettes'), (int) $projet->id(), $exerciceId);

        // Detacher : seulement des lignes reellement rattachees a CE projet.
        $aDetacherDepenses = array_intersect(
            $ids('detacher_depenses'),
            array_map(static fn ($d): int => (int) $d->id(), $this->depenseRepository->forProjet((int) $projet->id()))
        );
        $aDetacherRecettes = array_intersect(
            $ids('detacher_recettes'),
            array_map(static fn ($r): int => (int) $r->id(), $this->recetteRepository->forProjet((int) $projet->id()))
        );
        $detaches = $this->depenseRepository->rattacherAuProjet($aDetacherDepenses, null, $exerciceId)
            + $this->recetteRepository->rattacherAuProjet($aDetacherRecettes, null, $exerciceId);

        return ['rattaches' => $rattaches, 'detaches' => $detaches];
    }

    // ── Formatage ────────────────────────────────────────────────────────────

    private static function euros(float $montant, bool $signe = false): string
    {
        $texte = number_format(abs($montant), 2, ',', ' ') . ' €';

        if (!$signe || abs($montant) < 0.005) {
            return $texte;
        }

        return ($montant > 0 ? '+ ' : '− ') . $texte;
    }

    private static function classeSigne(float $montant): string
    {
        if (abs($montant) < 0.005) {
            return '';
        }

        return $montant > 0 ? 'pos' : 'neg';
    }

    private static function periode(Exercice $exercice): string
    {
        return self::dateFr($exercice->dateDebut()) . ' – ' . self::dateFr($exercice->dateFin());
    }

    private static function dateFr(string $ymd): string
    {
        $d = date_create($ymd);

        return $d !== false ? $d->format('d/m/Y') : $ymd;
    }
}
