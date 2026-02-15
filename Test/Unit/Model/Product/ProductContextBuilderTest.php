<?php

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Model\Product;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use MageOS\CatalogDataAI\Model\Config;
use MageOS\CatalogDataAI\Model\Product\ProductContextBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProductContextBuilderTest extends TestCase
{
    private Config&MockObject $config;
    private ProductContextBuilder $builder;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->builder = new ProductContextBuilder($this->config);
    }

    public function testBuildIncludesVisibleOnFrontAttributes(): void
    {
        $this->config->method('getContextValueMaxLength')->willReturn(500);

        $attribute = $this->createAttributeMock('color', 'Color', visibleOnFront: true);
        $product = $this->createProductMockWithAttributes(
            [$attribute],
            ['color' => 'Red']
        );

        $result = $this->builder->build($product);

        $this->assertSame('Color: Red', $result);
    }

    public function testBuildIncludesSearchableAttributes(): void
    {
        $this->config->method('getContextValueMaxLength')->willReturn(500);

        $attribute = $this->createAttributeMock('sku', 'SKU', searchable: true);
        $product = $this->createProductMockWithAttributes(
            [$attribute],
            ['sku' => 'ABC-123']
        );

        $result = $this->builder->build($product);

        $this->assertSame('SKU: ABC-123', $result);
    }

    public function testBuildExcludesNonVisibleNonSearchableAttributes(): void
    {
        $this->config->method('getContextValueMaxLength')->willReturn(500);

        $visible = $this->createAttributeMock('name', 'Name', visibleOnFront: true);
        $hidden = $this->createAttributeMock('internal_code', 'Internal Code');
        $product = $this->createProductMockWithAttributes(
            [$visible, $hidden],
            ['name' => 'Widget', 'internal_code' => 'X99']
        );

        $result = $this->builder->build($product);

        $this->assertSame('Name: Widget', $result);
    }

    public function testBuildSkipsEmptyValues(): void
    {
        $this->config->method('getContextValueMaxLength')->willReturn(500);

        $attr1 = $this->createAttributeMock('name', 'Name', visibleOnFront: true);
        $attr2 = $this->createAttributeMock('color', 'Color', visibleOnFront: true);
        $product = $this->createProductMockWithAttributes(
            [$attr1, $attr2],
            ['name' => 'Widget', 'color' => '']
        );

        $result = $this->builder->build($product);

        $this->assertSame('Name: Widget', $result);
    }

    public function testBuildSkipsNullValues(): void
    {
        $this->config->method('getContextValueMaxLength')->willReturn(500);

        $attr = $this->createAttributeMock('color', 'Color', visibleOnFront: true);
        $product = $this->createProductMockWithAttributes(
            [$attr],
            ['color' => null]
        );

        $result = $this->builder->build($product);

        $this->assertSame('', $result);
    }

    public function testBuildSkipsArrayValues(): void
    {
        $this->config->method('getContextValueMaxLength')->willReturn(500);

        $attr1 = $this->createAttributeMock('name', 'Name', visibleOnFront: true);
        $attr2 = $this->createAttributeMock('media_gallery', 'Gallery', visibleOnFront: true);
        $product = $this->createProductMockWithAttributes(
            [$attr1, $attr2],
            ['name' => 'Widget', 'media_gallery' => ['img1.jpg', 'img2.jpg']]
        );

        $result = $this->builder->build($product);

        $this->assertSame('Name: Widget', $result);
    }

    public function testBuildTruncatesLongValues(): void
    {
        $this->config->method('getContextValueMaxLength')->willReturn(10);

        $attr = $this->createAttributeMock('description', 'Description', visibleOnFront: true);
        $product = $this->createProductMockWithAttributes(
            [$attr],
            ['description' => 'This is a very long description text']
        );

        $result = $this->builder->build($product);

        $this->assertSame('Description: This is a ...', $result);
    }

    public function testBuildDisablesTruncationWhenZero(): void
    {
        $this->config->method('getContextValueMaxLength')->willReturn(0);

        $longValue = str_repeat('x', 5000);
        $attr = $this->createAttributeMock('description', 'Description', visibleOnFront: true);
        $product = $this->createProductMockWithAttributes(
            [$attr],
            ['description' => $longValue]
        );

        $result = $this->builder->build($product);

        $this->assertSame('Description: ' . $longValue, $result);
    }

    public function testBuildFallsBackToAttributeCodeWhenNoLabel(): void
    {
        $this->config->method('getContextValueMaxLength')->willReturn(500);

        $attr = $this->createAttributeMock('custom_attr', '', visibleOnFront: true);
        $product = $this->createProductMockWithAttributes(
            [$attr],
            ['custom_attr' => 'value']
        );

        $result = $this->builder->build($product);

        $this->assertSame('custom_attr: value', $result);
    }

    public function testBuildMultipleAttributesJoinedByNewline(): void
    {
        $this->config->method('getContextValueMaxLength')->willReturn(500);

        $attr1 = $this->createAttributeMock('name', 'Name', visibleOnFront: true);
        $attr2 = $this->createAttributeMock('color', 'Color', searchable: true);
        $product = $this->createProductMockWithAttributes(
            [$attr1, $attr2],
            ['name' => 'Widget', 'color' => 'Blue']
        );

        $result = $this->builder->build($product);

        $this->assertSame("Name: Widget\nColor: Blue", $result);
    }

    public function testBuildReturnsEmptyStringForProductWithNoQualifyingAttributes(): void
    {
        $this->config->method('getContextValueMaxLength')->willReturn(500);

        $product = $this->createProductMockWithAttributes([], []);

        $result = $this->builder->build($product);

        $this->assertSame('', $result);
    }

    // --- Helpers ---

    private function createAttributeMock(
        string $code,
        string $label,
        bool $visibleOnFront = false,
        bool $searchable = false
    ): Attribute&MockObject {
        $attr = $this->createMock(Attribute::class);
        $attr->method('getAttributeCode')->willReturn($code);
        $attr->method('getStoreLabel')->willReturn($label);
        $attr->method('getIsVisibleOnFront')->willReturn($visibleOnFront);
        $attr->method('getIsSearchable')->willReturn($searchable);
        return $attr;
    }

    private function createProductMockWithAttributes(
        array $attributes,
        array $data
    ): Product&MockObject {
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->getMock();

        $product->method('getAttributes')->willReturn($attributes);
        $product->method('getDataUsingMethod')
            ->willReturnCallback(fn(string $key) => $data[$key] ?? null);

        return $product;
    }
}
