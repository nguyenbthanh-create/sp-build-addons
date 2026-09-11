<?php

/**
 * Plugin Name: SP Compta
 * Description: Gestion de tresorerie associative (depenses, recettes, devis, factures, clients, fournisseurs, sponsors).
 * Version: 0.1.0
 * Text Domain: sp-compta
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'SpCompta\\';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require $path;
    }
});

use SpCompta\Plugin;

register_activation_hook(__FILE__, [Plugin::class, 'activate']);

Plugin::getInstance()->boot();
