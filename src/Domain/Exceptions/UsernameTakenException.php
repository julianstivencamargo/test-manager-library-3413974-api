<?php

declare(strict_types=1);

namespace Library\Domain\Exceptions;

final class UsernameTakenException extends LibraryBusinessException
{
    public function __construct(string $message = 'El nombre de usuario ya está registrado.')
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return 'USERNAME_TAKEN';
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
