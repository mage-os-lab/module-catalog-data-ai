<?php

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Model\Product;

use MageOS\CatalogDataAI\Model\Product\AttributeExtractor;
use PHPUnit\Framework\TestCase;

final class AttributeExtractorTest extends TestCase
{
    public function test_resolve_mapped_value_exact_match(): void
    {
        $mapping = ['Red' => '42', 'Blue' => '43', 'Green' => '44'];
        $result = AttributeExtractor::resolveMappedValue('Red', $mapping);
        $this->assertEquals('42', $result);
    }

    public function test_resolve_mapped_value_case_insensitive(): void
    {
        $mapping = ['Red' => '42', 'Blue' => '43'];
        $result = AttributeExtractor::resolveMappedValue('red', $mapping);
        $this->assertEquals('42', $result);
    }

    public function test_resolve_mapped_value_fuzzy_match(): void
    {
        $mapping = ['Crimson Red' => '42', 'Ocean Blue' => '43'];
        $result = AttributeExtractor::resolveMappedValue('crimson', $mapping);
        $this->assertEquals('42', $result);
    }

    public function test_resolve_mapped_value_returns_null_on_no_match(): void
    {
        $mapping = ['Red' => '42', 'Blue' => '43'];
        $result = AttributeExtractor::resolveMappedValue('Yellow', $mapping);
        $this->assertNull($result);
    }
}
