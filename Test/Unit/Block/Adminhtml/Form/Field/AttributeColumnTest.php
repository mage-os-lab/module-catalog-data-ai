<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Block\Adminhtml\Form\Field;

use MageOS\CatalogDataAI\Block\Adminhtml\Form\Field\AttributeColumn;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchResultsInterface;
use PHPUnit\Framework\TestCase;

final class AttributeColumnTest extends TestCase
{
    public function test_get_options_returns_text_and_textarea_attributes(): void
    {
        $attribute1 = $this->createMock(ProductAttributeInterface::class);
        $attribute1->method('getAttributeCode')->willReturn('description');
        $attribute1->method('getDefaultFrontendLabel')->willReturn('Description');

        $attribute2 = $this->createMock(ProductAttributeInterface::class);
        $attribute2->method('getAttributeCode')->willReturn('short_description');
        $attribute2->method('getDefaultFrontendLabel')->willReturn('Short Description');

        $searchResults = $this->createMock(SearchResultsInterface::class);
        $searchResults->method('getItems')->willReturn([$attribute1, $attribute2]);

        $searchCriteria = $this->createMock(SearchCriteria::class);

        $searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $searchCriteriaBuilder->method('addFilter')->willReturnSelf();
        $searchCriteriaBuilder->method('create')->willReturn($searchCriteria);

        $attributeRepository = $this->createMock(ProductAttributeRepositoryInterface::class);
        $attributeRepository->method('getList')->willReturn($searchResults);

        $column = new AttributeColumn($attributeRepository, $searchCriteriaBuilder);
        $options = $column->getOptions();

        $this->assertArrayHasKey('description', $options);
        $this->assertArrayHasKey('short_description', $options);
        $this->assertEquals('Description', $options['description']);
        $this->assertEquals('Short Description', $options['short_description']);
    }
}
