<?php

declare(strict_types=1);

namespace Library\Domain\Exceptions;

abstract class LibraryBusinessException extends DomainException
{
    abstract public function errorCode(): string;
    abstract public function httpStatus(): int;

    /**
     * @return array<int, array{field: string, issue: string}>
     */
    public function details(): array
    {
        return [];
    }
}
