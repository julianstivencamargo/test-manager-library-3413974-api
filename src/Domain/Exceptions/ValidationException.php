<?php

declare(strict_types=1);

namespace Library\Domain\Exceptions;

final class ValidationException extends LibraryBusinessException
{
    /**
     * @param array<int, array{field: string, issue: string}> $details
     */
    public function __construct(
        string $message = 'Error de validación en la solicitud.',
        private array $details = []
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return 'VALIDATION_ERROR';
    }

    public function httpStatus(): int
    {
        return 422;
    }

    public function details(): array
    {
        return $this->details;
    }
}
