<?php

declare(strict_types=1);

namespace SpCompta\Numbering;

final class SequenceGenerator
{
    public function __construct(private string $table)
    {
    }

    public function next(string $cle): int
    {
        $wpdb = $this->wpdb();

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$this->table} (cle, valeur) VALUES (%s, 1)
                 ON DUPLICATE KEY UPDATE valeur = valeur + 1",
                $cle
            )
        );

        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT valeur FROM {$this->table} WHERE cle = %s", $cle)
        );
    }

    private function wpdb(): \wpdb
    {
        global $wpdb;

        return $wpdb;
    }
}
