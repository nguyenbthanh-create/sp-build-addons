<?php

declare(strict_types=1);

namespace SpCompta\Entity;

final class Fournisseur
{
    public function __construct(
        private ?int $id,
        private string $nom,
        private string $adresse = '',
        private string $contact = '',
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

    public function adresse(): string
    {
        return $this->adresse;
    }

    public function contact(): string
    {
        return $this->contact;
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
        return new self($id, $this->nom, $this->adresse, $this->contact, $this->email, $this->telephone, $this->notes);
    }
}
