<?php

declare(strict_types=1);

namespace Library\Domain\ValueObjects;

use DateTimeImmutable;
use DateTimeZone;
use Library\Domain\Exceptions\InvalidDateRangeException;

final readonly class DateRange
{
    private DateTimeImmutable $startDate;
    private DateTimeImmutable $endDate;

    public function __construct(mixed $startDate, mixed $endDate)
    {
        if (!$startDate instanceof DateTimeImmutable || !$endDate instanceof DateTimeImmutable) {
            throw new \TypeError('Las fechas deben ser instancias de DateTimeImmutable.');
        }

        $utcZone = new DateTimeZone('UTC');
        $startUtc = $startDate->setTimezone($utcZone);
        $endUtc = $endDate->setTimezone($utcZone);

        if ($endUtc < $startUtc) {
            throw new InvalidDateRangeException('La fecha de fin no puede ser anterior a la fecha de inicio.');
        }

        $this->startDate = $startUtc;
        $this->endDate = $endUtc;
    }

    public function startDate(): DateTimeImmutable
    {
        return $this->startDate;
    }

    public function endDate(): DateTimeImmutable
    {
        return $this->endDate;
    }

    public function includes(DateTimeImmutable $date): bool
    {
        $dateUtc = $date->setTimezone(new DateTimeZone('UTC'));

        return $this->startDate <= $dateUtc && $dateUtc <= $this->endDate;
    }

    public function equals(self $other): bool
    {
        return $this->startDate == $other->startDate && $this->endDate == $other->endDate;
    }
}
