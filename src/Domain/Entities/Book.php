<?php

declare(strict_types=1);

namespace Library\Domain\Entities;

use DateTimeImmutable;
use DateTimeZone;
use Library\Domain\Exceptions\BookNotAvailableException;
use Library\Domain\Exceptions\ValidationException;
use Library\Domain\ValueObjects\Isbn;
use Library\Domain\ValueObjects\Quantity;

final class Book
{
    public function __construct(
        private string $id,
        private string $title,
        private string $author,
        private Isbn $isbn,
        private string $categoryId,
        private Quantity $totalCopies,
        private int $availableCopies,
        private ?string $coverKey = null,
        private ?DateTimeImmutable $deletedAt = null,
    ) {
        if ($this->availableCopies < 0 || $this->availableCopies > $this->totalCopies->value()) {
            throw new ValidationException('Los ejemplares disponibles deben estar entre 0 y el total.');
        }
    }

    public function id(): string
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function author(): string
    {
        return $this->author;
    }

    public function isbn(): Isbn
    {
        return $this->isbn;
    }

    public function categoryId(): string
    {
        return $this->categoryId;
    }

    public function totalCopies(): Quantity
    {
        return $this->totalCopies;
    }

    public function availableCopies(): int
    {
        return $this->availableCopies;
    }

    public function coverKey(): ?string
    {
        return $this->coverKey;
    }

    public function deletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function isActive(): bool
    {
        return $this->deletedAt === null;
    }

    public function withdraw(): void
    {
        if ($this->availableCopies <= 0) {
            throw new BookNotAvailableException("No quedan ejemplares disponibles de «{$this->title}».");
        }

        $this->availableCopies--;
    }

    public function restock(): void
    {
        if ($this->availableCopies >= $this->totalCopies->value()) {
            throw new ValidationException('Los disponibles jamás pueden superar el total de ejemplares.');
        }

        $this->availableCopies++;
    }

    public function adjustTotalCopies(int $newTotal): void
    {
        $diff = $newTotal - $this->totalCopies->value();
        $newAvailable = $this->availableCopies + $diff;

        if ($newAvailable < 0) {
            throw new ValidationException('Ajustar el total dejaría los ejemplares disponibles en negativo.');
        }

        $this->totalCopies = new Quantity($newTotal);
        $this->availableCopies = $newAvailable;
    }

    public function updateDetails(string $title, string $author, string $categoryId, int $newTotal): void
    {
        $this->title = $title;
        $this->author = $author;
        $this->categoryId = $categoryId;
        $this->adjustTotalCopies($newTotal);
    }

    public function setCoverKey(?string $coverKey): void
    {
        $this->coverKey = $coverKey;
    }

    public function deactivate(): void
    {
        $this->deletedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
