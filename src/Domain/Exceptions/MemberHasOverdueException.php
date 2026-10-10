<?php

declare(strict_types=1);

namespace Library\Domain\Exceptions;

final class MemberHasOverdueException extends LibraryBusinessException
{
    public function __construct(string $message = 'El socio tiene libros vencidos y no puede solicitar nuevos préstamos.')
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return 'MEMBER_HAS_OVERDUE';
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
