<?php

declare(strict_types=1);

namespace Library\Domain\Entities;

use DateTimeImmutable;
use Library\Domain\Exceptions\DuplicateBookInLoanException;
use Library\Domain\Exceptions\ValidationException;

final class Loan
{
    /**
     * @param array<int, LoanItem> $items
     */
    public function __construct(
        private string $id,
        private string $memberId,
        private string $loanedByUserId,
        private DateTimeImmutable $loanedAt,
        private array $items = []
    ) {
        if (count($items) < 1 || count($items) > 3) {
            throw new ValidationException('Un préstamo debe tener entre 1 y 3 libros.');
        }

        $bookIds = [];
        foreach ($items as $item) {
            if (in_array($item->bookId(), $bookIds, true)) {
                throw new DuplicateBookInLoanException('No se puede incluir el mismo libro más de una vez en un préstamo.');
            }
            $bookIds[] = $item->bookId();
        }
    }

    public function id(): string
    {
        return $this->id;
    }

    public function memberId(): string
    {
        return $this->memberId;
    }

    public function loanedByUserId(): string
    {
        return $this->loanedByUserId;
    }

    public function loanedAt(): DateTimeImmutable
    {
        return $this->loanedAt;
    }

    /**
     * @return array<int, LoanItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function calculateStatus(DateTimeImmutable $now): string
    {
        $allReturned = true;
        $hasOverdue = false;

        foreach ($this->items as $item) {
            if (!$item->isReturned()) {
                $allReturned = false;
                if ($item->isOverdue($now)) {
                    $hasOverdue = true;
                }
            }
        }

        if ($allReturned) {
            return 'closed';
        }

        if ($hasOverdue) {
            return 'overdue';
        }

        return 'open';
    }
}
