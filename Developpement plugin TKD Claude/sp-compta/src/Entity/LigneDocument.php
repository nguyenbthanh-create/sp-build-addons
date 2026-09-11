<?php

declare(strict_types=1);

namespace SpCompta\Entity;

final class LigneDocument
{
    public function __construct(
        private ?int $id,
        private string $designation,
        private float $quantite,
        private float $prixUnitaire
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function designation(): string
    {
        return $this->designation;
    }

    public function quantite(): float
    {
        return $this->quantite;
    }

    public function prixUnitaire(): float
    {
        return $this->prixUnitaire;
    }

    public function total(): float
    {
        return $this->quantite * $this->prixUnitaire;
    }

    public function withId(int $id): self
    {
        return new self($id, $this->designation, $this->quantite, $this->prixUnitaire);
    }
}
