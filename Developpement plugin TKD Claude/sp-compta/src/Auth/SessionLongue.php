<?php

declare(strict_types=1);

namespace SpCompta\Auth;

use SpCompta\Capabilities;

/**
 * Session WordPress longue (1 an) pour les comptes ayant acces a la
 * tresorerie - voir SessionLongue.md. Probleme regle (06/10/2026) : la saisie
 * rapide installee sur l'ecran d'accueil (iPhone et Android) redemandait la
 * connexion, parce que sans « Se souvenir de moi » WordPress pose un cookie
 * temporaire, efface des que le systeme ferme l'application.
 *
 * Les autres comptes du site ne sont pas concernes.
 */
final class SessionLongue
{
    public const DUREE = 365 * 24 * 3600;

    public function registerHooks(): void
    {
        add_filter('auth_cookie_expiration', [$this, 'filtrerDuree'], 10, 3);
        add_action('wp_login', [$this, 'prolongerALaConnexion'], 10, 2);
    }

    /**
     * Duree de validite du cookie de connexion : 1 an pour un compte bureau
     * (au lieu de 14 jours avec « Se souvenir de moi »), inchangee sinon.
     *
     * @param int|string $duree
     * @param int|string $userId
     * @param bool|int|string $remember
     */
    public function filtrerDuree($duree, $userId = 0, $remember = false): int
    {
        $duree = (int) $duree;

        return self::estConcerne((int) $userId) ? max($duree, self::DUREE) : $duree;
    }

    /**
     * A la connexion d'un compte bureau sans « Se souvenir de moi » coche :
     * on repose le cookie en mode persistant (WordPress vient de poser un
     * cookie temporaire). Avec la case cochee, rien a faire : le filtre
     * ci-dessus porte deja la duree a 1 an.
     *
     * @param string $login
     * @param \WP_User|null $user
     */
    public function prolongerALaConnexion($login, $user = null): void
    {
        if (!$user instanceof \WP_User || !self::estConcerne((int) $user->ID)) {
            return;
        }

        if (!empty($_POST['rememberme'])) {
            return;
        }

        if (!headers_sent()) {
            wp_set_auth_cookie((int) $user->ID, true, is_ssl());
        }
    }

    public static function estConcerne(int $userId): bool
    {
        return $userId > 0 && user_can($userId, Capabilities::MANAGE_COMPTA);
    }
}
