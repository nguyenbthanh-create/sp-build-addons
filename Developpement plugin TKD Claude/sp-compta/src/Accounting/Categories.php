<?php

declare(strict_types=1);

namespace SpCompta\Accounting;

/**
 * Referentiel comptable a deux niveaux (code categorie + sous-categorie)
 * pour les menus deroulants Depense/Recette. Transcrit du tableau CERFA
 * "Compte de resultat" fourni par l'utilisateur (liste officielle confirmee
 * le 08/09/2026, incluant les codes 86/87). Modifier ces deux tableaux
 * suffit a changer les options partout ou elles sont utilisees
 * (DepenseScreen, RecetteScreen, SponsorPaiementSync, SoldeScreen).
 */
final class Categories
{
    /**
     * @var array<string, array{label: string, sous_categories: array<string, string>}>
     */
    public const DEPENSE = [
        '60' => [
            'label' => 'Achat',
            'sous_categories' => [
                'fourniture_bureau' => 'Fourniture de bureau',
                'petit_equipement' => 'Petit equipement (ballons, protection, materiel...)',
                'marchandises' => 'Marchandises (gouter, repas, aperitif...)',
                'fournitures_consommables' => 'Fournitures consommables (encre, stylo, pharmacie...)',
            ],
        ],
        '61' => [
            'label' => 'Services exterieurs',
            'sous_categories' => [
                'sous_traitance_generale' => 'Sous traitance generale (sortie, prestataire...)',
                'locations' => 'Locations (vehicule...)',
                'entretien_reparation' => 'Entretien et reparation',
                'assurance' => 'Assurance',
                'documentation' => 'Documentation',
            ],
        ],
        '62' => [
            'label' => 'Autres services exterieurs',
            'sous_categories' => [
                'publicite_publication' => 'Publicite, publication',
                'deplacements' => 'Deplacements (IK, essence, peage...)',
                'frais_postaux_telecom' => 'Frais postaux et telecommunications',
                'services_bancaires' => 'Services bancaires',
                'cotisations_federation' => 'Cotisations (federation...)',
                'divers' => 'Divers (pourboires, dons courants...)',
            ],
        ],
        '63' => [
            'label' => 'Impots et taxes',
            'sous_categories' => [
                'autres_impots_taxes' => 'Autres impots et taxes (SACEM)',
            ],
        ],
        '64' => [
            'label' => 'Charges de personnel',
            'sous_categories' => [
                'remuneration_personnels' => 'Remuneration des personnels',
                'charges_sociales' => 'Charges sociales (GUSO, URSSAF...)',
                'autres_charges_personnel' => 'Autres charges de personnel',
            ],
        ],
        '65' => [
            'label' => 'Autres charges de gestion courante',
            'sous_categories' => [
                'gestion_courante' => 'Autres charges de gestion courante',
            ],
        ],
        '67' => [
            'label' => 'Charges exceptionnelles',
            'sous_categories' => [
                'charges_exceptionnelles' => 'Charges exceptionnelles',
            ],
        ],
        '68' => [
            'label' => 'Dotation aux amortissements',
            'sous_categories' => [
                'dotation_amortissements' => 'Dotation aux amortissements (provisions pour renouvellement)',
            ],
        ],
        '86' => [
            'label' => 'Emploi des contributions volontaires en nature',
            'sous_categories' => [
                'secours_en_nature' => 'Secours en nature (alimentaire, vestimentaire)',
                'mise_a_disposition_gratuite' => 'Mise a disposition gratuite de biens et prestations',
                'personnel_benevole' => 'Personnel benevole',
            ],
        ],
    ];

    /**
     * @var array<string, array{label: string, sous_categories: array<string, string>}>
     */
    public const RECETTE = [
        '70' => [
            'label' => 'Vente de produits finis, prestations de services, marchandises',
            'sous_categories' => [
                'prestation_services' => 'Prestation de services',
                'vente_marchandises' => 'Vente de marchandises',
                'produits_activites_annexes' => 'Produits des activites annexes',
            ],
        ],
        '74' => [
            'label' => 'Subvention d\'exploitation',
            'sous_categories' => [
                'etat' => 'Etat',
                'region' => 'Region',
                'departement' => 'Departement',
                'commune' => 'Commune',
                'organismes_sociaux' => 'Organismes sociaux',
                'cnasea' => 'CNASEA (emplois aides)',
                'sponsors_prives' => 'Sponsors prives',
                'autres_recettes' => 'Autres recettes',
            ],
        ],
        '75' => [
            'label' => 'Autres produits de gestion courante',
            'sous_categories' => [
                'cotisations' => 'Cotisations',
            ],
        ],
        '76' => [
            'label' => 'Produits financiers',
            'sous_categories' => [
                'produits_financiers' => 'Produits financiers',
            ],
        ],
        '78' => [
            'label' => 'Reprises sur amortissements et provisions',
            'sous_categories' => [
                'reprises_amortissements' => 'Reprises sur amortissements et provisions',
            ],
        ],
        '79' => [
            'label' => 'Transfert de charges',
            'sous_categories' => [
                'transfert_charges' => 'Transfert de charges',
            ],
        ],
        '87' => [
            'label' => 'Contributions volontaires en nature',
            'sous_categories' => [
                'benevolat' => 'Benevolat',
                'prestations_en_nature' => 'Prestations en nature',
                'dons_en_nature' => 'Dons en nature',
            ],
        ],
    ];

    /**
     * Retrouve le code categorie (ex. "74") auquel appartient une cle de
     * sous-categorie (ex. "commune"), ou null si elle n'existe dans aucune
     * categorie du groupe fourni (Categories::DEPENSE ou Categories::RECETTE).
     *
     * @param array<string, array{label: string, sous_categories: array<string, string>}> $groupe
     */
    public static function categorieDeSousCategorie(array $groupe, string $sousCategorieKey): ?string
    {
        foreach ($groupe as $code => $definition) {
            if (array_key_exists($sousCategorieKey, $definition['sous_categories'])) {
                // $code est un int ici : PHP convertit automatiquement les
                // cles de tableau qui ressemblent a un entier ('60', '74'...)
                // en int - on revient toujours a une string en sortie.
                return (string) $code;
            }
        }

        return null;
    }

    /**
     * $code accepte int|string : itérer sur DEPENSE/RECETTE avec un foreach
     * donne des cles int (voir categorieDeSousCategorie() ci-dessus), donc
     * les deux points d'appel (foreach direct dans les ecrans, ou valeur
     * deja string venant d'une entite Depense/Recette) doivent fonctionner
     * sans caster manuellement a chaque fois.
     *
     * @param array<string, array{label: string, sous_categories: array<string, string>}> $groupe
     */
    public static function libelleCategorie(array $groupe, int|string $code): string
    {
        $code = (string) $code;

        return isset($groupe[$code]) ? $code . ' - ' . $groupe[$code]['label'] : $code;
    }

    /**
     * @param array<string, array{label: string, sous_categories: array<string, string>}> $groupe
     */
    public static function libelleSousCategorie(array $groupe, int|string $code, string $sousCategorieKey): string
    {
        $code = (string) $code;

        return $groupe[$code]['sous_categories'][$sousCategorieKey] ?? $sousCategorieKey;
    }

    private function __construct()
    {
    }
}
