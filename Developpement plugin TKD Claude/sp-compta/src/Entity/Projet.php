<?php

declare(strict_types=1);

namespace SpCompta\Entity;

/**
 * Projet de la saison (fete de Noel, achat de materiel, stage...) : une
 * etiquette analytique posee sur des depenses/recettes, independante de la
 * categorie CERFA - voir Projet.md. Un projet appartient a un seul exercice.
 */
final class Projet
{
    public const NATURES = [
        'evenement' => 'Événement',
        'investissement' => 'Investissement (matériel)',
        'stage' => 'Stage',
        'competition' => 'Compétition',
        'autre' => 'Autre',
    ];

    public const STATUT_EN_COURS = 'en_cours';
    public const STATUT_CLOTURE = 'cloture';

    public const STATUTS = [
        self::STATUT_EN_COURS => 'En cours',
        self::STATUT_CLOTURE => 'Clôturé',
    ];

    public function __construct(
        private ?int $id,
        private int $exerciceId,
        private string $nom,
        private string $nature = 'evenement',
        private float $budgetDepenses = 0.0,
        private float $budgetRecettes = 0.0,
        private string $responsable = '',
        private ?string $dateDebut = null,
        private ?string $dateFin = null,
        private ?string $description = null,
        private string $statut = self::STATUT_EN_COURS
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function exerciceId(): int
    {
        return $this->exerciceId;
    }

    public function nom(): string
    {
        return $this->nom;
    }

    public function nature(): string
    {
        return $this->nature;
    }

    public function natureLabel(): string
    {
        return self::NATURES[$this->nature] ?? $this->nature;
    }

    public function budgetDepenses(): float
    {
        return $this->budgetDepenses;
    }

    public function budgetRecettes(): float
    {
        return $this->budgetRecettes;
    }

    /**
     * Resultat prevu : recettes prevues - depenses prevues (negatif = le projet
     * doit couter au club).
     */
    public function budgetNet(): float
    {
        return $this->budgetRecettes - $this->budgetDepenses;
    }

    /**
     * Vrai si au moins un montant prevu a ete saisi : sinon il n'y a pas de
     * "prevu" a comparer, l'ecran n'affiche que le realise.
     */
    public function aUnBudget(): bool
    {
        return $this->budgetDepenses > 0.0 || $this->budgetRecettes > 0.0;
    }

    public function responsable(): string
    {
        return $this->responsable;
    }

    public function dateDebut(): ?string
    {
        return $this->dateDebut;
    }

    public function dateFin(): ?string
    {
        return $this->dateFin;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function statut(): string
    {
        return $this->statut;
    }

    public function statutLabel(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }

    public function estEnCours(): bool
    {
        return $this->statut === self::STATUT_EN_COURS;
    }

    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->exerciceId,
            $this->nom,
            $this->nature,
            $this->budgetDepenses,
            $this->budgetRecettes,
            $this->responsable,
            $this->dateDebut,
            $this->dateFin,
            $this->description,
            $this->statut
        );
    }
}
