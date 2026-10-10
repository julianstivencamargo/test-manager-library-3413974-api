<?php

declare(strict_types=1);

namespace Library\Domain\Exceptions;

final class ItemAlreadyReturnedException extends LibraryBusinessException
{
    public function __construct(string $message = 'El ejemplar ya fue devuelto previamente.')
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return 'ITEM_ALREADY_RETURNED';
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
