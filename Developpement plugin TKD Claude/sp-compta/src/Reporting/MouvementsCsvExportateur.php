<?php

declare(strict_types=1);

namespace SpCompta\Reporting;

use SpCompta\Accounting\Categories;
use SpCompta\Entity\Client;
use SpCompta\Entity\Depense;
use SpCompta\Entity\Fournisseur;
use SpCompta\Entity\Recette;

/**
 * Construit le CSV "mouvements" d'un exercice (une ligne par depense/recette,
 * triees par date) - demande par la tresoriere le 11/09/2026 pour archivage,
 * en complement du JSON brut (voir ExerciceExportateur.md) qui vise plutot
 * une restauration technique complete. Ce CSV est pense pour etre ouvert
 * directement dans Excel : point-virgule comme separateur (convention FR,
 * evite l'ambiguite avec la virgule decimale), BOM UTF-8 pour les accents,
 * montant au format decimal francais.
 */
final class MouvementsCsvExportateur
{
    private const ENTETES = ['Date', 'Type', 'Categorie', 'Tiers', 'Mode de paiement', 'Montant', 'Detail'];

    /**
     * @param Depense[] $depenses
     * @param Recette[] $recettes
     * @param Fournisseur[] $fournisseurs
     * @param Client[] $clients
     */
    public static function toCsv(array $depenses, array $recettes, array $fournisseurs, array $clients): string
    {
        $nomsFournisseurs = self::indexParId($fournisseurs);
        $nomsClients = self::indexParId($clients);

        $lignes = [];

        foreach ($depenses as $depense) {
            $lignes[] = [
                'date' => $depense->date(),
                'type' => 'Depense',
                'categorie' => self::libelleCategorie(Categories::DEPENSE, $depense->categorie(), $depense->sousCategorie()),
                'tiers' => $depense->fournisseurId() !== null ? ($nomsFournisseurs[$depense->fournisseurId()] ?? '') : '',
                'mode_paiement' => $depense->modePaiement(),
                'montant' => -$depense->montant(),
                'detail' => (string) $depense->detail(),
            ];
        }

        foreach ($recettes as $recette) {
            $lignes[] = [
                'date' => $recette->date(),
                'type' => 'Recette',
                'categorie' => self::libelleCategorie(Categories::RECETTE, $recette->categorie(), $recette->sousCategorie()),
                'tiers' => $recette->clientId() !== null ? ($nomsClients[$recette->clientId()] ?? '') : $recette->provenance(),
                'mode_paiement' => $recette->modePaiement(),
                'montant' => $recette->montant(),
                'detail' => (string) $recette->detail(),
            ];
        }

        usort($lignes, static fn (array $a, array $b): int => $a['date'] <=> $b['date']);

        return self::assembler($lignes);
    }

    /**
     * @param array<int, array{date: string, type: string, categorie: string, tiers: string, mode_paiement: string, montant: float, detail: string}> $lignes
     */
    private static function assembler(array $lignes): string
    {
        $flux = fopen('php://temp', 'r+');

        // BOM UTF-8 : sans lui, Excel ouvre le fichier en interpretant mal les
        // caracteres accentues (e, e, euro) sur Windows.
        fwrite($flux, "\xEF\xBB\xBF");
        fputcsv($flux, self::ENTETES, ';');

        foreach ($lignes as $ligne) {
            fputcsv($flux, [
                self::formatDate($ligne['date']),
                $ligne['type'],
                $ligne['categorie'],
                self::champSecurise($ligne['tiers']),
                $ligne['mode_paiement'],
                number_format($ligne['montant'], 2, ',', ''),
                self::champSecurise($ligne['detail']),
            ], ';');
        }

        rewind($flux);
        $csv = stream_get_contents($flux);
        fclose($flux);

        return $csv === false ? '' : $csv;
    }

    private static function formatDate(string $date): string
    {
        $objet = date_create($date);

        return $objet !== false ? $objet->format('d/m/Y') : $date;
    }

    private static function libelleCategorie(array $groupe, string $categorie, string $sousCategorie): string
    {
        if ($categorie === '') {
            return 'Non categorise';
        }

        return Categories::libelleCategorie($groupe, $categorie) . ' - ' . Categories::libelleSousCategorie($groupe, $categorie, $sousCategorie);
    }

    /**
     * Neutralise l'injection de formule CSV/Excel : un champ de texte libre
     * (detail, provenance) commencant par =, +, - ou @ serait execute comme
     * une formule par Excel a l'ouverture. On prefixe d'une apostrophe dans
     * ce cas (force l'affichage en texte), comme le fait Excel lui-meme.
     */
    private static function champSecurise(string $valeur): string
    {
        if ($valeur !== '' && strpbrk($valeur[0], "=+-@\t\r") !== false) {
            return "'" . $valeur;
        }

        return $valeur;
    }

    /**
     * @param array<int, Fournisseur|Client> $entites
     * @return array<int, string>
     */
    private static function indexParId(array $entites): array
    {
        $index = [];

        foreach ($entites as $entite) {
            $index[(int) $entite->id()] = $entite->nom();
        }

        return $index;
    }
}
