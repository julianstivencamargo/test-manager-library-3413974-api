<?php

declare(strict_types=1);

namespace Library\Domain\Exceptions;

final class MemberLimitExceededException extends LibraryBusinessException
{
    public function __construct(string $message = 'El socio no puede tener más de 3 libros sin devolver.')
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return 'MEMBER_LIMIT_EXCEEDED';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
