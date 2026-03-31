<?php

namespace Tests\Unit;

use App\Services\ImpressionEngine\LibH3Indexer;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LibH3IndexerHexTest extends TestCase
{
    #[Test]
    public function h3_hex_to_ffi_index_avoids_float_round_trip(): void
    {
        $hex15 = '8928308280fffff';
        $idx = LibH3Indexer::h3HexStringToFfiIndex($hex15);
        $this->assertIsInt($idx);
        $this->assertNotSame(0, $idx);
    }

    #[Test]
    public function h3_hex_high_bit_preserves_bit_pattern(): void
    {
        $hex = str_repeat('f', 16);
        $idx = LibH3Indexer::h3HexStringToFfiIndex($hex);
        $this->assertSame(-1, $idx);
    }

    #[Test]
    public function rejects_invalid_hex(): void
    {
        $this->expectException(InvalidArgumentException::class);
        LibH3Indexer::h3HexStringToFfiIndex('not-hex');
    }
}
