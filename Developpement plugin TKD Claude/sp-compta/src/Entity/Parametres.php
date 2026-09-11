<?php

declare(strict_types=1);

namespace SpCompta\Entity;

final class Parametres
{
    public function __construct(
        private ?int $id,
        private string $siret = '',
        private string $siegeSocial = '',
        private string $nomAssociation = '',
        private string $logoUrl = ''
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function siret(): string
    {
        return $this->siret;
    }

    public function siegeSocial(): string
    {
        return $this->siegeSocial;
    }

    public function nomAssociation(): string
    {
        return $this->nomAssociation;
    }

    public function logoUrl(): string
    {
        return $this->logoUrl;
    }

    public function withId(int $id): self
    {
        return new self($id, $this->siret, $this->siegeSocial, $this->nomAssociation, $this->logoUrl);
    }
}
