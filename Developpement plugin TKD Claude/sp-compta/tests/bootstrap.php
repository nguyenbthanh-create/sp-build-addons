<?php

declare(strict_types=1);

$testsDir = getenv('WP_TESTS_DIR');

if (!$testsDir) {
    $testsDir = '/tmp/wordpress-tests-lib';
}

require_once $testsDir . '/includes/functions.php';

function _sp_compta_load_plugin(): void
{
    require dirname(__DIR__) . '/sp-compta.php';
}
tests_add_filter('muplugins_loaded', '_sp_compta_load_plugin');

require $testsDir . '/includes/bootstrap.php';
