<?php

declare(strict_types=1);

namespace SpCompta\Media;

use SpCompta\Capabilities;
use SpCompta\Database;

/**
 * Deplacement, une fois pour toutes, des justificatifs deja envoyes dans la
 * mediatheque publique vers uploads/sp-compta/ (10/11/2026). Se lance au
 * premier chargement d'une page wp-admin par un compte tresorerie apres le
 * deploiement ; rejoue tant qu'un deplacement a echoue. Voir
 * JustificatifStorage.md.
 */
final class JustificatifMigration
{
    public const OPTION = 'sp_compta_justificatifs_proteges';

    public const OPTION_JOURNAL = 'sp_compta_justificatifs_journal';

    private const VERSION = '1';

    private const VERROU = 'sp_compta_justificatifs_verrou';

    public function __construct(private Database $database, private Capabilities $capabilities)
    {
    }

    public function registerHooks(): void
    {
        add_action('admin_init', [$this, 'maybeRun']);
    }

    public function maybeRun(): void
    {
        if (get_option(self::OPTION) === self::VERSION || !$this->capabilities->currentUserCanManage()) {
            return;
        }
        if (get_transient(self::VERROU)) {
            return; // deplacement deja en cours dans une autre requete
        }
        set_transient(self::VERROU, 1, 5 * MINUTE_IN_SECONDS);

        $bilan = ['deplace' => 0, 'deja' => 0, 'absent' => 0, 'echec' => 0];
        $echecs = [];
        foreach ($this->idsReferences() as $id) {
            $r = JustificatifStorage::deplacer($id);
            $bilan[$r]++;
            if ($r === 'echec') {
                $echecs[] = $id;
            }
        }

        update_option(self::OPTION_JOURNAL, [
            'date' => current_time('mysql'),
            'bilan' => $bilan,
            'echecs' => $echecs,
        ], false);
        if ($echecs === []) {
            update_option(self::OPTION, self::VERSION, false);
        } else {
            error_log('[SP Compta] justificatifs non deplaces (attachments ' . implode(', ', $echecs) . ')');
        }

        delete_transient(self::VERROU);
    }

    /**
     * Ids d'attachments cites par une depense, une recette ou un contrat de
     * sponsor (valeurs purement numeriques ; les anciennes references texte
     * libre sont ignorees).
     *
     * @return int[]
     */
    public function idsReferences(): array
    {
        global $wpdb;
        $ids = [];
        $sources = [
            [$this->database->tableDepense(), 'justificatif'],
            [$this->database->tableRecette(), 'justificatif'],
            [$this->database->tableSponsor(), 'fichier_contrat'],
        ];
        foreach ($sources as [$table, $colonne]) {
            // Noms de table et de colonne internes (pas de saisie utilisateur).
            $valeurs = $wpdb->get_col("SELECT DISTINCT {$colonne} FROM {$table} WHERE {$colonne} REGEXP '^[0-9]+$'");
            foreach ((array) $valeurs as $v) {
                $ids[(int) $v] = true;
            }
        }
        unset($ids[0]);

        return array_keys($ids);
    }
}
