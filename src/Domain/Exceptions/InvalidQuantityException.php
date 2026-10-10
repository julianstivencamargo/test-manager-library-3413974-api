<?php

declare(strict_types=1);

namespace Library\Domain\Exceptions;

final class InvalidQuantityException extends DomainException
{
    public function __construct(string $message = 'La cantidad debe ser un entero estrictamente positivo.')
    {
        parent::__construct($message);
    }
}
