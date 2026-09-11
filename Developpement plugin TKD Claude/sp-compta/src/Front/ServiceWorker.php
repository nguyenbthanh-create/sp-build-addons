<?php

declare(strict_types=1);

namespace SpCompta\Front;

/**
 * Sert /sw.js (a la racine du site, quel que soit l'endroit ou vit la page
 * de saisie rapide) sans passer par les regles de reecriture WordPress -
 * juste une interception directe sur 'init', plus simple et sans flush de
 * regles a gerer a l'activation. Service worker volontairement minimal
 * (pas de cache, pas de mode hors-ligne) : son seul but est de satisfaire
 * les criteres d'installabilite PWA de Chrome/Android pour declencher la
 * banniere d'installation automatique - voir SaisieRapideShortcode.md.
 */
final class ServiceWorker
{
    public function registerHooks(): void
    {
        add_action('init', [$this, 'maybeServe']);
    }

    public function maybeServe(): void
    {
        if (!$this->matchesRequest((string) ($_SERVER['REQUEST_URI'] ?? ''))) {
            return;
        }

        $scope = (string) parse_url(home_url('/'), PHP_URL_PATH);

        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: ' . ($scope !== '' ? $scope : '/'));
        header('Cache-Control: no-cache');
        echo self::contents();
        exit;
    }

    public function matchesRequest(string $requestUri): bool
    {
        $expectedPath = (string) parse_url(self::url(), PHP_URL_PATH);
        $requestPath = (string) parse_url($requestUri, PHP_URL_PATH);

        return $requestPath === $expectedPath;
    }

    public static function url(): string
    {
        return home_url('/sw.js');
    }

    public static function contents(): string
    {
        return <<<'JS'
// Service worker minimal pour SP Compta.
// Ne met rien en cache et ne fournit aucun mode hors-ligne : son seul role
// est de satisfaire les criteres techniques d'installation PWA de Chrome
// (manifest + service worker + HTTPS) pour declencher la banniere
// d'installation automatique "Ajouter a l'ecran d'accueil".
self.addEventListener('install', function (event) {
  self.skipWaiting();
});

self.addEventListener('activate', function (event) {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', function () {
  // Intentionnellement vide : chaque requete part normalement sur le
  // reseau, comme si aucun service worker n'etait present.
});
JS;
    }
}
