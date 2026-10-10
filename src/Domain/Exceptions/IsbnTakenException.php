<?php

declare(strict_types=1);

namespace Library\Domain\Exceptions;

final class IsbnTakenException extends LibraryBusinessException
{
    public function __construct(string $message = 'El ISBN ya está registrado en otro libro activo.')
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return 'ISBN_TAKEN';
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
