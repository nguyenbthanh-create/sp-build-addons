<?php

declare(strict_types=1);

namespace SpCompta\Admin;

use SpCompta\Entity\Projet;
use SpCompta\Repository\ProjetRepository;

/**
 * Champ "Projet (facultatif)" commun aux formulaires Depense / Recette (admin)
 * et aux deux saisies rapides (telephone) : une seule facon d'afficher la
 * liste et une seule facon de valider la valeur recue - voir ProjetChamp.md.
 */
final class ProjetChamp
{
    public const NOM = 'projet_id';

    /**
     * Projets proposes : ceux "en cours" de l'exercice, plus le projet deja
     * rattache s'il est cloture (sinon modifier la ligne le ferait perdre en
     * silence).
     *
     * @return Projet[]
     */
    public static function options(?ProjetRepository $repository, int $exerciceId, ?int $selectionne = null): array
    {
        if ($repository === null || $exerciceId <= 0) {
            return [];
        }

        $projets = $repository->enCoursPourExercice($exerciceId);

        if ($selectionne !== null && !in_array($selectionne, array_map(static fn (Projet $p): ?int => $p->id(), $projets), true)) {
            $actuel = $repository->find($selectionne);
            if ($actuel !== null) {
                $projets[] = $actuel;
            }
        }

        return $projets;
    }

    /**
     * Balise <select> du champ (chaine vide si aucun projet a proposer : le
     * champ n'apparait pas tant qu'aucun projet n'existe).
     *
     * @param Projet[] $projets
     */
    public static function select(array $projets, ?int $selectionne, string $id, string $classe = ''): string
    {
        if ($projets === []) {
            return '';
        }

        $html = '<select id="' . esc_attr($id) . '" name="' . self::NOM . '"' . ($classe !== '' ? ' class="' . esc_attr($classe) . '"' : '') . '>';
        $html .= '<option value="">— Fonctionnement courant (aucun projet) —</option>';

        foreach ($projets as $projet) {
            $libelle = $projet->nom() . ($projet->estEnCours() ? '' : ' (clôturé)');
            $html .= '<option value="' . esc_attr((string) $projet->id()) . '"' . ($projet->id() === $selectionne ? ' selected' : '') . '>'
                . esc_html($libelle) . '</option>';
        }

        return $html . '</select>';
    }

    /**
     * Valeur a enregistrer a partir de la requete :
     * - champ absent de la requete -> on garde le projet deja rattache ($existant) ;
     * - champ vide -> fonctionnement courant (null) ;
     * - sinon le projet doit exister et appartenir au meme exercice que la ligne,
     *   faute de quoi la ligne repasse en fonctionnement courant.
     *
     * @param array<string, mixed> $request
     */
    public static function resoudre(?ProjetRepository $repository, array $request, int $exerciceId, ?int $existant): ?int
    {
        if (!array_key_exists(self::NOM, $request)) {
            return $existant;
        }

        $valeur = (int) $request[self::NOM];

        if ($valeur <= 0 || $repository === null) {
            return null;
        }

        $projet = $repository->find($valeur);

        return $projet !== null && $projet->exerciceId() === $exerciceId ? $valeur : null;
    }

    /**
     * Index id => nom de tous les projets d'un exercice (colonne "Projet" des listes).
     *
     * @return array<int, string>
     */
    public static function noms(?ProjetRepository $repository, int $exerciceId): array
    {
        if ($repository === null) {
            return [];
        }

        $noms = [];
        foreach ($repository->forExercice($exerciceId) as $projet) {
            $noms[(int) $projet->id()] = $projet->nom();
        }

        return $noms;
    }
}
