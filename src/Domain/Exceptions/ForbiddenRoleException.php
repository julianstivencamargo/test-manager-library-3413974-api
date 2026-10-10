<?php

declare(strict_types=1);

namespace Library\Domain\Exceptions;

final class ForbiddenRoleException extends LibraryBusinessException
{
    public function __construct(string $message = 'No tiene permisos para realizar esta acción.')
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return 'FORBIDDEN_ROLE';
    }

    public function httpStatus(): int
    {
        return 403;
    }
}
