<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Accounting;

use SpCompta\Accounting\Categories;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Accounting/Categories.md
 */
final class CategoriesTest extends WP_UnitTestCase
{
    /** @test */
    public function it_finds_the_parent_categorie_of_a_sous_categorie(): void
    {
        // Given the RECETTE referentiel
        // When looking up the parent of "commune"
        $code = Categories::categorieDeSousCategorie(Categories::RECETTE, 'commune');

        // Then it is "74" (Subvention d'exploitation)
        $this->assertSame('74', $code);
    }

    /** @test */
    public function it_returns_null_for_an_unknown_sous_categorie(): void
    {
        // Given a sous-categorie key that does not exist anywhere
        // When looking it up
        $code = Categories::categorieDeSousCategorie(Categories::DEPENSE, 'invente');

        // Then nothing is returned
        $this->assertNull($code);
    }

    /** @test */
    public function it_builds_a_combined_label_for_a_categorie(): void
    {
        // Given the code 74
        // When building its label
        $label = Categories::libelleCategorie(Categories::RECETTE, '74');

        // Then it combines the code and the official intitule
        $this->assertSame("74 - Subvention d'exploitation", $label);
    }

    /** @test */
    public function it_gives_every_categorie_code_at_least_one_sous_categorie(): void
    {
        // Given both referentiels
        // When checking every categorie
        foreach ([Categories::DEPENSE, Categories::RECETTE] as $groupe) {
            foreach ($groupe as $code => $definition) {
                // Then each one has at least one selectable sous-categorie
                $this->assertNotEmpty($definition['sous_categories'], "Categorie {$code} sans sous-categorie");
            }
        }
    }

    /** @test */
    public function it_classifies_private_sponsors_under_subventions_dexploitation(): void
    {
        // Given the sponsors_prives sous-categorie used by SponsorPaiementSync
        // When looking up its parent
        $code = Categories::categorieDeSousCategorie(Categories::RECETTE, 'sponsors_prives');

        // Then it belongs to 74
        $this->assertSame('74', $code);
    }

    /**
     * Regression du 08/09/2026 : PHP convertit automatiquement les cles de
     * tableau qui ressemblent a un entier ('60' => ...) en int - iterer sur
     * Categories::DEPENSE/RECETTE avec foreach ($groupe as $code => ...)
     * donne donc un $code de type int, jamais string, meme si le code
     * source ecrit '60' entre guillemets. Sous strict_types=1, passer cet
     * int a une methode declaree string $code levait une TypeError fatale
     * (page blanche sur Depenses/Recettes). libelleCategorie() et
     * libelleSousCategorie() doivent accepter les deux types sans lever.
     */

    /** @test */
    public function it_accepts_an_int_code_for_libelle_categorie_without_throwing(): void
    {
        // Given the code 60 passed as an int, exactly like a foreach over Categories::DEPENSE yields it
        $label = Categories::libelleCategorie(Categories::DEPENSE, 60);

        // Then it resolves exactly like the string equivalent, no TypeError
        $this->assertSame('60 - Achat', $label);
    }

    /** @test */
    public function it_accepts_an_int_code_for_libelle_sous_categorie_without_throwing(): void
    {
        // Given the code 60 passed as an int
        $label = Categories::libelleSousCategorie(Categories::DEPENSE, 60, 'fourniture_bureau');

        // Then it resolves exactly like the string equivalent, no TypeError
        $this->assertSame('Fourniture de bureau', $label);
    }

    /** @test */
    public function it_always_returns_a_real_string_code_never_an_int(): void
    {
        // Given a lookup that internally iterates over int-keyed codes
        $code = Categories::categorieDeSousCategorie(Categories::DEPENSE, 'fourniture_bureau');

        // Then the returned code is a genuine string, not an int that merely looks equal
        $this->assertIsString($code);
        $this->assertSame('60', $code);
    }
}
