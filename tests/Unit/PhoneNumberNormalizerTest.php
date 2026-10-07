<?php

namespace Tests\Unit;

use App\Support\PhoneNumberNormalizer;
use PHPUnit\Framework\TestCase;

class PhoneNumberNormalizerTest extends TestCase
{
    public function test_normalizes_standard_indonesian_mobile_numbers(): void
    {
        $this->assertEquals('628123456789', PhoneNumberNormalizer::normalize('08123456789'));
        $this->assertEquals('628123456789', PhoneNumberNormalizer::normalize('+628123456789'));
        $this->assertEquals('628123456789', PhoneNumberNormalizer::normalize('628123456789'));
        $this->assertEquals('6285212345678', PhoneNumberNormalizer::normalize('0852-1234-5678'));
        $this->assertEquals('6281912345678', PhoneNumberNormalizer::normalize('+62 819 1234 5678'));
    }

    public function test_rejects_toll_free_numbers(): void
    {
        $this->assertFalse(PhoneNumberNormalizer::isValid('08001234567'));
        $this->assertFalse(PhoneNumberNormalizer::isValid('628001234567'));
        $this->assertNull(PhoneNumberNormalizer::normalize('08001234567'));
    }

    public function test_rejects_invalid_short_or_long_numbers(): void
    {
        // Terlalu pendek (< 10 digit)
        $this->assertFalse(PhoneNumberNormalizer::isValid('0812345'));
        $this->assertNull(PhoneNumberNormalizer::normalize('0812345'));

        // Terlalu panjang (> 15 digit)
        $this->assertFalse(PhoneNumberNormalizer::isValid('081234567890123456'));
        $this->assertNull(PhoneNumberNormalizer::normalize('081234567890123456'));
    }

    public function test_rejects_non_mobile_or_foreign_numbers(): void
    {
        // Bukan nomor seluler 08...
        $this->assertFalse(PhoneNumberNormalizer::isValid('0215551234'));
        $this->assertFalse(PhoneNumberNormalizer::isValid('+14155552671'));
        $this->assertFalse(PhoneNumberNormalizer::isValid('random-letters'));
    }

    public function test_formats_to_display_correctly(): void
    {
        $this->assertEquals('+62 812-3456-789', PhoneNumberNormalizer::toDisplayFormat('628123456789'));
        $this->assertEquals('-', PhoneNumberNormalizer::toDisplayFormat(null));
        $this->assertEquals('-', PhoneNumberNormalizer::toDisplayFormat(''));
    }
}
