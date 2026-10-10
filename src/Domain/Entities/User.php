<?php

declare(strict_types=1);

namespace Library\Domain\Entities;

use Library\Domain\Exceptions\ValidationException;

final class User
{
    private string $username;

    public function __construct(
        private string $id,
        string $username,
        private string $passwordHash,
        private string $role,
    ) {
        $cleanUsername = strtolower(trim($username));
        if ($cleanUsername === '') {
            throw new ValidationException('El nombre de usuario no puede estar vacío.');
        }

        if (!in_array($this->role, ['admin', 'librarian'], true)) {
            throw new ValidationException("Rol '{$this->role}' no válido. Solo se permiten 'admin' o 'librarian'.");
        }

        $this->username = $cleanUsername;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function username(): string
    {
        return $this->username;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function role(): string
    {
        return $this->role;
    }
}
