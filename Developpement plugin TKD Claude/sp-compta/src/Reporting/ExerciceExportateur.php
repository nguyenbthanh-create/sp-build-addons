<?php

declare(strict_types=1);

namespace SpCompta\Reporting;

use SpCompta\Entity\Client;
use SpCompta\Entity\Depense;
use SpCompta\Entity\Devis;
use SpCompta\Entity\Exercice;
use SpCompta\Entity\Facture;
use SpCompta\Entity\Fournisseur;
use SpCompta\Entity\LigneDocument;
use SpCompta\Entity\Recette;
use SpCompta\Entity\Sponsor;

/**
 * Construit un tableau exhaustif et fidele des donnees d'un exercice, pret
 * pour wp_json_encode() - la "sauvegarde brute" de l'exercice. Aucune mise
 * en forme (voir RapportAgGenerator pour la version presentable) : chaque
 * champ garde son nom et son type d'origine, pour rester exploitable meme
 * sans relire le code du plugin.
 */
final class ExerciceExportateur
{
    /**
     * @param Depense[] $depenses
     * @param Recette[] $recettes
     * @param Sponsor[] $sponsors
     * @param Devis[] $devis
     * @param Facture[] $factures
     * @param Client[] $clients
     * @param Fournisseur[] $fournisseurs
     * @return array<string, mixed>
     */
    public static function toArray(
        Exercice $exercice,
        array $depenses,
        array $recettes,
        array $sponsors,
        array $devis,
        array $factures,
        array $clients,
        array $fournisseurs
    ): array {
        $nomsClients = self::indexNoms($clients, static fn (Client $c): int => (int) $c->id(), static fn (Client $c): string => $c->nom());
        $nomsFournisseurs = self::indexNoms(
            $fournisseurs,
            static fn (Fournisseur $f): int => (int) $f->id(),
            static fn (Fournisseur $f): string => $f->nom()
        );

        return [
            'genere_le' => current_time('mysql'),
            'exercice' => [
                'id' => $exercice->id(),
                'date_debut' => $exercice->dateDebut(),
                'date_fin' => $exercice->dateFin(),
                'solde_initial' => $exercice->soldeInitial(),
                'actif' => $exercice->actif(),
            ],
            'depenses' => array_map(
                static fn (Depense $d): array => [
                    'id' => $d->id(),
                    'date' => $d->date(),
                    'montant' => $d->montant(),
                    'fournisseur_id' => $d->fournisseurId(),
                    'fournisseur_nom' => $d->fournisseurId() !== null ? ($nomsFournisseurs[$d->fournisseurId()] ?? '') : '',
                    'categorie' => $d->categorie(),
                    'sous_categorie' => $d->sousCategorie(),
                    'detail' => $d->detail(),
                    'mode_paiement' => $d->modePaiement(),
                    'justificatif' => $d->justificatif(),
                ],
                $depenses
            ),
            'recettes' => array_map(
                static fn (Recette $r): array => [
                    'id' => $r->id(),
                    'date' => $r->date(),
                    'montant' => $r->montant(),
                    'provenance' => $r->provenance(),
                    'client_id' => $r->clientId(),
                    'client_nom' => $r->clientId() !== null ? ($nomsClients[$r->clientId()] ?? '') : '',
                    'categorie' => $r->categorie(),
                    'sous_categorie' => $r->sousCategorie(),
                    'detail' => $r->detail(),
                    'mode_paiement' => $r->modePaiement(),
                    'justificatif' => $r->justificatif(),
                ],
                $recettes
            ),
            'sponsors' => array_map(
                static fn (Sponsor $s): array => [
                    'id' => $s->id(),
                    'nom' => $s->nom(),
                    'montant' => $s->montant(),
                    'date' => $s->date(),
                    'type_paiement' => $s->typePaiement(),
                    'contrat_signe' => $s->contratSigne(),
                    'fichier_contrat' => $s->fichierContrat(),
                    'contrat_paye' => $s->contratPaye(),
                    'recette_id' => $s->recetteId(),
                ],
                $sponsors
            ),
            'devis' => array_map(
                static fn (Devis $d): array => [
                    'id' => $d->id(),
                    'numero' => $d->numero(),
                    'client_id' => $d->clientId(),
                    'client_nom' => $nomsClients[$d->clientId()] ?? '',
                    'date_creation' => $d->dateCreation(),
                    'date_validite' => $d->dateValidite(),
                    'statut' => $d->statut(),
                    'notes' => $d->notes(),
                    'total' => $d->total(),
                    'lignes' => self::lignesArray($d->lignes()),
                ],
                $devis
            ),
            'factures' => array_map(
                static fn (Facture $f): array => [
                    'id' => $f->id(),
                    'numero' => $f->numero(),
                    'client_id' => $f->clientId(),
                    'client_nom' => $nomsClients[$f->clientId()] ?? '',
                    'date_emission' => $f->dateEmission(),
                    'date_echeance' => $f->dateEcheance(),
                    'statut' => $f->statut(),
                    'devis_id' => $f->devisId(),
                    'mode_reglement' => $f->modeReglement(),
                    'notes' => $f->notes(),
                    'total' => $f->total(),
                    'lignes' => self::lignesArray($f->lignes()),
                ],
                $factures
            ),
        ];
    }

    /**
     * @param LigneDocument[] $lignes
     * @return array<int, array<string, mixed>>
     */
    private static function lignesArray(array $lignes): array
    {
        return array_map(
            static fn (LigneDocument $l): array => [
                'designation' => $l->designation(),
                'quantite' => $l->quantite(),
                'prix_unitaire' => $l->prixUnitaire(),
                'total' => $l->total(),
            ],
            $lignes
        );
    }

    /**
     * @param array<int, object> $entites
     * @param callable(object): int $id
     * @param callable(object): string $nom
     * @return array<int, string>
     */
    private static function indexNoms(array $entites, callable $id, callable $nom): array
    {
        $index = [];

        foreach ($entites as $entite) {
            $index[$id($entite)] = $nom($entite);
        }

        return $index;
    }
}
