<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use DateTimeImmutable;
use DateTimeZone;
use Library\Domain\Exceptions\InvalidDateRangeException;
use Library\Domain\Exceptions\InvalidIsbnException;
use Library\Domain\Exceptions\InvalidQuantityException;
use Library\Domain\ValueObjects\DateRange;
use Library\Domain\ValueObjects\Isbn;
use Library\Domain\ValueObjects\Quantity;
use PHPUnit\Framework\TestCase;

class ValueObjectsTest extends TestCase
{
    // A. ISBN tests (P-ISBN-01 to P-ISBN-15)
    public function test_p_isbn_01_valid_isbn_created_successfully(): void
    {
        $isbn = new Isbn('9789584276971');
        $this->assertSame('9789584276971', $isbn->value());
    }

    public function test_p_isbn_02_isbn_with_hyphens_normalized(): void
    {
        $isbn = new Isbn('978-958-42-7697-1');
        $this->assertSame('9789584276971', $isbn->value());
    }

    public function test_p_isbn_03_isbn_with_surrounding_whitespace_normalized(): void
    {
        $isbn = new Isbn('  9789584276971  ');
        $this->assertSame('9789584276971', $isbn->value());
    }

    public function test_p_isbn_04_isbn_with_leading_zeros_preserved(): void
    {
        $isbn = new Isbn('0123456789012');
        $this->assertSame('0123456789012', $isbn->value());
    }

    public function test_p_isbn_05_empty_string_throws_invalid_isbn_exception(): void
    {
        $this->expectException(InvalidIsbnException::class);
        new Isbn('');
    }

    public function test_p_isbn_06_12_digits_throws_invalid_isbn_exception(): void
    {
        $this->expectException(InvalidIsbnException::class);
        new Isbn('978123456789');
    }

    public function test_p_isbn_07_14_digits_throws_invalid_isbn_exception(): void
    {
        $this->expectException(InvalidIsbnException::class);
        new Isbn('97812345678901');
    }

    public function test_p_isbn_08_alphabetic_character_throws_invalid_isbn_exception(): void
    {
        $this->expectException(InvalidIsbnException::class);
        new Isbn('978123456789X');
    }

    public function test_p_isbn_09_letter_after_hyphens_throws_invalid_isbn_exception(): void
    {
        $this->expectException(InvalidIsbnException::class);
        new Isbn('978-1234-5678-A');
    }

    public function test_p_isbn_10_interior_spaces_throws_invalid_isbn_exception(): void
    {
        $this->expectException(InvalidIsbnException::class);
        new Isbn('978 1234 56789');
    }

    public function test_p_isbn_11_sign_characters_throws_invalid_isbn_exception(): void
    {
        $this->expectException(InvalidIsbnException::class);
        new Isbn('+9781234567890');
    }

    public function test_p_isbn_12_equals_comparison(): void
    {
        $isbn1 = new Isbn('978-958-42-7697-1');
        $isbn2 = new Isbn('9789584276971');
        $isbn3 = new Isbn('0123456789012');

        $this->assertTrue($isbn1->equals($isbn2));
        $this->assertFalse($isbn1->equals($isbn3));
    }

    public function test_p_isbn_13_to_string_matches_value(): void
    {
        $isbn = new Isbn('978-958-42-7697-1');
        $this->assertSame('9789584276971', (string) $isbn);
    }

    public function test_p_isbn_14_non_string_types_throw_type_error(): void
    {
        $this->expectException(\TypeError::class);
        new Isbn(12.5);
    }

    public function test_p_isbn_15_weak_typing_caller_passing_integer_throws_type_error_without_coercion(): void
    {
        $this->expectException(\TypeError::class);
        new Isbn(9789584276971);
    }

    // B. Quantity tests (P-QTY-01 to P-QTY-12)
    public function test_p_qty_01_minimum_positive_quantity(): void
    {
        $qty = new Quantity(1);
        $this->assertSame(1, $qty->value());
    }

    public function test_p_qty_02_positive_quantity(): void
    {
        $qty = new Quantity(100);
        $this->assertSame(100, $qty->value());
    }

    public function test_p_qty_03_zero_quantity_throws_invalid_quantity_exception(): void
    {
        $this->expectException(InvalidQuantityException::class);
        new Quantity(0);
    }

    public function test_p_qty_04_negative_quantity_throws_invalid_quantity_exception(): void
    {
        $this->expectException(InvalidQuantityException::class);
        new Quantity(-1);
    }

    public function test_p_qty_05_large_negative_quantity_throws_invalid_quantity_exception(): void
    {
        $this->expectException(InvalidQuantityException::class);
        new Quantity(-50);
    }

    public function test_p_qty_06_add_operation_returns_new_instance_with_sum(): void
    {
        $qty5 = new Quantity(5);
        $qty3 = new Quantity(3);
        $result = $qty5->add($qty3);

        $this->assertSame(8, $result->value());
        $this->assertNotSame($qty5, $result);
    }

    public function test_p_qty_07_add_operation_immutability(): void
    {
        $qty5 = new Quantity(5);
        $qty3 = new Quantity(3);
        $qty5->add($qty3);

        $this->assertSame(5, $qty5->value());
    }

    public function test_p_qty_08_overflow_detection_throws_invalid_quantity_exception(): void
    {
        $this->expectException(InvalidQuantityException::class);
        $max = new Quantity(PHP_INT_MAX);
        $one = new Quantity(1);
        $max->add($one);
    }

    public function test_p_qty_09_equals_identical_returns_true(): void
    {
        $qtyA = new Quantity(42);
        $qtyB = new Quantity(42);
        $this->assertTrue($qtyA->equals($qtyB));
    }

    public function test_p_qty_10_equals_different_returns_false(): void
    {
        $qtyA = new Quantity(42);
        $qtyB = new Quantity(43);
        $this->assertFalse($qtyA->equals($qtyB));
    }

    public function test_p_qty_11_non_int_types_throw_type_error(): void
    {
        $this->expectException(\TypeError::class);
        new Quantity(5.5);
    }

    public function test_p_qty_12_weak_typing_caller_passing_string_throws_type_error_without_coercion(): void
    {
        $this->expectException(\TypeError::class);
        new Quantity('10');
    }

    // C. DateRange tests (P-DR-01 to P-DR-10)
    public function test_p_dr_01_valid_range_created(): void
    {
        $start = new DateTimeImmutable('2026-01-01 10:00:00', new DateTimeZone('UTC'));
        $end = new DateTimeImmutable('2026-01-02 10:00:00', new DateTimeZone('UTC'));
        $range = new DateRange($start, $end);

        $this->assertSame('UTC', $range->startDate()->getTimezone()->getName());
        $this->assertSame('UTC', $range->endDate()->getTimezone()->getName());
        $this->assertSame($start->getTimestamp(), $range->startDate()->getTimestamp());
        $this->assertSame($end->getTimestamp(), $range->endDate()->getTimestamp());
    }

    public function test_p_dr_02_same_start_and_end_instant_allowed(): void
    {
        $instant = new DateTimeImmutable('2026-06-15 12:00:00', new DateTimeZone('UTC'));
        $range = new DateRange($instant, $instant);

        $this->assertTrue($range->startDate() == $range->endDate());
    }

    public function test_p_dr_03_end_before_start_throws_invalid_date_range_exception(): void
    {
        $this->expectException(InvalidDateRangeException::class);
        $start = new DateTimeImmutable('2026-01-02 10:00:00', new DateTimeZone('UTC'));
        $end = new DateTimeImmutable('2026-01-01 10:00:00', new DateTimeZone('UTC'));
        new DateRange($start, $end);
    }

    public function test_p_dr_04_different_timezones_normalized_to_utc(): void
    {
        $start = new DateTimeImmutable('2026-01-01 10:00:00', new DateTimeZone('America/Bogota'));
        $end = new DateTimeImmutable('2026-01-01 16:00:00', new DateTimeZone('UTC'));
        $range = new DateRange($start, $end);

        $this->assertSame('UTC', $range->startDate()->getTimezone()->getName());
        $this->assertSame('UTC', $range->endDate()->getTimezone()->getName());
        $this->assertTrue($range->startDate() < $range->endDate());
    }

    public function test_p_dr_05_different_timezones_same_absolute_instant(): void
    {
        $bogota = new DateTimeImmutable('2026-01-01 10:00:00', new DateTimeZone('America/Bogota'));
        $utc = new DateTimeImmutable('2026-01-01 15:00:00', new DateTimeZone('UTC'));
        $range = new DateRange($bogota, $utc);

        $this->assertTrue($range->startDate() == $range->endDate());
    }

    public function test_p_dr_06_explicit_normalization_and_immutability(): void
    {
        $bogotaStart = new DateTimeImmutable('2026-01-01 08:00:00', new DateTimeZone('America/Bogota'));
        $bogotaEnd = new DateTimeImmutable('2026-01-01 18:00:00', new DateTimeZone('America/Bogota'));

        $range = new DateRange($bogotaStart, $bogotaEnd);

        $this->assertSame('UTC', $range->startDate()->getTimezone()->getName());
        $this->assertSame('UTC', $range->endDate()->getTimezone()->getName());
        $this->assertSame($bogotaStart->getTimestamp(), $range->startDate()->getTimestamp());
        $this->assertSame($bogotaEnd->getTimestamp(), $range->endDate()->getTimestamp());

        $this->assertSame('America/Bogota', $bogotaStart->getTimezone()->getName());
        $this->assertSame('America/Bogota', $bogotaEnd->getTimezone()->getName());
    }

    public function test_p_dr_07_includes_intermediate_instant(): void
    {
        $start = new DateTimeImmutable('2026-01-01 10:00:00', new DateTimeZone('UTC'));
        $end = new DateTimeImmutable('2026-01-03 10:00:00', new DateTimeZone('UTC'));
        $range = new DateRange($start, $end);

        $mid = new DateTimeImmutable('2026-01-02 10:00:00', new DateTimeZone('UTC'));
        $this->assertTrue($range->includes($mid));
    }

    public function test_p_dr_08_includes_exact_boundaries(): void
    {
        $start = new DateTimeImmutable('2026-01-01 10:00:00', new DateTimeZone('UTC'));
        $end = new DateTimeImmutable('2026-01-03 10:00:00', new DateTimeZone('UTC'));
        $range = new DateRange($start, $end);

        $this->assertTrue($range->includes($start));
        $this->assertTrue($range->includes($end));
    }

    public function test_p_dr_09_includes_outside_returns_false_and_equals_validates_equality(): void
    {
        $start = new DateTimeImmutable('2026-01-01 10:00:00', new DateTimeZone('UTC'));
        $end = new DateTimeImmutable('2026-01-03 10:00:00', new DateTimeZone('UTC'));
        $range1 = new DateRange($start, $end);
        $range2 = new DateRange($start, $end);
        $range3 = new DateRange($start, new DateTimeImmutable('2026-01-04 10:00:00', new DateTimeZone('UTC')));

        $before = new DateTimeImmutable('2026-01-01 09:59:59', new DateTimeZone('UTC'));
        $after = new DateTimeImmutable('2026-01-03 10:00:01', new DateTimeZone('UTC'));

        $this->assertFalse($range1->includes($before));
        $this->assertFalse($range1->includes($after));
        $this->assertTrue($range1->equals($range2));
        $this->assertFalse($range1->equals($range3));
    }

    public function test_p_dr_10_non_datetime_immutable_types_throw_type_error(): void
    {
        $this->expectException(\TypeError::class);
        new DateRange('2026-01-01', '2026-01-02');
    }
}
