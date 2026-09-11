<?php

declare(strict_types=1);

namespace SpCompta\Entity;

final class Recette
{
    public function __construct(
        private ?int $id,
        private int $exerciceId,
        private string $date,
        private float $montant,
        private string $provenance = '',
        private ?int $clientId = null,
        private string $categorie = '',
        private ?string $detail = null,
        private string $modePaiement = '',
        private string $justificatif = '',
        private string $sousCategorie = ''
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

    public function date(): string
    {
        return $this->date;
    }

    public function montant(): float
    {
        return $this->montant;
    }

    public function provenance(): string
    {
        return $this->provenance;
    }

    public function clientId(): ?int
    {
        return $this->clientId;
    }

    public function categorie(): string
    {
        return $this->categorie;
    }

    public function sousCategorie(): string
    {
        return $this->sousCategorie;
    }

    public function detail(): ?string
    {
        return $this->detail;
    }

    public function modePaiement(): string
    {
        return $this->modePaiement;
    }

    public function justificatif(): string
    {
        return $this->justificatif;
    }

    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->exerciceId,
            $this->date,
            $this->montant,
            $this->provenance,
            $this->clientId,
            $this->categorie,
            $this->detail,
            $this->modePaiement,
            $this->justificatif,
            $this->sousCategorie
        );
    }
}
