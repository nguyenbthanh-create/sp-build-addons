<?php

declare(strict_types=1);

namespace SpCompta\Billing;

use SpCompta\Accounting\Categories;
use SpCompta\Entity\Depense;
use SpCompta\Entity\IkPaiement;
use SpCompta\Repository\DepenseRepository;
use SpCompta\Repository\ExerciceRepository;
use SpCompta\Repository\IkPaiementRepository;

/**
 * Meme principe que SponsorPaiementSync : cocher "paye" cree la depense liee correspondante
 * (categorie 62 / deplacements), idempotent (ne recree jamais si deja paye), et decocher ne
 * supprime jamais la depense deja creee.
 */
final class IkPaiementSync
{
    private const MOIS_LABELS = [
        1 => 'janvier', 2 => 'fevrier', 3 => 'mars', 4 => 'avril', 5 => 'mai', 6 => 'juin',
        7 => 'juillet', 8 => 'aout', 9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'decembre',
    ];

    public function __construct(
        private IkPaiementRepository $ikRepository,
        private DepenseRepository $depenseRepository,
        private ExerciceRepository $exerciceRepository
    ) {
    }

    /**
     * Marque payee l'IK d'un entraineur pour un mois donne. Ne fait rien si deja payee (evite
     * les doublons a chaque re-soumission). Retourne null si aucun exercice n'est actif (on ne
     * peut pas creer de depense sans exercice, meme cas que Depense/Recette/Sponsor).
     */
    public function marquerPaye(int $trainerId, int $annee, int $mois, float $montant, string $trainerNom): ?IkPaiement
    {
        $existing = $this->ikRepository->findByTrainerPeriode($trainerId, $annee, $mois);
        if ($existing !== null && $existing->paye()) {
            return $existing;
        }

        $exerciceActif = $this->exerciceRepository->active();
        if ($exerciceActif === null) {
            return null;
        }

        $date = gmdate('Y-m-d');
        $moisLabel = (self::MOIS_LABELS[$mois] ?? (string) $mois) . ' ' . $annee;

        $depense = $this->depenseRepository->save(new Depense(
            id: null,
            exerciceId: (int) $exerciceActif->id(),
            date: $date,
            montant: $montant,
            fournisseurId: null,
            categorie: Categories::categorieDeSousCategorie(Categories::DEPENSE, 'deplacements') ?? '',
            detail: 'Indemnites kilometriques ' . $trainerNom . ' - ' . $moisLabel,
            modePaiement: '',
            justificatif: '',
            sousCategorie: 'deplacements'
        ));

        $ik = ($existing ?? new IkPaiement(null, $trainerId, $annee, $mois))
            ->withPaiement($montant, $date, (int) $depense->id());

        return $this->ikRepository->save($ik);
    }

    /**
     * Decoche "paye" — garde le lien vers la depense deja creee (jamais de suppression
     * automatique, coherent avec SponsorPaiementSync). Ne fait rien si aucune ligne n'existe
     * encore pour cette periode (rien a decocher).
     */
    public function demarquerPaye(int $trainerId, int $annee, int $mois): void
    {
        $existing = $this->ikRepository->findByTrainerPeriode($trainerId, $annee, $mois);
        if ($existing === null || !$existing->paye()) {
            return;
        }

        $this->ikRepository->save($existing->sansPaiement());
    }
}
