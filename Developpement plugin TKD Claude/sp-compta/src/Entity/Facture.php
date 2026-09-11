<?php

declare(strict_types=1);

namespace SpCompta\Entity;

final class Facture
{
    public const STATUT_EMISE = 'emise';
    public const STATUT_PAYEE = 'payee';
    public const STATUT_ANNULEE = 'annulee';

    /**
     * @param LigneDocument[] $lignes
     */
    public function __construct(
        private ?int $id,
        private int $exerciceId,
        private int $clientId,
        private string $dateEmission,
        private string $dateEcheance,
        private array $lignes = [],
        private string $statut = self::STATUT_EMISE,
        private string $numero = '',
        private ?int $devisId = null,
        private string $modeReglement = '',
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

    public function dateEmission(): string
    {
        return $this->dateEmission;
    }

    public function dateEcheance(): string
    {
        return $this->dateEcheance;
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

    public function devisId(): ?int
    {
        return $this->devisId;
    }

    public function modeReglement(): string
    {
        return $this->modeReglement;
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
            $this->dateEmission,
            $this->dateEcheance,
            $this->lignes,
            $this->statut,
            $this->numero,
            $this->devisId,
            $this->modeReglement,
            $this->notes
        );
    }

    public function withNumero(string $numero): self
    {
        return new self(
            $this->id,
            $this->exerciceId,
            $this->clientId,
            $this->dateEmission,
            $this->dateEcheance,
            $this->lignes,
            $this->statut,
            $numero,
            $this->devisId,
            $this->modeReglement,
            $this->notes
        );
    }

    public function withStatut(string $statut): self
    {
        return new self(
            $this->id,
            $this->exerciceId,
            $this->clientId,
            $this->dateEmission,
            $this->dateEcheance,
            $this->lignes,
            $statut,
            $this->numero,
            $this->devisId,
            $this->modeReglement,
            $this->notes
        );
    }
}
