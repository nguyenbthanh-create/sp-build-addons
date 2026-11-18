<?php

declare(strict_types=1);

namespace SpCompta\Entity;

/**
 * Ligne du budget prevu d'un projet : un sens (depense / recette), une
 * categorie CERFA (code '60', '74'...), un libelle libre et un montant prevu.
 * Le realise de la ligne se lit par categorie dans Accounting\ProjetBilan -
 * voir BudgetLigne.md.
 */
final class BudgetLigne
{
    public const SENS_DEPENSE = 'depense';
    public const SENS_RECETTE = 'recette';

    public const SENS = [
        self::SENS_DEPENSE => 'Dépense',
        self::SENS_RECETTE => 'Recette',
    ];

    public function __construct(
        private ?int $id,
        private int $projetId,
        private string $sens,
        private string $categorie,
        private string $libelle,
        private float $montant,
        private int $ordre = 0
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function projetId(): int
    {
        return $this->projetId;
    }

    public function sens(): string
    {
        return $this->sens;
    }

    public function estDepense(): bool
    {
        return $this->sens === self::SENS_DEPENSE;
    }

    public function categorie(): string
    {
        return $this->categorie;
    }

    public function libelle(): string
    {
        return $this->libelle;
    }

    public function montant(): float
    {
        return $this->montant;
    }

    public function ordre(): int
    {
        return $this->ordre;
    }

    public function withId(int $id): self
    {
        return new self($id, $this->projetId, $this->sens, $this->categorie, $this->libelle, $this->montant, $this->ordre);
    }
}
