<?php
/**
 * Constantes définies au démarrage des plugins (fichier principal de chaque plugin), déclarées
 * ici pour que PHPStan les connaisse. Valeurs factices : seule leur existence compte.
 * À compléter si un plugin ajoute une constante utilisée dans d'autres fichiers.
 */
define( 'SP_CAL_PRO_VERSION', '0' );
define( 'SP_CAL_PRO_PATH', __DIR__ . '/../sp_build/' );
define( 'SP_CAL_PRO_URL', 'https://example.test/wp-content/plugins/sp_build/' );
define( 'CLAIRA_TKD_PLUGIN_DIR', __DIR__ . '/../claira-tkd-parcours/' );
define( 'CLAIRA_TKD_PLUGIN_URL', 'https://example.test/wp-content/plugins/claira-tkd-parcours/' );
define( 'CLAIRA_TKD_PLUGIN_VERSION', '0' );
define( 'TKD_COT_DIR', __DIR__ . '/../tkd-cotisations/' );
define( 'TKD_COT_CAP', 'tkd_cotisations_manager' );
