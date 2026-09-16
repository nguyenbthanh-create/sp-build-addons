<?php

declare(strict_types=1);

namespace SpCompta\Entity;

/**
 * Statut de paiement d'une indemnité kilométrique (IK) pour un entraîneur sp_build donné,
 * sur un mois donné. Ne stocke pas le nombre d'AR ni le km (toujours recalculés à l'affichage
 * depuis SpBuildReader, jamais figés ici pour éviter une désynchronisation) — seulement l'état
 * de paiement et le montant réellement versé au moment où il a été marqué payé.
 */
final class IkPaiement
{
    public function __construct(
        private ?int $id,
        private int $trainerId,
        private int $annee,
        private int $mois,
        private bool $paye = false,
        private ?float $montantVerse = null,
        private ?string $datePaiement = null,
        private ?int $depenseId = null
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function trainerId(): int
    {
        return $this->trainerId;
    }

    public function annee(): int
    {
        return $this->annee;
    }

    public function mois(): int
    {
        return $this->mois;
    }

    public function paye(): bool
    {
        return $this->paye;
    }

    public function montantVerse(): ?float
    {
        return $this->montantVerse;
    }

    public function datePaiement(): ?string
    {
        return $this->datePaiement;
    }

    public function depenseId(): ?int
    {
        return $this->depenseId;
    }

    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->trainerId,
            $this->annee,
            $this->mois,
            $this->paye,
            $this->montantVerse,
            $this->datePaiement,
            $this->depenseId
        );
    }

    /**
     * Marque payé avec le montant/date/dépense liée — utilisé par IkPaiementSync uniquement.
     */
    public function withPaiement(float $montant, string $date, int $depenseId): self
    {
        return new self(
            $this->id,
            $this->trainerId,
            $this->annee,
            $this->mois,
            true,
            $montant,
            $date,
            $depenseId
        );
    }

    /**
     * Décoche "payé" — ne supprime jamais le lien vers la dépense déjà créée (même principe
     * que SponsorPaiementSync : aucune suppression automatique si on décoche).
     */
    public function sansPaiement(): self
    {
        return new self(
            $this->id,
            $this->trainerId,
            $this->annee,
            $this->mois,
            false,
            $this->montantVerse,
            $this->datePaiement,
            $this->depenseId
        );
    }
}
