<?php

/**
 * Copyright © 2025 Mage-OS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Model\Product;

use MageOS\CatalogDataAI\Model\Product\Request;
use PHPUnit\Framework\TestCase;

class RequestTest extends TestCase
{
    public function testGetIdReturnsConstructorValue(): void
    {
        $request = new Request(42, true);
        $this->assertSame(42, $request->getId());
    }

    public function testGetOverwriteReturnsTrue(): void
    {
        $request = new Request(1, true);
        $this->assertTrue($request->getOverwrite());
    }

    public function testGetOverwriteReturnsFalse(): void
    {
        $request = new Request(1, false);
        $this->assertFalse($request->getOverwrite());
    }
}
