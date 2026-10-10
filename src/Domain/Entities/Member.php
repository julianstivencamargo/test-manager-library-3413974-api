<?php

declare(strict_types=1);

namespace Library\Domain\Entities;

final readonly class Member
{
    public function __construct(
        private string $id,
        private string $fullName,
        private string $documentNumber,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function fullName(): string
    {
        return $this->fullName;
    }

    public function documentNumber(): string
    {
        return $this->documentNumber;
    }
}
