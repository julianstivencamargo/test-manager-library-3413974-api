<?php

declare(strict_types=1);

namespace Library\Domain\Exceptions;

final class BookNotAvailableException extends LibraryBusinessException
{
    /**
     * @param array<int, array{field: string, issue: string}> $details
     */
    public function __construct(
        string $message = 'No quedan ejemplares disponibles del libro solicitado.',
        private array $details = []
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return 'BOOK_NOT_AVAILABLE';
    }

    public function httpStatus(): int
    {
        return 409;
    }

    public function details(): array
    {
        return $this->details;
    }
}
