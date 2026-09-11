<?php

declare(strict_types=1);

namespace SpCompta\Billing;

use SpCompta\Accounting\Categories;
use SpCompta\Entity\Recette;
use SpCompta\Entity\Sponsor;
use SpCompta\Repository\RecetteRepository;
use SpCompta\Repository\SponsorRepository;

final class SponsorPaiementSync
{
    public function __construct(
        private SponsorRepository $sponsorRepository,
        private RecetteRepository $recetteRepository
    ) {
    }

    /**
     * Si le sponsor est marque "contrat paye" et n'a pas encore de recette
     * liee, cree la recette correspondante et memorise le lien sur le
     * sponsor. Ne fait rien si le sponsor n'est pas paye, ou s'il a deja
     * une recette liee (evite les doublons a chaque re-sauvegarde).
     */
    public function sync(Sponsor $sponsor): Sponsor
    {
        if (!$sponsor->contratPaye() || $sponsor->recetteId() !== null) {
            return $sponsor;
        }

        $recette = $this->recetteRepository->save(new Recette(
            id: null,
            exerciceId: $sponsor->exerciceId(),
            date: $sponsor->date(),
            montant: $sponsor->montant(),
            provenance: $sponsor->nom(),
            clientId: null,
            categorie: Categories::categorieDeSousCategorie(Categories::RECETTE, 'sponsors_prives') ?? '',
            detail: 'Genere automatiquement depuis le sponsor "' . $sponsor->nom() . '".',
            sousCategorie: 'sponsors_prives'
        ));

        $linked = $sponsor->withRecetteId((int) $recette->id());

        return $this->sponsorRepository->save($linked);
    }
}
