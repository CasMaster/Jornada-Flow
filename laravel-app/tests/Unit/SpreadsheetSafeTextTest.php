<?php

namespace Tests\Unit;

use App\Support\SpreadsheetSafeText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SpreadsheetSafeTextTest extends TestCase
{
    #[DataProvider('dangerousValues')]
    public function test_it_neutralizes_spreadsheet_formula_prefixes(string $value): void
    {
        $this->assertSame("'".$value, SpreadsheetSafeText::csv($value));
    }

    public static function dangerousValues(): array
    {
        return [['=1+1'], ['+1'], ['-1'], ['@SUM(A1)'], ["\tformula"], ["\rformula"], ["\nformula"]];
    }

    public function test_it_preserves_ordinary_text(): void
    {
        $this->assertSame('Maria da Silva', SpreadsheetSafeText::csv('Maria da Silva'));
    }
}
