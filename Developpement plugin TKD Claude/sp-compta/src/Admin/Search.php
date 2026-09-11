<?php

declare(strict_types=1);

namespace SpCompta\Admin;

final class Search
{
    /**
     * Vrai si $term est une sous-chaine (insensible a la casse) d'au moins
     * une des valeurs de $haystack. $term vide correspond toujours (aucun
     * filtre applique). Les valeurs numeriques/nullables de $haystack sont
     * converties en chaine avant comparaison.
     *
     * @param array<int, string|int|float|null> $haystack
     */
    public static function matches(string $term, array $haystack): bool
    {
        $term = trim($term);

        if ($term === '') {
            return true;
        }

        foreach ($haystack as $value) {
            if ($value === null) {
                continue;
            }

            if (mb_stripos((string) $value, $term) !== false) {
                return true;
            }
        }

        return false;
    }

    private function __construct()
    {
    }
}
