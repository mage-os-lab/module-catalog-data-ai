<?php

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Ui\DataProvider\Product\Form\Modifier;

use MageOS\CatalogDataAI\Api\Data\EnrichmentInterface;
use MageOS\CatalogDataAI\Api\Data\EnrichmentSearchResultsInterface;
use MageOS\CatalogDataAI\Api\EnrichmentRepositoryInterface;
use MageOS\CatalogDataAI\Model\Config;
use MageOS\CatalogDataAI\Ui\DataProvider\Product\Form\Modifier\EnrichmentStatus;
use Magento\Catalog\Model\Locator\LocatorInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Stdlib\ArrayManager;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Backend\Model\UrlInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EnrichmentStatusTest extends TestCase
{
    private EnrichmentStatus $modifier;
    private LocatorInterface&MockObject $locator;
    private EnrichmentRepositoryInterface&MockObject $enrichmentRepository;
    private SearchCriteriaBuilder&MockObject $searchCriteriaBuilder;
    private Config&MockObject $config;
    private UrlInterface&MockObject $urlBuilder;
    private ArrayManager $arrayManager;

    protected function setUp(): void
    {
        $this->locator = $this->createMock(LocatorInterface::class);
        $this->enrichmentRepository = $this->createMock(EnrichmentRepositoryInterface::class);
        $this->searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $this->config = $this->createMock(Config::class);
        $this->urlBuilder = $this->createMock(UrlInterface::class);
        $this->arrayManager = new ArrayManager();

        $this->searchCriteriaBuilder->method('addFilter')->willReturnSelf();
        $this->searchCriteriaBuilder->method('create')
            ->willReturn($this->createMock(SearchCriteria::class));

        $this->modifier = new EnrichmentStatus(
            $this->locator,
            $this->enrichmentRepository,
            $this->searchCriteriaBuilder,
            $this->config,
            $this->urlBuilder,
            $this->arrayManager
        );
    }

    public function testModifyDataReturnsUnchanged(): void
    {
        $data = ['product' => ['name' => 'Test']];
        $this->assertSame($data, $this->modifier->modifyData($data));
    }

    public function testModifyMetaReturnsUnchangedWhenCacheDisabled(): void
    {
        $this->config->method('isCacheEnabled')->willReturn(false);

        $meta = $this->buildMetaWithField('description');
        $this->assertSame($meta, $this->modifier->modifyMeta($meta));
    }

    public function testModifyMetaReturnsUnchangedForNewProduct(): void
    {
        $this->config->method('isCacheEnabled')->willReturn(true);
        $this->setupLocator(null, 1);

        $meta = $this->buildMetaWithField('description');
        $this->assertSame($meta, $this->modifier->modifyMeta($meta));
    }

    public function testModifyMetaReturnsUnchangedWhenNoEnrichments(): void
    {
        $this->config->method('isCacheEnabled')->willReturn(true);
        $this->setupLocator(42, 1);
        $this->setupEmptySearchResults();

        $meta = $this->buildMetaWithField('description');
        $this->assertSame($meta, $this->modifier->modifyMeta($meta));
    }

    public function testModifyMetaInjectsPendingStatus(): void
    {
        $this->config->method('isCacheEnabled')->willReturn(true);
        $this->setupLocator(42, 1);
        $this->setupProductAttributeValue('description', 'Some value');

        $enrichment = $this->createEnrichmentMock(
            10,
            'description',
            EnrichmentInterface::STATUS_PENDING,
            'AI generated text',
            null
        );
        $this->setupSearchResults([$enrichment]);
        $this->urlBuilder->method('getUrl')
            ->with('catalogai/enrichment/edit', ['id' => 10])
            ->willReturn('http://example.com/admin/catalogai/enrichment/edit/id/10');

        $meta = $this->buildMetaWithField('description');
        $result = $this->modifier->modifyMeta($meta);

        $additionalInfo = $this->extractAdditionalInfo($result, 'description');
        $this->assertNotNull($additionalInfo, 'additionalInfo should be set for enriched field');
        $this->assertStringContainsString('Pending review', $additionalInfo);
        $this->assertStringContainsString('catalogai-enrichment-note--pending', $additionalInfo);
        $this->assertStringContainsString('View details', $additionalInfo);
    }

    public function testModifyMetaInjectsApprovedStatus(): void
    {
        $this->config->method('isCacheEnabled')->willReturn(true);
        $this->setupLocator(42, 1);
        $this->setupProductAttributeValue('description', 'AI generated text');

        $enrichment = $this->createEnrichmentMock(
            11,
            'description',
            EnrichmentInterface::STATUS_APPLIED,
            'AI generated text',
            null
        );
        $this->setupSearchResults([$enrichment]);
        $this->urlBuilder->method('getUrl')->willReturn('http://example.com/admin/url');

        $meta = $this->buildMetaWithField('description');
        $result = $this->modifier->modifyMeta($meta);

        $additionalInfo = $this->extractAdditionalInfo($result, 'description');
        $this->assertStringContainsString('Approved', $additionalInfo);
        $this->assertStringContainsString('catalogai-enrichment-note--approved', $additionalInfo);
    }

    public function testModifyMetaInjectsModifiedStatus(): void
    {
        $this->config->method('isCacheEnabled')->willReturn(true);
        $this->setupLocator(42, 1);
        $this->setupProductAttributeValue('description', 'Manually edited value');

        $enrichment = $this->createEnrichmentMock(
            12,
            'description',
            EnrichmentInterface::STATUS_APPLIED,
            'AI generated text',
            'AI generated text'
        );
        $this->setupSearchResults([$enrichment]);
        $this->urlBuilder->method('getUrl')->willReturn('http://example.com/admin/url');

        $meta = $this->buildMetaWithField('description');
        $result = $this->modifier->modifyMeta($meta);

        $additionalInfo = $this->extractAdditionalInfo($result, 'description');
        $this->assertStringContainsString('Modified', $additionalInfo);
        $this->assertStringContainsString('catalogai-enrichment-note--modified', $additionalInfo);
    }

    public function testModifyMetaInjectsDeniedStatus(): void
    {
        $this->config->method('isCacheEnabled')->willReturn(true);
        $this->setupLocator(42, 1);
        $this->setupProductAttributeValue('description', 'Some value');

        $enrichment = $this->createEnrichmentMock(
            13,
            'description',
            EnrichmentInterface::STATUS_DENIED,
            'AI generated text',
            null
        );
        $this->setupSearchResults([$enrichment]);
        $this->urlBuilder->method('getUrl')->willReturn('http://example.com/admin/url');

        $meta = $this->buildMetaWithField('description');
        $result = $this->modifier->modifyMeta($meta);

        $additionalInfo = $this->extractAdditionalInfo($result, 'description');
        $this->assertStringContainsString('Denied', $additionalInfo);
        $this->assertStringContainsString('catalogai-enrichment-note--denied', $additionalInfo);
    }

    public function testModifyMetaUsesAppliedValueForComparison(): void
    {
        $this->config->method('isCacheEnabled')->willReturn(true);
        $this->setupLocator(42, 1);
        $this->setupProductAttributeValue('description', 'Admin edited applied value');

        $enrichment = $this->createEnrichmentMock(
            14,
            'description',
            EnrichmentInterface::STATUS_APPLIED,
            'Original AI text',
            'Admin edited applied value'
        );
        $this->setupSearchResults([$enrichment]);
        $this->urlBuilder->method('getUrl')->willReturn('http://example.com/admin/url');

        $meta = $this->buildMetaWithField('description');
        $result = $this->modifier->modifyMeta($meta);

        $additionalInfo = $this->extractAdditionalInfo($result, 'description');
        $this->assertStringContainsString('Approved', $additionalInfo);
    }

    public function testModifyMetaSkipsFieldNotInMeta(): void
    {
        $this->config->method('isCacheEnabled')->willReturn(true);
        $this->setupLocator(42, 1);
        $this->setupProductAttributeValue('nonexistent_attr', 'value');

        $enrichment = $this->createEnrichmentMock(
            15,
            'nonexistent_attr',
            EnrichmentInterface::STATUS_PENDING,
            'AI text',
            null
        );
        $this->setupSearchResults([$enrichment]);

        $meta = $this->buildMetaWithField('description');
        $result = $this->modifier->modifyMeta($meta);

        $this->assertSame($meta, $result);
    }

    // ---- helpers ----

    private function setupLocator(?int $productId, int $storeId): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($productId);

        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn($storeId);

        $this->locator->method('getProduct')->willReturn($product);
        $this->locator->method('getStore')->willReturn($store);
    }

    private function setupProductAttributeValue(string $attributeCode, ?string $value): void
    {
        $product = $this->locator->getProduct();
        if ($product instanceof MockObject) {
            $product->method('getData')
                ->willReturnCallback(fn(string $key) => $key === $attributeCode ? $value : null);
        }
    }

    private function createEnrichmentMock(
        int $entityId,
        string $attributeCode,
        string $status,
        ?string $generatedValue,
        ?string $appliedValue
    ): EnrichmentInterface&MockObject {
        $enrichment = $this->createMock(EnrichmentInterface::class);
        $enrichment->method('getEntityId')->willReturn($entityId);
        $enrichment->method('getAttributeCode')->willReturn($attributeCode);
        $enrichment->method('getStatus')->willReturn($status);
        $enrichment->method('getGeneratedValue')->willReturn($generatedValue);
        $enrichment->method('getAppliedValue')->willReturn($appliedValue);
        $enrichment->method('getUpdatedAt')->willReturn('2026-02-15 12:00:00');
        return $enrichment;
    }

    private function setupSearchResults(array $items): void
    {
        $searchResults = $this->createMock(EnrichmentSearchResultsInterface::class);
        $searchResults->method('getItems')->willReturn($items);
        $this->enrichmentRepository->method('getList')->willReturn($searchResults);
    }

    private function setupEmptySearchResults(): void
    {
        $this->setupSearchResults([]);
    }

    private function buildMetaWithField(string $attributeCode): array
    {
        return [
            'product-details' => [
                'children' => [
                    'container_' . $attributeCode => [
                        'children' => [
                            $attributeCode => [
                                'arguments' => [
                                    'data' => [
                                        'config' => [
                                            'formElement' => 'textarea',
                                            'visible' => true,
                                            'label' => ucfirst($attributeCode),
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    private function extractAdditionalInfo(array $meta, string $attributeCode): ?string
    {
        $path = $this->arrayManager->findPath(
            $attributeCode,
            $meta,
            null,
            'children'
        );

        if (!$path) {
            return null;
        }

        return $this->arrayManager->get(
            $path . '/arguments/data/config/additionalInfo',
            $meta
        );
    }
}
