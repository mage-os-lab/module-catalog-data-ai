<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Model\Product;

use MageOS\CatalogDataAI\Model\Product\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function test_request_carries_store_id(): void
    {
        $request = new Request(42, true, 3);

        $this->assertEquals(42, $request->getId());
        $this->assertTrue($request->getOverwrite());
        $this->assertEquals(3, $request->getStoreId());
    }

    public function test_store_id_defaults_to_zero(): void
    {
        $request = new Request(42, false);

        $this->assertEquals(0, $request->getStoreId());
    }
}
