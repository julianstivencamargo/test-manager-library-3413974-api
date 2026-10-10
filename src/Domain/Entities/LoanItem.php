<?php

declare(strict_types=1);

namespace Library\Domain\Entities;

use DateTimeImmutable;
use Library\Domain\Exceptions\ItemAlreadyReturnedException;

final class LoanItem
{
    public function __construct(
        private string $id,
        private string $loanId,
        private string $bookId,
        private string $bookTitle,
        private string $isbn,
        private string $categoryName,
        private DateTimeImmutable $dueAt,
        private ?DateTimeImmutable $returnedAt = null,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function loanId(): string
    {
        return $this->loanId;
    }

    public function bookId(): string
    {
        return $this->bookId;
    }

    public function bookTitle(): string
    {
        return $this->bookTitle;
    }

    public function isbn(): string
    {
        return $this->isbn;
    }

    public function categoryName(): string
    {
        return $this->categoryName;
    }

    public function dueAt(): DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function returnedAt(): ?DateTimeImmutable
    {
        return $this->returnedAt;
    }

    public function isReturned(): bool
    {
        return $this->returnedAt !== null;
    }

    public function isOverdue(DateTimeImmutable $now): bool
    {
        return !$this->isReturned() && $this->dueAt < $now;
    }

    public function daysLate(DateTimeImmutable $now): int
    {
        if (!$this->isOverdue($now)) {
            return 0;
        }

        $diffSeconds = $now->getTimestamp() - $this->dueAt->getTimestamp();

        return (int) ceil($diffSeconds / 86400);
    }

    public function returnItem(DateTimeImmutable $returnedAt): void
    {
        if ($this->returnedAt !== null) {
            throw new ItemAlreadyReturnedException("La línea de préstamo ya fue devuelta previamente.");
        }

        $this->returnedAt = $returnedAt;
    }
}
