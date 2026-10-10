<?php

declare(strict_types=1);

namespace Library\Domain\ValueObjects;

use Library\Domain\Exceptions\InvalidQuantityException;

final readonly class Quantity
{
    private int $value;

    public function __construct(mixed $value)
    {
        if (!is_int($value)) {
            throw new \TypeError('La cantidad debe ser un número entero (int).');
        }

        if ($value <= 0) {
            throw new InvalidQuantityException("La cantidad debe ser mayor que 0. Valor recibido: {$value}");
        }

        $this->value = $value;
    }

    public function value(): int
    {
        return $this->value;
    }

    public function add(self $other): self
    {
        if ($other->value() > PHP_INT_MAX - $this->value) {
            throw new InvalidQuantityException('Desbordamiento aritmético al sumar cantidades.');
        }

        return new self($this->value + $other->value());
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
