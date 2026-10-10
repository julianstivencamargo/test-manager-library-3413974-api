<?php

declare(strict_types=1);

namespace Library\Domain\Exceptions;

final class InvalidDateRangeException extends LibraryBusinessException
{
    public function __construct(string $message = 'El rango de fechas es inválido: la fecha de fin no puede ser anterior a la de inicio.')
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return 'INVALID_DATE_RANGE';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
