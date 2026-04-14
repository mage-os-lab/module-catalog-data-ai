<?php

/**
 * Copyright © 2025 Mage-OS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Model\Product;

use MageOS\CatalogDataAI\Model\Product\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
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

    public function test_request_carries_store_id(): void
    {
        $request = new Request(42, true, 3);

        $this->assertSame(42, $request->getId());
        $this->assertTrue($request->getOverwrite());
        $this->assertSame(3, $request->getStoreId());
    }

    public function test_store_id_defaults_to_zero(): void
    {
        $request = new Request(42, false);

        $this->assertSame(0, $request->getStoreId());
    }
}
