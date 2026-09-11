<?php

declare(strict_types=1);

namespace SpCompta\Billing;

use RuntimeException;
use SpCompta\Entity\Devis;
use SpCompta\Entity\Facture;
use SpCompta\Repository\DevisRepository;
use SpCompta\Repository\FactureRepository;

final class DevisToFactureConverter
{
    public function __construct(
        private DevisRepository $devisRepository,
        private FactureRepository $factureRepository
    ) {
    }

    public function convert(Devis $devis, string $dateEmission, string $dateEcheance): Facture
    {
        if ($devis->statut() !== Devis::STATUT_ACCEPTE) {
            throw new RuntimeException(
                sprintf('Seul un devis accepte peut etre transforme en facture (statut actuel : %s).', $devis->statut())
            );
        }

        $facture = $this->factureRepository->save(new Facture(
            null,
            $devis->exerciceId(),
            $devis->clientId(),
            $dateEmission,
            $dateEcheance,
            $devis->lignes(),
            Facture::STATUT_EMISE,
            '',
            $devis->id()
        ));

        $this->devisRepository->save($devis->withStatut(Devis::STATUT_FACTURE));

        return $facture;
    }
}
