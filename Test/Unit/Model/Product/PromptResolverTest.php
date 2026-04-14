<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Model\Product;

use MageOS\CatalogDataAI\Model\Config;
use MageOS\CatalogDataAI\Model\Product\PromptResolver;
use MageOS\CatalogDataAI\Model\PromptRule;
use MageOS\CatalogDataAI\Model\ResourceModel\PromptRule\Collection;
use MageOS\CatalogDataAI\Model\ResourceModel\PromptRule\CollectionFactory;
use Magento\Catalog\Model\Product;
use PHPUnit\Framework\TestCase;

final class PromptResolverTest extends TestCase
{
    public function test_returns_highest_priority_matching_rule_prompt(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getStoreId')->willReturn(1);

        $lowRule = $this->createMock(PromptRule::class);
        $lowRule->method('getIsActive')->willReturn(true);
        $lowRule->method('getPriority')->willReturn(10);
        $lowRule->method('getPrompt')->willReturn('low priority prompt');
        $lowRule->method('matchesStore')->willReturn(true);
        $lowRule->method('matchesProduct')->willReturn(true);

        $highRule = $this->createMock(PromptRule::class);
        $highRule->method('getIsActive')->willReturn(true);
        $highRule->method('getPriority')->willReturn(50);
        $highRule->method('getPrompt')->willReturn('high priority prompt');
        $highRule->method('matchesStore')->willReturn(true);
        $highRule->method('matchesProduct')->willReturn(true);

        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setOrder')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$highRule, $lowRule]));

        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $config = $this->createMock(Config::class);

        $resolver = new PromptResolver($collectionFactory, $config);
        $result = $resolver->resolve('description', $product);

        $this->assertEquals('high priority prompt', $result);
    }

    public function test_falls_back_to_config_when_no_rule_matches(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getStoreId')->willReturn(1);

        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setOrder')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator([]));

        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $config = $this->createMock(Config::class);
        $config->method('getProductPrompt')
            ->with('description')
            ->willReturn('default config prompt');

        $resolver = new PromptResolver($collectionFactory, $config);
        $result = $resolver->resolve('description', $product);

        $this->assertEquals('default config prompt', $result);
    }
}
