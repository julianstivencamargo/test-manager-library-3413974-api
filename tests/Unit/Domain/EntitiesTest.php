<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use DateTimeImmutable;
use DateTimeZone;
use Library\Domain\Entities\Book;
use Library\Domain\Entities\Loan;
use Library\Domain\Entities\LoanItem;
use Library\Domain\Entities\Member;
use Library\Domain\Entities\User;
use Library\Domain\Exceptions\BookNotAvailableException;
use Library\Domain\Exceptions\DuplicateBookInLoanException;
use Library\Domain\Exceptions\ItemAlreadyReturnedException;
use Library\Domain\Exceptions\ValidationException;
use Library\Domain\ValueObjects\Isbn;
use Library\Domain\ValueObjects\Quantity;
use PHPUnit\Framework\TestCase;

class EntitiesTest extends TestCase
{
    public function test_book_withdraw_and_restock(): void
    {
        $book = new Book(
            id: 'b1',
            title: 'Clean Code',
            author: 'Robert C. Martin',
            isbn: new Isbn('9780132350884'),
            categoryId: 'c1',
            totalCopies: new Quantity(3),
            availableCopies: 3
        );

        $this->assertSame(3, $book->availableCopies());
        $book->withdraw();
        $this->assertSame(2, $book->availableCopies());
        $book->withdraw();
        $book->withdraw();
        $this->assertSame(0, $book->availableCopies());

        $this->expectException(BookNotAvailableException::class);
        $book->withdraw();
    }

    public function test_book_restock_cannot_exceed_total(): void
    {
        $book = new Book(
            id: 'b1',
            title: 'Clean Code',
            author: 'Robert C. Martin',
            isbn: new Isbn('9780132350884'),
            categoryId: 'c1',
            totalCopies: new Quantity(2),
            availableCopies: 2
        );

        $this->expectException(ValidationException::class);
        $book->restock();
    }

    public function test_book_adjust_total_copies(): void
    {
        $book = new Book(
            id: 'b1',
            title: 'Clean Code',
            author: 'Robert C. Martin',
            isbn: new Isbn('9780132350884'),
            categoryId: 'c1',
            totalCopies: new Quantity(5),
            availableCopies: 3
        );

        $book->adjustTotalCopies(4);
        $this->assertSame(4, $book->totalCopies()->value());
        $this->assertSame(2, $book->availableCopies());

        $this->expectException(ValidationException::class);
        $book->adjustTotalCopies(1); // 3 + (1 - 4) = 0 ok, but total was 4, diff is -3 => available = 2 - 3 = -1 => throws
    }

    public function test_loan_item_return_twice_throws_exception(): void
    {
        $due = new DateTimeImmutable('+14 days', new DateTimeZone('UTC'));
        $item = new LoanItem('li1', 'l1', 'b1', 'Clean Code', '9780132350884', 'Tecnología', $due);

        $this->assertFalse($item->isReturned());
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $item->returnItem($now);
        $this->assertTrue($item->isReturned());

        $this->expectException(ItemAlreadyReturnedException::class);
        $item->returnItem($now);
    }

    public function test_loan_duplicate_books_throws_exception(): void
    {
        $due = new DateTimeImmutable('+14 days', new DateTimeZone('UTC'));
        $item1 = new LoanItem('li1', 'l1', 'b1', 'Clean Code', '9780132350884', 'Tecnología', $due);
        $item2 = new LoanItem('li2', 'l1', 'b1', 'Clean Code', '9780132350884', 'Tecnología', $due);

        $this->expectException(DuplicateBookInLoanException::class);
        new Loan('l1', 'm1', 'u1', new DateTimeImmutable('now', new DateTimeZone('UTC')), [$item1, $item2]);
    }

    public function test_loan_status_calculation(): void
    {
        $now = new DateTimeImmutable('2026-10-15 12:00:00', new DateTimeZone('UTC'));
        $dueFuture = new DateTimeImmutable('2026-10-20 12:00:00', new DateTimeZone('UTC'));
        $duePast = new DateTimeImmutable('2026-10-10 12:00:00', new DateTimeZone('UTC'));

        $itemOpen = new LoanItem('li1', 'l1', 'b1', 'B1', '9780132350884', 'Cat', $dueFuture);
        $loanOpen = new Loan('l1', 'm1', 'u1', $now, [$itemOpen]);
        $this->assertSame('open', $loanOpen->calculateStatus($now));

        $itemOverdue = new LoanItem('li2', 'l2', 'b2', 'B2', '9780132350884', 'Cat', $duePast);
        $loanOverdue = new Loan('l2', 'm1', 'u1', $now, [$itemOpen, $itemOverdue]);
        $this->assertSame('overdue', $loanOverdue->calculateStatus($now));

        $itemOpen->returnItem($now);
        $itemOverdue->returnItem($now);
        $this->assertSame('closed', $loanOverdue->calculateStatus($now));
    }

    public function test_user_normalizes_username_and_validates_role(): void
    {
        $user = new User('u1', '  Ana ', 'hashed_pwd', 'librarian');
        $this->assertSame('ana', $user->username());

        $this->expectException(ValidationException::class);
        new User('u2', 'admin_bad', 'hashed_pwd', 'superadmin');
    }
}
