<?php

declare(strict_types=1);

namespace Library\Domain\Exceptions;

final class DuplicateBookInLoanException extends LibraryBusinessException
{
    public function __construct(string $message = 'No se puede prestar el mismo libro dos veces en la misma operación.')
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return 'DUPLICATE_BOOK_IN_LOAN';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
