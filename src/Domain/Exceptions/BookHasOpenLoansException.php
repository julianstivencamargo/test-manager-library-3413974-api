<?php

declare(strict_types=1);

namespace Library\Domain\Exceptions;

final class BookHasOpenLoansException extends LibraryBusinessException
{
    public function __construct(string $message = 'El libro tiene préstamos abiertos y no puede darse de baja.')
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return 'BOOK_HAS_OPEN_LOANS';
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
