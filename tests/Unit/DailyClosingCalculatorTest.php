<?php

namespace Tests\Unit;

use App\Services\DailyClosingCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DailyClosingCalculatorTest extends TestCase
{
    public function test_zero_difference_is_klop(): void
    {
        $result = (new DailyClosingCalculator())->calculate(100000, 70000, 20000, 10000);

        self::assertSame(100000, $result['total_accountability']);
        self::assertSame(0, $result['difference']);
        self::assertSame('KLOP', $result['difference_status']);
    }

    public function test_negative_difference_is_minus(): void
    {
        $result = (new DailyClosingCalculator())->calculate(100000, 50000, 20000, 10000);

        self::assertSame(-20000, $result['difference']);
        self::assertSame('MINUS', $result['difference_status']);
    }

    public function test_positive_difference_is_plus(): void
    {
        $result = (new DailyClosingCalculator())->calculate(100000, 80000, 20000, 10000);

        self::assertSame(10000, $result['difference']);
        self::assertSame('PLUS', $result['difference_status']);
    }

    public function test_negative_amount_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DailyClosingCalculator())->calculate(100000, -1, 0, 0);
    }
}
