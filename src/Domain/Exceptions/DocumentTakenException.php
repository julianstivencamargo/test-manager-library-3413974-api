<?php

declare(strict_types=1);

namespace Library\Domain\Exceptions;

final class DocumentTakenException extends LibraryBusinessException
{
    public function __construct(string $message = 'El número de documento ya está registrado en otro socio.')
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return 'DOCUMENT_TAKEN';
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
