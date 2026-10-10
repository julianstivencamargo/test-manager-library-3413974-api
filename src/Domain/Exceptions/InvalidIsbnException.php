<?php

declare(strict_types=1);

namespace Library\Domain\Exceptions;

final class InvalidIsbnException extends DomainException
{
    public function __construct(string $message = 'El ISBN proporcionado no tiene un formato válido de 13 dígitos.')
    {
        parent::__construct($message);
    }
}
