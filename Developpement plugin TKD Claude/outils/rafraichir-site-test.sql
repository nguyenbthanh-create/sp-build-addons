-- ════════════════════════════════════════════════════════════════════════════════════════
-- Rafraîchissement du site de test (dev.tkdclaira.fr) avec une copie de la base de prod.
-- 08/10/2026 — à exécuter dans phpMyAdmin, sur la base du SITE DE TEST, juste après l'import.
--
-- Garde-fou : chaque requête contient « DATABASE() <> 'tkdclad766' » (base de la PROD) :
-- exécuté par erreur sur la prod, ce script ne modifie rien.
--
-- Utilisé le 08/10/2026 (cf. JOURNAL.md, section 8). Pièges rencontrés :
--   - OVH refuse l'import si la sauvegarde contient « DEFINER=`tkdclad766`@`%` » (4 vues
--     mod237_uap_*_view) : les retirer avant l'import, sous Git Bash :
--       gzip -dc sauvegarde.gz | sed 's#DEFINER=`tkdclad766`@`%` ##g' | gzip -9 > corrige.sql.gz
--   - Étape 4 : le tableau de résultats de phpMyAdmin tronque les textes longs (« Textes
--     partiels ») et répète l'en-tête toutes les 100 lignes — passer en « Textes complets ».
--   - Après l'import : copier sur le test les dossiers des extensions actives en prod qui y
--     manqueraient, puis les RÉACTIVER (WordPress les désactive s'il les trouve absentes) ;
--     enfin Elementor → Outils → « Remplacer l'URL » (https://tkdclaira.fr → https://dev.tkdclaira.fr).
--
-- Rappel — avant l'import, dans le wp-config.php du SITE DE TEST (jamais celui de la prod) :
--     define( 'WPMS_ON', true );            // WP Mail SMTP lit les constantes ci-dessous
--     define( 'WPMS_DO_NOT_SEND', true );   // aucun email ne part, quoi qu'il y ait dans la base
--     define( 'DISABLE_WP_CRON', true );    // pas de tâches automatiques (vœux, rappels, file d'envoi)
-- ════════════════════════════════════════════════════════════════════════════════════════

-- 1. Adresse du site
UPDATE mod237_options SET option_value = 'https://dev.tkdclaira.fr'
 WHERE option_name IN ( 'siteurl', 'home' ) AND DATABASE() <> 'tkdclad766';

-- 2. Adresses email des comptes WordPress et de l'administrateur : « .invalid » ajouté
--    (domaine réservé, aucun email ne peut y arriver ; la connexion par identifiant reste possible).
UPDATE mod237_users SET user_email = CONCAT( user_email, '.invalid' )
 WHERE user_email <> '' AND user_email NOT LIKE '%.invalid' AND DATABASE() <> 'tkdclad766';
UPDATE mod237_options SET option_value = CONCAT( option_value, '.invalid' )
 WHERE option_name IN ( 'admin_email', 'new_admin_email' ) AND option_value LIKE '%@%'
   AND option_value NOT LIKE '%.invalid' AND DATABASE() <> 'tkdclad766';

-- 3. Envois en attente et abonnements aux notifications : vidés sur le test.
DELETE FROM mod237_sp_cal_mail_queue WHERE DATABASE() <> 'tkdclad766';
DELETE FROM mod237_sp_cal_push_subs  WHERE DATABASE() <> 'tkdclad766';

-- 4. Adresses email dans les tables des plugins (adhérents, demandes d'adhésion, entraîneurs,
--    trésorerie, inscriptions…, y compris dans les colonnes JSON) : chaque adresse devient
--    « nom@domaine.fr.invalid ». Les tables WordPress (options, métadonnées) ne sont pas touchées :
--    leurs valeurs sérialisées casseraient si la longueur d'un texte changeait.
--
--    Cette requête ne modifie rien : elle PRODUIT les requêtes à exécuter (une par colonne).
--    Copier la colonne « requete » du résultat (« Afficher tout » si besoin) et l'exécuter
--    UNE SEULE FOIS (une 2e exécution ajouterait un 2e « .invalid », sans autre conséquence).
SELECT CONCAT(
         'UPDATE `', c.TABLE_NAME, '` SET `', c.COLUMN_NAME, '` = REGEXP_REPLACE(`', c.COLUMN_NAME,
         '`, ''([A-Za-z0-9._%+-]+@[A-Za-z0-9-]+(\\\\.[A-Za-z0-9-]+)*\\\\.[A-Za-z]{2,})'', ''$1.invalid'')',
         ' WHERE `', c.COLUMN_NAME, '` LIKE ''%@%'' AND DATABASE() <> ''tkdclad766'';'
       ) AS requete
  FROM information_schema.COLUMNS c
 WHERE c.TABLE_SCHEMA = DATABASE()
   AND DATABASE() <> 'tkdclad766'
   AND c.TABLE_NAME LIKE 'mod237\_sp%'
   AND c.DATA_TYPE IN ( 'varchar', 'char', 'text', 'tinytext', 'mediumtext', 'longtext', 'json' )
 ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION;

-- 5. Contrôle (adhérents) : doit renvoyer 0 une fois l'étape 4 exécutée.
SELECT COUNT(*) AS adresses_non_neutralisees FROM mod237_sp_cal_eleves
 WHERE ( email LIKE '%@%' AND email NOT LIKE '%.invalid' )
    OR ( email_parent LIKE '%@%' AND email_parent NOT LIKE '%.invalid' );
