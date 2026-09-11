<?php

declare(strict_types=1);

namespace SpCompta\Admin;

use SpCompta\Capabilities;

final class Menu
{
    /**
     * @param AdminScreen[] $screens
     */
    public function __construct(private array $screens)
    {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenuPages']);
    }

    public function addMenuPages(): void
    {
        if ($this->screens === []) {
            return;
        }

        $first = $this->screens[0];

        add_menu_page(
            'Trésorerie',
            'Trésorerie',
            Capabilities::MANAGE_COMPTA,
            $first->slug(),
            [$first, 'render'],
            'dashicons-money-alt'
        );

        foreach ($this->screens as $screen) {
            add_submenu_page(
                $first->slug(),
                $screen->title(),
                $screen->menuLabel(),
                Capabilities::MANAGE_COMPTA,
                $screen->slug(),
                [$screen, 'render']
            );
        }
    }
}
