<?php

declare(strict_types=1);

namespace Library\Domain\ValueObjects;

use Library\Domain\Exceptions\InvalidIsbnException;

final readonly class Isbn
{
    private string $value;

    public function __construct(mixed $value)
    {
        if (!is_string($value)) {
            throw new \TypeError('ISBN debe ser una cadena de texto (string).');
        }

        $trimmed = trim($value);
        $normalized = str_replace('-', '', $trimmed);

        if (!preg_match('/^[0-9]{13}$/', $normalized)) {
            throw new InvalidIsbnException("El valor '{$value}' no es un ISBN-13 válido.");
        }

        $this->value = $normalized;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
