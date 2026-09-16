<?php

declare(strict_types=1);

namespace SpCompta;

final class Database
{
    /**
     * Incrementer a chaque changement de schema (nouvelle table ou nouvelle
     * colonne) pour que maybeUpgrade() rejoue createTables() sur les sites
     * deja actives, sans desactivation/reactivation du plugin.
     */
    private const DB_VERSION = '1.3.0';
    private const DB_VERSION_OPTION = 'sp_compta_db_version';

    public function tableExercice(): string
    {
        return $this->prefix() . 'sp_compta_exercice';
    }

    public function tableFournisseur(): string
    {
        return $this->prefix() . 'sp_compta_fournisseur';
    }

    public function tableClient(): string
    {
        return $this->prefix() . 'sp_compta_client';
    }

    public function tableDepense(): string
    {
        return $this->prefix() . 'sp_compta_depense';
    }

    public function tableRecette(): string
    {
        return $this->prefix() . 'sp_compta_recette';
    }

    public function tableSponsor(): string
    {
        return $this->prefix() . 'sp_compta_sponsor';
    }

    public function tableParametres(): string
    {
        return $this->prefix() . 'sp_compta_parametres';
    }

    public function tableSequence(): string
    {
        return $this->prefix() . 'sp_compta_sequence';
    }

    public function tableDevis(): string
    {
        return $this->prefix() . 'sp_compta_devis';
    }

    public function tableDevisLigne(): string
    {
        return $this->prefix() . 'sp_compta_devis_ligne';
    }

    public function tableFacture(): string
    {
        return $this->prefix() . 'sp_compta_facture';
    }

    public function tableFactureLigne(): string
    {
        return $this->prefix() . 'sp_compta_facture_ligne';
    }

    public function tableIkPaiement(): string
    {
        return $this->prefix() . 'sp_compta_ik_paiement';
    }

    public function createTables(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charsetCollate = $this->wpdb()->get_charset_collate();

        dbDelta($this->exerciceSchema($charsetCollate));
        dbDelta($this->fournisseurSchema($charsetCollate));
        dbDelta($this->clientSchema($charsetCollate));
        dbDelta($this->depenseSchema($charsetCollate));
        dbDelta($this->recetteSchema($charsetCollate));
        dbDelta($this->sponsorSchema($charsetCollate));
        dbDelta($this->parametresSchema($charsetCollate));
        dbDelta($this->sequenceSchema($charsetCollate));
        dbDelta($this->devisSchema($charsetCollate));
        dbDelta($this->devisLigneSchema($charsetCollate));
        dbDelta($this->factureSchema($charsetCollate));
        dbDelta($this->factureLigneSchema($charsetCollate));
        dbDelta($this->ikPaiementSchema($charsetCollate));

        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
    }

    /**
     * Rejoue createTables() (dbDelta est idempotent et ne fait qu'ajouter
     * les tables/colonnes manquantes, jamais en supprimer) si le schema du
     * code est plus recent que celui deja applique sur ce site. A appeler
     * sur 'admin_init' pour que les sites deja actives recuperent les
     * nouvelles colonnes sans devoir desactiver/reactiver le plugin.
     */
    public function maybeUpgrade(): void
    {
        if (get_option(self::DB_VERSION_OPTION) !== self::DB_VERSION) {
            $this->createTables();
        }
    }

    private function exerciceSchema(string $charsetCollate): string
    {
        $table = $this->tableExercice();

        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            date_debut DATE NOT NULL,
            date_fin DATE NOT NULL,
            solde_initial DECIMAL(10,2) NOT NULL DEFAULT 0,
            actif TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id)
        ) {$charsetCollate};";
    }

    private function fournisseurSchema(string $charsetCollate): string
    {
        $table = $this->tableFournisseur();

        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            nom VARCHAR(191) NOT NULL,
            adresse VARCHAR(255) NOT NULL DEFAULT '',
            contact VARCHAR(191) NOT NULL DEFAULT '',
            email VARCHAR(191) NOT NULL DEFAULT '',
            telephone VARCHAR(30) NOT NULL DEFAULT '',
            notes TEXT NULL,
            PRIMARY KEY (id)
        ) {$charsetCollate};";
    }

    private function clientSchema(string $charsetCollate): string
    {
        $table = $this->tableClient();

        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            type VARCHAR(20) NOT NULL DEFAULT 'particulier',
            nom VARCHAR(191) NOT NULL,
            adresse VARCHAR(255) NOT NULL DEFAULT '',
            code_postal VARCHAR(10) NOT NULL DEFAULT '',
            ville VARCHAR(191) NOT NULL DEFAULT '',
            email VARCHAR(191) NOT NULL DEFAULT '',
            telephone VARCHAR(30) NOT NULL DEFAULT '',
            notes TEXT NULL,
            PRIMARY KEY (id)
        ) {$charsetCollate};";
    }

    private function depenseSchema(string $charsetCollate): string
    {
        $table = $this->tableDepense();

        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            exercice_id BIGINT UNSIGNED NOT NULL,
            date DATE NOT NULL,
            montant DECIMAL(10,2) NOT NULL,
            fournisseur_id BIGINT UNSIGNED NULL,
            categorie VARCHAR(100) NOT NULL DEFAULT '',
            sous_categorie VARCHAR(100) NOT NULL DEFAULT '',
            detail TEXT NULL,
            mode_paiement VARCHAR(20) NOT NULL DEFAULT '',
            justificatif VARCHAR(255) NOT NULL DEFAULT '',
            PRIMARY KEY (id),
            KEY exercice_id (exercice_id),
            KEY fournisseur_id (fournisseur_id)
        ) {$charsetCollate};";
    }

    private function recetteSchema(string $charsetCollate): string
    {
        $table = $this->tableRecette();

        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            exercice_id BIGINT UNSIGNED NOT NULL,
            date DATE NOT NULL,
            montant DECIMAL(10,2) NOT NULL,
            provenance VARCHAR(191) NOT NULL DEFAULT '',
            client_id BIGINT UNSIGNED NULL,
            categorie VARCHAR(100) NOT NULL DEFAULT '',
            sous_categorie VARCHAR(100) NOT NULL DEFAULT '',
            detail TEXT NULL,
            mode_paiement VARCHAR(20) NOT NULL DEFAULT '',
            justificatif VARCHAR(255) NOT NULL DEFAULT '',
            PRIMARY KEY (id),
            KEY exercice_id (exercice_id),
            KEY client_id (client_id)
        ) {$charsetCollate};";
    }

    private function sponsorSchema(string $charsetCollate): string
    {
        $table = $this->tableSponsor();

        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            exercice_id BIGINT UNSIGNED NOT NULL,
            nom VARCHAR(191) NOT NULL,
            montant DECIMAL(10,2) NOT NULL,
            type_paiement VARCHAR(20) NOT NULL DEFAULT 'numeraire',
            date DATE NOT NULL,
            contrat_signe TINYINT(1) NOT NULL DEFAULT 0,
            fichier_contrat VARCHAR(255) NOT NULL DEFAULT '',
            contrat_paye TINYINT(1) NOT NULL DEFAULT 0,
            recette_id BIGINT UNSIGNED NULL,
            PRIMARY KEY (id),
            KEY exercice_id (exercice_id)
        ) {$charsetCollate};";
    }

    private function parametresSchema(string $charsetCollate): string
    {
        $table = $this->tableParametres();

        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            siret VARCHAR(20) NOT NULL DEFAULT '',
            siege_social VARCHAR(255) NOT NULL DEFAULT '',
            nom_association VARCHAR(191) NOT NULL DEFAULT '',
            logo_url VARCHAR(255) NOT NULL DEFAULT '',
            PRIMARY KEY (id)
        ) {$charsetCollate};";
    }

    private function sequenceSchema(string $charsetCollate): string
    {
        $table = $this->tableSequence();

        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            cle VARCHAR(50) NOT NULL,
            valeur INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY cle (cle)
        ) {$charsetCollate};";
    }

    private function devisSchema(string $charsetCollate): string
    {
        $table = $this->tableDevis();

        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            exercice_id BIGINT UNSIGNED NOT NULL,
            numero VARCHAR(20) NOT NULL,
            client_id BIGINT UNSIGNED NOT NULL,
            date_creation DATE NOT NULL,
            date_validite DATE NOT NULL,
            statut VARCHAR(20) NOT NULL DEFAULT 'brouillon',
            notes TEXT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY numero (numero),
            KEY exercice_id (exercice_id),
            KEY client_id (client_id)
        ) {$charsetCollate};";
    }

    private function devisLigneSchema(string $charsetCollate): string
    {
        $table = $this->tableDevisLigne();

        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            devis_id BIGINT UNSIGNED NOT NULL,
            designation VARCHAR(255) NOT NULL,
            quantite DECIMAL(10,2) NOT NULL DEFAULT 1,
            prix_unitaire DECIMAL(10,2) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY devis_id (devis_id)
        ) {$charsetCollate};";
    }

    private function factureSchema(string $charsetCollate): string
    {
        $table = $this->tableFacture();

        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            exercice_id BIGINT UNSIGNED NOT NULL,
            numero VARCHAR(20) NOT NULL,
            devis_id BIGINT UNSIGNED NULL,
            client_id BIGINT UNSIGNED NOT NULL,
            date_emission DATE NOT NULL,
            date_echeance DATE NOT NULL,
            statut VARCHAR(20) NOT NULL DEFAULT 'emise',
            mode_reglement VARCHAR(20) NOT NULL DEFAULT '',
            notes TEXT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY numero (numero),
            KEY exercice_id (exercice_id),
            KEY client_id (client_id),
            KEY devis_id (devis_id)
        ) {$charsetCollate};";
    }

    private function factureLigneSchema(string $charsetCollate): string
    {
        $table = $this->tableFactureLigne();

        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            facture_id BIGINT UNSIGNED NOT NULL,
            designation VARCHAR(255) NOT NULL,
            quantite DECIMAL(10,2) NOT NULL DEFAULT 1,
            prix_unitaire DECIMAL(10,2) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY facture_id (facture_id)
        ) {$charsetCollate};";
    }

    private function ikPaiementSchema(string $charsetCollate): string
    {
        $table = $this->tableIkPaiement();

        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            trainer_id BIGINT UNSIGNED NOT NULL,
            annee SMALLINT UNSIGNED NOT NULL,
            mois TINYINT UNSIGNED NOT NULL,
            paye TINYINT(1) NOT NULL DEFAULT 0,
            montant_verse DECIMAL(10,2) NULL,
            date_paiement DATE NULL,
            depense_id BIGINT UNSIGNED NULL,
            PRIMARY KEY (id),
            UNIQUE KEY trainer_periode (trainer_id, annee, mois)
        ) {$charsetCollate};";
    }

    private function prefix(): string
    {
        return $this->wpdb()->prefix;
    }

    private function wpdb(): \wpdb
    {
        global $wpdb;

        return $wpdb;
    }
}
