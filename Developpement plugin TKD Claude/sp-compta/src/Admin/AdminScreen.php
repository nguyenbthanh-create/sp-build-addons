<?php

declare(strict_types=1);

namespace SpCompta\Admin;

interface AdminScreen
{
    public function slug(): string;

    public function title(): string;

    public function menuLabel(): string;

    public function render(): void;
}
