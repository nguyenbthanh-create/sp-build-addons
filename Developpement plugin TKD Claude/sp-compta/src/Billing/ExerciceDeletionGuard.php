<?php

declare(strict_types=1);

namespace SpCompta\Billing;

use SpCompta\Repository\DepenseRepository;
use SpCompta\Repository\DevisRepository;
use SpCompta\Repository\ExerciceRepository;
use SpCompta\Repository\FactureRepository;
use SpCompta\Repository\RecetteRepository;
use SpCompta\Repository\SponsorRepository;

final class ExerciceDeletionGuard
{
    public function __construct(
        private ExerciceRepository $exerciceRepository,
        private DepenseRepository $depenseRepository,
        private RecetteRepository $recetteRepository,
        private SponsorRepository $sponsorRepository,
        private DevisRepository $devisRepository,
        private FactureRepository $factureRepository
    ) {
    }

    /**
     * Supprime l'exercice uniquement s'il n'a aucune donnee rattachee.
     * Retourne false sans rien supprimer si des donnees existent encore -
     * jamais de suppression partielle ou forcee.
     */
    public function delete(int $exerciceId): bool
    {
        if ($this->hasData($exerciceId)) {
            return false;
        }

        return $this->exerciceRepository->delete($exerciceId);
    }

    public function hasData(int $exerciceId): bool
    {
        return $this->depenseRepository->forExercice($exerciceId) !== []
            || $this->recetteRepository->forExercice($exerciceId) !== []
            || $this->sponsorRepository->forExercice($exerciceId) !== []
            || $this->devisRepository->forExercice($exerciceId) !== []
            || $this->factureRepository->forExercice($exerciceId) !== [];
    }
}
