<?php

namespace App\Services;

final class DailyClosingCalculator
{
    /**
     * @return array{total_accountability:int,difference:int,difference_status:string}
     */
    public function calculate(int $systemGross, int $countedCash, int $qrisSettlement, int $totalValidExpenses): array
    {
        foreach ([$systemGross, $countedCash, $qrisSettlement, $totalValidExpenses] as $amount) {
            if ($amount < 0) {
                throw new \InvalidArgumentException('Nominal pertanggungjawaban tidak boleh negatif.');
            }
        }

        $totalAccountability = $countedCash + $qrisSettlement + $totalValidExpenses;
        $difference = $totalAccountability - $systemGross;

        return [
            'total_accountability' => $totalAccountability,
            'difference' => $difference,
            'difference_status' => match (true) {
                $difference === 0 => 'KLOP',
                $difference < 0 => 'MINUS',
                default => 'PLUS',
            },
        ];
    }
}
