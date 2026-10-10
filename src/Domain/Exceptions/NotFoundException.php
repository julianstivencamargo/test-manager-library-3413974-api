<?php

declare(strict_types=1);

namespace Library\Domain\Exceptions;

final class NotFoundException extends LibraryBusinessException
{
    public function __construct(string $message = 'Recurso no encontrado.')
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return 'NOT_FOUND';
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
