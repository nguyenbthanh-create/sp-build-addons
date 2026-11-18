<?php

declare(strict_types=1);

namespace SpCompta\Integration;

use SpCompta\Accounting\Categories;
use SpCompta\Admin\DepenseScreen;
use SpCompta\Accounting\ProjetBilan;
use SpCompta\Capabilities;
use SpCompta\Entity\BudgetLigne;
use SpCompta\Entity\Exercice;
use SpCompta\Entity\Projet;
use SpCompta\Media\AttachmentUploader;
use SpCompta\Plugin;
use SpCompta\Repository\BudgetLigneRepository;
use SpCompta\Repository\DepenseRepository;
use SpCompta\Repository\ExerciceRepository;
use SpCompta\Repository\FournisseurRepository;
use SpCompta\Repository\ProjetRepository;
use SpCompta\Repository\RecetteRepository;

/**
 * Porte d'entree documentee de SP Compta pour l'extension TKD Projets
 * (18/11/2026) : projets compta, lignes de budget, bilan, mouvements. TKD
 * Projets ne lit ni n'ecrit jamais les tables de SP Compta directement.
 * Tout est reserve au bureau (sp_compta_manager) - voir ProjetsApi.md.
 */
final class ProjetsApi
{
    public const VERSION = '1.0';

    public const MAX_LIGNES = 100;

    public function __construct(
        private ProjetRepository $projets,
        private BudgetLigneRepository $lignes,
        private ExerciceRepository $exercices,
        private DepenseRepository $depenses,
        private RecetteRepository $recettes,
        private ?DepenseScreen $depenseScreen = null
    ) {
    }

    /** Instance branchee sur les tables du site (point d'entree pour les autres extensions). */
    public static function depuisPlugin(): self
    {
        $db = Plugin::getInstance()->database();
        $db->maybeUpgrade(); // la table des lignes doit exister meme si aucune page wp-admin n'a encore ete ouverte

        $projets = new ProjetRepository($db->tableProjet());
        $exercices = new ExerciceRepository($db->tableExercice());
        $depenses = new DepenseRepository($db->tableDepense());

        return new self(
            $projets,
            new BudgetLigneRepository($db->tableBudgetLigne()),
            $exercices,
            $depenses,
            new RecetteRepository($db->tableRecette()),
            // Meme chemin que l'ecran Depenses et la saisie rapide (justificatif protege compris).
            new DepenseScreen($depenses, $exercices, new FournisseurRepository($db->tableFournisseur()), new AttachmentUploader(), $projets)
        );
    }

    /** La personne connectee fait-elle partie du bureau (droit de voir et de modifier les budgets) ? */
    public function peutConsulter(): bool
    {
        return current_user_can(Capabilities::MANAGE_COMPTA);
    }

    /**
     * @return array<int, array{id: int, libelle: string, debut: string, fin: string, actif: bool}>
     */
    public function saisons(): array
    {
        $this->exigerBureau();

        return array_map(
            static fn (Exercice $e): array => [
                'id' => (int) $e->id(),
                'libelle' => self::libelleSaison($e),
                'debut' => $e->dateDebut(),
                'fin' => $e->dateFin(),
                'actif' => $e->actif(),
            ],
            $this->exercices->all()
        );
    }

    /** Saison qui contient la date (AAAA-MM-JJ), sinon la saison active, sinon null. */
    public function saisonPourDate(?string $date): ?int
    {
        $this->exigerBureau();

        if ($date !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            foreach ($this->exercices->all() as $e) {
                if ($e->dateDebut() <= $date && $date <= $e->dateFin()) {
                    return (int) $e->id();
                }
            }
        }
        $actif = $this->exercices->active();

        return $actif !== null ? (int) $actif->id() : null;
    }

    /**
     * Referentiel des categories, par sens : code => "60 - Achat".
     *
     * @return array{depense: array<string, string>, recette: array<string, string>}
     */
    public function categories(): array
    {
        $liste = static function (array $groupe): array {
            $out = [];
            foreach (array_keys($groupe) as $code) {
                $out[(string) $code] = Categories::libelleCategorie($groupe, $code);
            }

            return $out;
        };

        return ['depense' => $liste(Categories::DEPENSE), 'recette' => $liste(Categories::RECETTE)];
    }

    /**
     * Sous-categories de depense, par categorie : pour la saisie d'une depense
     * (la categorie se deduit de la sous-categorie, comme dans l'ecran Depenses).
     *
     * @return array<string, array{libelle: string, sous: array<string, string>}>
     */
    public function sousCategoriesDepense(): array
    {
        $out = [];
        foreach (Categories::DEPENSE as $code => $def) {
            $out[(string) $code] = ['libelle' => Categories::libelleCategorie(Categories::DEPENSE, $code), 'sous' => $def['sous_categories']];
        }

        return $out;
    }

    /** @return list<string> */
    public function modesPaiement(): array
    {
        return DepenseScreen::modesPaiement();
    }

    /**
     * Projets compta d'une saison (pour relier un projet TKD a un projet deja
     * cree par la tresoriere).
     *
     * @return array<int, array{id: int, nom: string, statut: string}>
     */
    public function projetsDeSaison(int $exerciceId): array
    {
        $this->exigerBureau();

        return array_map(
            static fn (Projet $p): array => ['id' => (int) $p->id(), 'nom' => $p->nom(), 'statut' => $p->statut()],
            $this->projets->forExercice($exerciceId)
        );
    }

    /**
     * Enregistre une depense rattachee au projet (etape 4 de la Gestion de
     * projets, 18/11/2026 : la depense validee par le president entre
     * directement en tresorerie). Passe par DepenseScreen::saveFromRequest(),
     * comme la saisie rapide : justificatif range dans uploads/sp-compta.
     * Champs : date (dans la saison du projet), montant (> 0, format francais
     * accepte), sous_categorie (cle du referentiel), detail, mode_paiement.
     * Le justificatif eventuel est lu dans $files['justificatif'] (= $_FILES).
     * Qui a le droit de valider (le president) est verifie par l'appelant.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $files
     * @return array{id: int, date: string, montant: float, categorie: string, justificatif: bool}
     */
    public function enregistrerDepense(int $projetId, array $data, array $files = [], string $validePar = ''): array
    {
        $this->exigerBureau();

        $projet = $this->projets->find($projetId);
        if ($projet === null || $this->depenseScreen === null) {
            throw new \InvalidArgumentException('Projet compta introuvable.');
        }
        if (!$projet->estEnCours()) {
            throw new \InvalidArgumentException('Ce projet est clôturé dans la trésorerie : il ne reçoit plus de dépense.');
        }
        $exercice = $this->exercices->find($projet->exerciceId());
        $date = (string) ($data['date'] ?? '');
        if ($exercice === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1 || $date < $exercice->dateDebut() || $date > $exercice->dateFin()) {
            throw new \InvalidArgumentException('La date doit être dans la saison du projet.');
        }
        $montant = ProjetBilan::montantSaisi($data['montant'] ?? '');
        if ($montant <= 0.0) {
            throw new \InvalidArgumentException('Indiquez un montant.');
        }
        $sous = (string) ($data['sous_categorie'] ?? '');
        if (Categories::categorieDeSousCategorie(Categories::DEPENSE, $sous) === null) {
            throw new \InvalidArgumentException('Choisissez une catégorie.');
        }
        $detail = trim(sanitize_textarea_field((string) ($data['detail'] ?? '')));
        if ($validePar !== '') {
            $detail = trim($detail . ' — validée par ' . sanitize_text_field($validePar) . ' (TKD Projets)');
        }

        $depense = $this->depenseScreen->saveFromRequest(
            [
                'exercice_id' => (string) $projet->exerciceId(),
                'date' => $date,
                'montant' => number_format($montant, 2, '.', ''),
                'sous_categorie' => $sous,
                'detail' => $detail,
                'mode_paiement' => (string) ($data['mode_paiement'] ?? ''),
                'projet_id' => (string) $projetId,
            ],
            $files
        );

        return [
            'id' => (int) $depense->id(),
            'date' => $depense->date(),
            'montant' => $depense->montant(),
            'categorie' => $depense->categorie(),
            'justificatif' => $depense->justificatif() !== '',
        ];
    }

    /** @return array<string, mixed>|null */
    public function projet(int $id): ?array
    {
        $this->exigerBureau();
        $projet = $this->projets->find($id);

        return $projet !== null ? self::projetEnTableau($projet) : null;
    }

    /**
     * Cree (id null ou projet introuvable) ou met a jour un projet compta.
     * Champs : nom (obligatoire), nature, exercice_id (creation seulement : un
     * projet ne change jamais de saison), responsable, date_debut, date_fin,
     * description, statut. Les totaux prevus ne sont pas touches ici.
     *
     * @param array<string, mixed> $data
     * @return int id du projet compta
     */
    public function enregistrerProjet(?int $id, array $data): int
    {
        $this->exigerBureau();

        $existant = $id !== null ? $this->projets->find($id) : null;
        $nom = sanitize_text_field((string) ($data['nom'] ?? ''));
        if ($nom === '') {
            throw new \InvalidArgumentException('Le nom du projet est obligatoire.');
        }

        $exerciceId = $existant !== null ? $existant->exerciceId() : (int) ($data['exercice_id'] ?? 0);
        if ($existant === null && $this->exercices->find($exerciceId) === null) {
            throw new \InvalidArgumentException('Saison inconnue : créez et activez une saison dans Trésorerie → Paramètres.');
        }

        $nature = (string) ($data['nature'] ?? '');
        $statut = (string) ($data['statut'] ?? '');
        $date = static fn ($v): ?string => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1 ? $v : null;
        $description = sanitize_textarea_field((string) ($data['description'] ?? ''));

        $projet = $this->projets->save(new Projet(
            $existant?->id(),
            $exerciceId,
            mb_substr($nom, 0, 191),
            isset(Projet::NATURES[$nature]) ? $nature : 'autre',
            $existant?->budgetDepenses() ?? 0.0,
            $existant?->budgetRecettes() ?? 0.0,
            mb_substr(sanitize_text_field((string) ($data['responsable'] ?? '')), 0, 191),
            $date($data['date_debut'] ?? null),
            $date($data['date_fin'] ?? null),
            $description !== '' ? $description : null,
            isset(Projet::STATUTS[$statut]) ? $statut : Projet::STATUT_EN_COURS
        ));

        return (int) $projet->id();
    }

    /**
     * @return array<int, array{id: int, sens: string, categorie: string, libelle: string, montant: float}>
     */
    public function lignesBudget(int $projetId): array
    {
        $this->exigerBureau();

        return array_map([self::class, 'ligneEnTableau'], $this->lignes->forProjet($projetId));
    }

    /**
     * Remplace les lignes de budget du projet, puis met ses totaux prevus a la
     * somme des lignes. Une ligne sans montant ni libelle est ignoree ; une
     * categorie absente du referentiel de son sens est refusee.
     *
     * @param array<int, array<string, mixed>> $lignes sens, categorie, libelle, montant
     * @return array<int, array{id: int, sens: string, categorie: string, libelle: string, montant: float}>
     */
    public function enregistrerLignesBudget(int $projetId, array $lignes): array
    {
        $this->exigerBureau();

        $projet = $this->projets->find($projetId);
        if ($projet === null) {
            throw new \InvalidArgumentException('Projet compta introuvable.');
        }

        $propres = self::lignesValidees($projetId, $lignes);
        $enregistrees = $this->lignes->remplacer($projetId, $propres);
        $totaux = ProjetBilan::totauxLignes($enregistrees);

        $this->projets->save(new Projet(
            $projet->id(),
            $projet->exerciceId(),
            $projet->nom(),
            $projet->nature(),
            $totaux['depenses'],
            $totaux['recettes'],
            $projet->responsable(),
            $projet->dateDebut(),
            $projet->dateFin(),
            $projet->description(),
            $projet->statut()
        ));

        return array_map([self::class, 'ligneEnTableau'], $enregistrees);
    }

    /**
     * Valide des lignes recues (sans base de donnees : testable seule).
     *
     * @param array<int, mixed> $lignes
     * @return BudgetLigne[]
     */
    public static function lignesValidees(int $projetId, array $lignes): array
    {
        if (count($lignes) > self::MAX_LIGNES) {
            throw new \InvalidArgumentException('Trop de lignes de budget (' . self::MAX_LIGNES . ' au plus).'); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- texte brut, échappé là où il est affiché
        }

        $propres = [];
        foreach (array_values($lignes) as $i => $l) {
            if (!is_array($l)) {
                continue;
            }
            $sens = (string) ($l['sens'] ?? '');
            $categorie = (string) ($l['categorie'] ?? '');
            $libelle = mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($l['libelle'] ?? ''))) ?? ''), 0, 191);
            $montant = ProjetBilan::montantSaisi($l['montant'] ?? '');

            if ($montant <= 0.0 && $libelle === '') {
                continue; // ligne vide
            }
            if (!isset(BudgetLigne::SENS[$sens])) {
                throw new \InvalidArgumentException('Ligne ' . ($i + 1) . ' : choisissez « Dépense » ou « Recette ».'); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- texte brut, échappé là où il est affiché
            }
            $referentiel = $sens === BudgetLigne::SENS_RECETTE ? Categories::RECETTE : Categories::DEPENSE;
            if (!isset($referentiel[$categorie])) {
                throw new \InvalidArgumentException('Ligne ' . ($i + 1) . ' : catégorie inconnue.'); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- texte brut, échappé là où il est affiché
            }
            $propres[] = new BudgetLigne(null, $projetId, $sens, $categorie, $libelle, $montant, $i);
        }

        return $propres;
    }

    /**
     * Bilan du projet : totaux (prevu / realise / ecart) et detail par categorie.
     *
     * @return array{projet: array<string, mixed>, totaux: array<string, mixed>, categories: array<int, array<string, mixed>>}|null
     */
    public function bilan(int $projetId): ?array
    {
        $this->exigerBureau();

        $projet = $this->projets->find($projetId);
        if ($projet === null) {
            return null;
        }
        $depenses = $this->depenses->forProjet($projetId);
        $recettes = $this->recettes->forProjet($projetId);
        $totaux = ProjetBilan::pour($projet, $depenses, $recettes);
        unset($totaux['projet']);

        return [
            'projet' => self::projetEnTableau($projet),
            'totaux' => $totaux,
            'categories' => ProjetBilan::parCategorie($projet, $this->lignes->forProjet($projetId), $depenses, $recettes),
        ];
    }

    /**
     * Mouvements rattaches au projet, du plus recent au plus ancien. Montant
     * negatif pour une depense. Jamais de lien vers les justificatifs.
     *
     * @return array<int, array{type: string, date: string, categorie: string, libelle: string, detail: string, montant: float}>
     */
    public function mouvements(int $projetId): array
    {
        $this->exigerBureau();

        $out = [];
        foreach ($this->depenses->forProjet($projetId) as $d) {
            $out[] = [
                'type' => BudgetLigne::SENS_DEPENSE,
                'date' => $d->date(),
                'categorie' => $d->categorie(),
                'libelle' => $d->categorie() !== '' ? Categories::libelleSousCategorie(Categories::DEPENSE, $d->categorie(), $d->sousCategorie()) : 'Non catégorisé',
                'detail' => (string) $d->detail(),
                'montant' => -$d->montant(),
            ];
        }
        foreach ($this->recettes->forProjet($projetId) as $r) {
            $out[] = [
                'type' => BudgetLigne::SENS_RECETTE,
                'date' => $r->date(),
                'categorie' => $r->categorie(),
                'libelle' => trim(($r->provenance() !== '' ? $r->provenance() . ' · ' : '')
                    . ($r->categorie() !== '' ? Categories::libelleSousCategorie(Categories::RECETTE, $r->categorie(), $r->sousCategorie()) : '')),
                'detail' => (string) $r->detail(),
                'montant' => $r->montant(),
            ];
        }
        usort($out, static fn (array $a, array $b): int => strcmp($b['date'], $a['date']));

        return $out;
    }

    private function exigerBureau(): void
    {
        if (!$this->peutConsulter()) {
            throw new \RuntimeException('Réservé au bureau (Trésorerie → Paramètres → Accès bureau).', 403);
        }
    }

    /** @return array<string, mixed> */
    private static function projetEnTableau(Projet $p): array
    {
        return [
            'id' => (int) $p->id(),
            'exercice_id' => $p->exerciceId(),
            'nom' => $p->nom(),
            'nature' => $p->nature(),
            'nature_libelle' => $p->natureLabel(),
            'statut' => $p->statut(),
            'budget_depenses' => $p->budgetDepenses(),
            'budget_recettes' => $p->budgetRecettes(),
            'responsable' => $p->responsable(),
            'date_debut' => $p->dateDebut(),
            'date_fin' => $p->dateFin(),
        ];
    }

    /** @return array{id: int, sens: string, categorie: string, libelle: string, montant: float} */
    private static function ligneEnTableau(BudgetLigne $l): array
    {
        return [
            'id' => (int) $l->id(),
            'sens' => $l->sens(),
            'categorie' => $l->categorie(),
            'libelle' => $l->libelle(),
            'montant' => $l->montant(),
        ];
    }

    private static function libelleSaison(Exercice $e): string
    {
        return substr($e->dateDebut(), 0, 4) . '-' . substr($e->dateFin(), 0, 4);
    }
}
