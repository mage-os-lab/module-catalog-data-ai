<?php

/**
 * Copyright © 2025 Mage-OS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Model\Product;

use MageOS\CatalogDataAI\Model\Product\HashGenerator;
use PHPUnit\Framework\TestCase;

class HashGeneratorTest extends TestCase
{
    private HashGenerator $hashGenerator;

    protected function setUp(): void
    {
        $this->hashGenerator = new HashGenerator();
    }

    public function testGenerateReturnsSha256HexString(): void
    {
        $result = $this->hashGenerator->generate('prompt', 'system', 'description', 1);
        $expected = hash('sha256', 'prompt|system|description|1');

        $this->assertSame($expected, $result);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result);
    }

    public function testGenerateIsDeterministic(): void
    {
        $result1 = $this->hashGenerator->generate('prompt', 'system', 'attr', 0);
        $result2 = $this->hashGenerator->generate('prompt', 'system', 'attr', 0);

        $this->assertSame($result1, $result2);
    }

    public function testGenerateDiffersOnPromptChange(): void
    {
        $result1 = $this->hashGenerator->generate('prompt1', 'system', 'attr', 0);
        $result2 = $this->hashGenerator->generate('prompt2', 'system', 'attr', 0);

        $this->assertNotSame($result1, $result2);
    }

    public function testGenerateDiffersOnAttributeChange(): void
    {
        $result1 = $this->hashGenerator->generate('prompt', 'system', 'name', 0);
        $result2 = $this->hashGenerator->generate('prompt', 'system', 'description', 0);

        $this->assertNotSame($result1, $result2);
    }

    public function testGenerateDiffersOnStoreChange(): void
    {
        $result1 = $this->hashGenerator->generate('prompt', 'system', 'attr', 0);
        $result2 = $this->hashGenerator->generate('prompt', 'system', 'attr', 1);

        $this->assertNotSame($result1, $result2);
    }

    public function testGenerateDiffersOnSystemPromptChange(): void
    {
        $result1 = $this->hashGenerator->generate('prompt', 'system1', 'attr', 0);
        $result2 = $this->hashGenerator->generate('prompt', 'system2', 'attr', 0);

        $this->assertNotSame($result1, $result2);
    }
}
