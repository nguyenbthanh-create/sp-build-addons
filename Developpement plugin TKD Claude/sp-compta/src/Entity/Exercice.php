<?php

declare(strict_types=1);

namespace SpCompta\Entity;

final class Exercice
{
    public function __construct(
        private ?int $id,
        private string $dateDebut,
        private string $dateFin,
        private float $soldeInitial = 0.0,
        private bool $actif = false
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function dateDebut(): string
    {
        return $this->dateDebut;
    }

    public function dateFin(): string
    {
        return $this->dateFin;
    }

    public function soldeInitial(): float
    {
        return $this->soldeInitial;
    }

    public function actif(): bool
    {
        return $this->actif;
    }

    public function withId(int $id): self
    {
        return new self($id, $this->dateDebut, $this->dateFin, $this->soldeInitial, $this->actif);
    }
}
