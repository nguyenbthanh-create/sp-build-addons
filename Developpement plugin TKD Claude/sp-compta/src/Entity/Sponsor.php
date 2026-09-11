<?php

declare(strict_types=1);

namespace SpCompta\Entity;

final class Sponsor
{
    public function __construct(
        private ?int $id,
        private int $exerciceId,
        private string $nom,
        private float $montant,
        private string $date,
        private string $typePaiement = 'numeraire',
        private bool $contratSigne = false,
        private string $fichierContrat = '',
        private bool $contratPaye = false,
        private ?int $recetteId = null
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

    public function montant(): float
    {
        return $this->montant;
    }

    public function date(): string
    {
        return $this->date;
    }

    public function typePaiement(): string
    {
        return $this->typePaiement;
    }

    public function contratSigne(): bool
    {
        return $this->contratSigne;
    }

    public function fichierContrat(): string
    {
        return $this->fichierContrat;
    }

    public function contratPaye(): bool
    {
        return $this->contratPaye;
    }

    public function recetteId(): ?int
    {
        return $this->recetteId;
    }

    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->exerciceId,
            $this->nom,
            $this->montant,
            $this->date,
            $this->typePaiement,
            $this->contratSigne,
            $this->fichierContrat,
            $this->contratPaye,
            $this->recetteId
        );
    }

    public function withRecetteId(int $recetteId): self
    {
        return new self(
            $this->id,
            $this->exerciceId,
            $this->nom,
            $this->montant,
            $this->date,
            $this->typePaiement,
            $this->contratSigne,
            $this->fichierContrat,
            $this->contratPaye,
            $recetteId
        );
    }
}
