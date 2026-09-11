<?php

declare(strict_types=1);

namespace SpCompta\Entity;

final class Devis
{
    public const STATUT_BROUILLON = 'brouillon';
    public const STATUT_ENVOYE = 'envoye';
    public const STATUT_ACCEPTE = 'accepte';
    public const STATUT_REFUSE = 'refuse';
    public const STATUT_FACTURE = 'facture';

    /**
     * @param LigneDocument[] $lignes
     */
    public function __construct(
        private ?int $id,
        private int $exerciceId,
        private int $clientId,
        private string $dateCreation,
        private string $dateValidite,
        private array $lignes = [],
        private string $statut = self::STATUT_BROUILLON,
        private string $numero = '',
        private string $notes = ''
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

    public function clientId(): int
    {
        return $this->clientId;
    }

    public function dateCreation(): string
    {
        return $this->dateCreation;
    }

    public function dateValidite(): string
    {
        return $this->dateValidite;
    }

    /**
     * @return LigneDocument[]
     */
    public function lignes(): array
    {
        return $this->lignes;
    }

    public function statut(): string
    {
        return $this->statut;
    }

    public function numero(): string
    {
        return $this->numero;
    }

    public function notes(): string
    {
        return $this->notes;
    }

    public function total(): float
    {
        return array_reduce(
            $this->lignes,
            static fn (float $somme, LigneDocument $ligne): float => $somme + $ligne->total(),
            0.0
        );
    }

    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->exerciceId,
            $this->clientId,
            $this->dateCreation,
            $this->dateValidite,
            $this->lignes,
            $this->statut,
            $this->numero,
            $this->notes
        );
    }

    public function withNumero(string $numero): self
    {
        return new self(
            $this->id,
            $this->exerciceId,
            $this->clientId,
            $this->dateCreation,
            $this->dateValidite,
            $this->lignes,
            $this->statut,
            $numero,
            $this->notes
        );
    }

    public function withStatut(string $statut): self
    {
        return new self(
            $this->id,
            $this->exerciceId,
            $this->clientId,
            $this->dateCreation,
            $this->dateValidite,
            $this->lignes,
            $statut,
            $this->numero,
            $this->notes
        );
    }
}
