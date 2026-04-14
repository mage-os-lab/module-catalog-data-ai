<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Model\Product;

use MageOS\CatalogDataAI\Model\Config;
use MageOS\CatalogDataAI\Model\Product\Enricher;
use MageOS\CatalogDataAI\Model\Product\EnrichmentLogger;
use Magento\Catalog\Model\Product;
use OpenAI\Factory;
use PHPUnit\Framework\TestCase;

final class EnricherTest extends TestCase
{
    public function test_get_attributes_returns_enabled_attribute_codes_from_config(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getEnrichableAttributes')->willReturn([
            'description' => 'describe {{name}}',
            'custom_field' => 'generate {{name}} custom',
        ]);

        $factory = $this->createMock(Factory::class);
        $logger = $this->createMock(EnrichmentLogger::class);
        $enricher = new Enricher($factory, $config, $logger);

        $this->assertEquals(['description', 'custom_field'], $enricher->getAttributes());
    }

    public function test_parse_prompt_replaces_placeholders_with_product_data(): void
    {
        $config = $this->createMock(Config::class);
        $factory = $this->createMock(Factory::class);
        $logger = $this->createMock(EnrichmentLogger::class);
        $enricher = new Enricher($factory, $config, $logger);

        $product = $this->createMock(Product::class);
        $product->method('getData')
            ->willReturnMap([
                ['name', null, 'Cool Widget'],
                ['price', null, '29.99'],
            ]);

        $result = $enricher->parsePrompt('describe {{name}} at {{price}}', $product);

        $this->assertEquals('describe Cool Widget at 29.99', $result);
    }
}
