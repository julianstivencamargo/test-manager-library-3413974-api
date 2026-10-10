<?php

declare(strict_types=1);

namespace Library\Domain\Exceptions;

final class InvalidCredentialsException extends LibraryBusinessException
{
    public function __construct(string $message = 'Credenciales inválidas.')
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return 'INVALID_CREDENTIALS';
    }

    public function httpStatus(): int
    {
        return 401;
    }
}
