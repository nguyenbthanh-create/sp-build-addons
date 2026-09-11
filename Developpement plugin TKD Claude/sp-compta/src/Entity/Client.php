<?php

declare(strict_types=1);

namespace SpCompta\Entity;

final class Client
{
    public function __construct(
        private ?int $id,
        private string $nom,
        private string $type = 'particulier',
        private string $adresse = '',
        private string $codePostal = '',
        private string $ville = '',
        private string $email = '',
        private string $telephone = '',
        private ?string $notes = null
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function nom(): string
    {
        return $this->nom;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function adresse(): string
    {
        return $this->adresse;
    }

    public function codePostal(): string
    {
        return $this->codePostal;
    }

    public function ville(): string
    {
        return $this->ville;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function telephone(): string
    {
        return $this->telephone;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->nom,
            $this->type,
            $this->adresse,
            $this->codePostal,
            $this->ville,
            $this->email,
            $this->telephone,
            $this->notes
        );
    }
}
