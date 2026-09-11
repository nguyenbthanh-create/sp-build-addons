<?php

declare(strict_types=1);

namespace SpCompta\Numbering;

final class DocumentNumeroGenerator
{
    public function __construct(
        private SequenceGenerator $sequences,
        private string $prefixe
    ) {
    }

    public function next(string $annee): string
    {
        $sequence = $this->sequences->next($this->prefixe . '_' . $annee);

        return sprintf('%s%s-%04d', $this->prefixe, $annee, $sequence);
    }
}
