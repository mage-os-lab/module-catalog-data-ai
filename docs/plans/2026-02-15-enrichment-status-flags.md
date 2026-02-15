# Enrichment Status Flags on Product Edit — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Show inline AI enrichment status notes below each enriched attribute field on the product edit form, with links to the enrichment review form.

**Architecture:** A product form `ModifierInterface` registered in the modifier pool via `etc/adminhtml/di.xml`. It queries `catalogai_product_enrichment` records for the current product, determines display status (pending/approved/modified/denied), and injects `additionalInfo` HTML into each enriched field's meta config. Magento's `form/field.html` template renders the HTML. A `_module.less` file provides status colors using admin theme LESS variables.

**Tech Stack:** PHP 8.2+, Magento UI DataProvider Modifiers, `ArrayManager`, LESS

**Design doc:** `docs/plans/2026-02-15-enrichment-status-flags-design.md`

---

## Task 1: Create DI Configuration for Product Form Modifier

**Files:**
- Create: `etc/adminhtml/di.xml`
- Reference: `vendor: Magento/Catalog/etc/adminhtml/di.xml` (modifier pool pattern at lines 103-167)

**Step 1: Create the adminhtml-scoped di.xml**

The module currently only has a global `etc/di.xml`. We need an adminhtml-scoped one to register our modifier in the product form modifier pool.

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">
    <virtualType name="Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\Pool">
        <arguments>
            <argument name="modifiers" xsi:type="array">
                <item name="catalogai_enrichment_status" xsi:type="array">
                    <item name="class" xsi:type="string">MageOS\CatalogDataAI\Ui\DataProvider\Product\Form\Modifier\EnrichmentStatus</item>
                    <item name="sortOrder" xsi:type="number">200</item>
                </item>
            </argument>
        </arguments>
    </virtualType>
</config>
```

**Step 2: Verify no compile errors**

Run: `bin/magento setup:di:compile --dry-run 2>&1 | head -20` (or just verify XML is well-formed)

**Step 3: Commit**

```bash
git add etc/adminhtml/di.xml
git commit -m "feat(#48): register EnrichmentStatus modifier in product form pool"
```

---

## Task 2: Write Failing Tests for EnrichmentStatus Modifier

**Files:**
- Create: `Test/Unit/Ui/DataProvider/Product/Form/Modifier/EnrichmentStatusTest.php`

**Context:**
- The modifier class does not exist yet — tests will fail with class-not-found
- Test conventions follow `MageOS\AdminActivityLog\Test\Unit` patterns: `declare(strict_types=1)`, PHPUnit `TestCase`, `setUp()` with mocks
- The modifier needs: `LocatorInterface` (product + store), `EnrichmentRepositoryInterface` (query records), `SearchCriteriaBuilder`, `Config` (cache check), `UrlInterface` (admin URLs), `ArrayManager` (meta manipulation)

**Step 1: Write the test class**

```php
<?php

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Ui\DataProvider\Product\Form\Modifier;

use MageOS\CatalogDataAI\Api\Data\EnrichmentInterface;
use MageOS\CatalogDataAI\Api\Data\EnrichmentSearchResultsInterface;
use MageOS\CatalogDataAI\Api\EnrichmentRepositoryInterface;
use MageOS\CatalogDataAI\Model\Config;
use MageOS\CatalogDataAI\Ui\DataProvider\Product\Form\Modifier\EnrichmentStatus;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Locator\LocatorInterface;
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

        // Default: searchCriteriaBuilder returns self on addFilter, and returns a SearchCriteria
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
        $this->setupLocator(null, 1); // null product ID = new product

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
        // Value matches applied_value, so status should be "Approved" not "Modified"
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

        // Meta should be unchanged since field is not in the form
        $this->assertSame($meta, $result);
    }

    // ---- helpers ----

    private function setupLocator(?int $productId, int $storeId): void
    {
        $product = $this->createMock(ProductInterface::class);
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

    /**
     * Build a minimal product form meta tree with a single field.
     *
     * Mirrors the structure the EAV modifier produces:
     * fieldset > container_<code> > <code> > arguments/data/config
     */
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
```

**Step 2: Run the test to confirm it fails**

Run: `php vendor/bin/phpunit app/code/MageOS/CatalogDataAI/Test/Unit/Ui/DataProvider/Product/Form/Modifier/EnrichmentStatusTest.php --no-coverage 2>&1 | tail -5`

Expected: FAIL — `Class 'MageOS\CatalogDataAI\Ui\DataProvider\Product\Form\Modifier\EnrichmentStatus' not found`

**Step 3: Commit**

```bash
git add Test/Unit/Ui/DataProvider/Product/Form/Modifier/EnrichmentStatusTest.php
git commit -m "test(#48): add unit tests for EnrichmentStatus product form modifier"
```

---

## Task 3: Implement EnrichmentStatus Modifier

**Files:**
- Create: `Ui/DataProvider/Product/Form/Modifier/EnrichmentStatus.php`
- Reference: `Magento/Catalog/Ui/DataProvider/Product/Form/Modifier/AbstractModifier.php` (base class)
- Reference: `Magento/Catalog/Ui/DataProvider/Product/Form/Modifier/TierPrice.php` (ArrayManager.findPath + merge pattern)

**Context:**
- Extends `AbstractModifier` which implements `ModifierInterface` (requires `modifyData` and `modifyMeta`)
- Uses `LocatorInterface` (not `RequestInterface`) to get the current product and store — this is the standard pattern all Catalog form modifiers use
- Uses `ArrayManager::findPath()` to locate a field by name in the meta tree, then `ArrayManager::merge()` to inject `additionalInfo`
- Queries enrichments via `EnrichmentRepositoryInterface::getList()` with `SearchCriteriaBuilder` filters for `product_id` and `store_id`
- Determines display status by comparing product attribute value against enrichment's applied/generated value

**Step 1: Write the modifier class**

```php
<?php

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Ui\DataProvider\Product\Form\Modifier;

use MageOS\CatalogDataAI\Api\Data\EnrichmentInterface;
use MageOS\CatalogDataAI\Api\EnrichmentRepositoryInterface;
use MageOS\CatalogDataAI\Model\Config;
use Magento\Catalog\Model\Locator\LocatorInterface;
use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AbstractModifier;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Stdlib\ArrayManager;
use Magento\Backend\Model\UrlInterface;

class EnrichmentStatus extends AbstractModifier
{
    private const STATUS_LABELS = [
        'pending'  => 'AI-generated &middot; Pending review',
        'approved' => 'AI-generated &middot; Approved',
        'applied'  => 'AI-generated &middot; Approved',
        'modified' => 'AI-generated &middot; Modified',
        'denied'   => 'AI-generated &middot; Denied',
    ];

    private const STATUS_CSS_MAP = [
        'pending'  => 'pending',
        'approved' => 'approved',
        'applied'  => 'approved',
        'modified' => 'modified',
        'denied'   => 'denied',
    ];

    public function __construct(
        private readonly LocatorInterface $locator,
        private readonly EnrichmentRepositoryInterface $enrichmentRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly Config $config,
        private readonly UrlInterface $urlBuilder,
        private readonly ArrayManager $arrayManager
    ) {
    }

    public function modifyData(array $data): array
    {
        return $data;
    }

    public function modifyMeta(array $meta): array
    {
        if (!$this->config->isCacheEnabled()) {
            return $meta;
        }

        $product = $this->locator->getProduct();
        $productId = $product->getId();

        if (!$productId) {
            return $meta;
        }

        $storeId = (int) $this->locator->getStore()->getId();
        $enrichments = $this->getEnrichmentsByProduct((int) $productId, $storeId);

        if (empty($enrichments)) {
            return $meta;
        }

        foreach ($enrichments as $attributeCode => $enrichment) {
            $meta = $this->injectEnrichmentNote($meta, $attributeCode, $enrichment, $product);
        }

        return $meta;
    }

    /**
     * Query enrichment records for a product, grouped by attribute_code (most recent wins).
     *
     * @return array<string, EnrichmentInterface>
     */
    private function getEnrichmentsByProduct(int $productId, int $storeId): array
    {
        $this->searchCriteriaBuilder->addFilter('product_id', $productId);
        $this->searchCriteriaBuilder->addFilter('store_id', $storeId);
        $searchCriteria = $this->searchCriteriaBuilder->create();

        $results = $this->enrichmentRepository->getList($searchCriteria);
        $enrichments = [];

        foreach ($results->getItems() as $enrichment) {
            $code = $enrichment->getAttributeCode();
            // Keep the most recent record per attribute (last write wins)
            if (!isset($enrichments[$code])
                || $enrichment->getUpdatedAt() > $enrichments[$code]->getUpdatedAt()
            ) {
                $enrichments[$code] = $enrichment;
            }
        }

        return $enrichments;
    }

    private function injectEnrichmentNote(
        array $meta,
        string $attributeCode,
        EnrichmentInterface $enrichment,
        $product
    ): array {
        $fieldPath = $this->arrayManager->findPath($attributeCode, $meta, null, 'children');

        if (!$fieldPath) {
            return $meta;
        }

        $displayStatus = $this->resolveDisplayStatus($enrichment, $product);
        $html = $this->buildNoteHtml($enrichment, $displayStatus);
        $configPath = $fieldPath . '/arguments/data/config';

        return $this->arrayManager->merge($configPath, $meta, ['additionalInfo' => $html]);
    }

    private function resolveDisplayStatus(EnrichmentInterface $enrichment, $product): string
    {
        $status = $enrichment->getStatus();

        if ($status === EnrichmentInterface::STATUS_PENDING || $status === EnrichmentInterface::STATUS_DENIED) {
            return $status;
        }

        // For approved/applied: check if product value still matches
        $currentValue = (string) $product->getData($enrichment->getAttributeCode());
        $enrichmentValue = $enrichment->getAppliedValue() ?? $enrichment->getGeneratedValue();

        if ($currentValue !== (string) $enrichmentValue) {
            return 'modified';
        }

        return $status;
    }

    private function buildNoteHtml(EnrichmentInterface $enrichment, string $displayStatus): string
    {
        $label = self::STATUS_LABELS[$displayStatus] ?? self::STATUS_LABELS['pending'];
        $cssClass = self::STATUS_CSS_MAP[$displayStatus] ?? 'pending';
        $editUrl = $this->urlBuilder->getUrl(
            'catalogai/enrichment/edit',
            ['id' => $enrichment->getEntityId()]
        );

        return sprintf(
            '<span class="catalogai-enrichment-note catalogai-enrichment-note--%s">'
            . '%s &mdash; <a href="%s" target="_blank">%s</a>'
            . '</span>',
            $cssClass,
            $label,
            htmlspecialchars($editUrl, ENT_QUOTES),
            'View details'
        );
    }
}
```

**Step 2: Run the tests**

Run: `php vendor/bin/phpunit app/code/MageOS/CatalogDataAI/Test/Unit/Ui/DataProvider/Product/Form/Modifier/EnrichmentStatusTest.php --no-coverage`

Expected: All tests PASS

**Step 3: Commit**

```bash
git add Ui/DataProvider/Product/Form/Modifier/EnrichmentStatus.php
git commit -m "feat(#48): implement EnrichmentStatus product form modifier"
```

---

## Task 4: Add LESS Styles

**Files:**
- Create: `view/adminhtml/web/css/source/_module.less`

**Context:**
- Magento automatically includes `_module.less` from any module's `view/adminhtml/web/css/source/` directory
- Uses admin theme LESS variables for color consistency:
  - `@grid-severity-minor-color` (orange) — defined in `app/design/adminhtml/Magento/backend/web/mui/styles/_vars.less:476`
  - `@grid-severity-notice-color` (green) — defined at `:472`
  - `@theme__color__primary` (blue) — defined in `lib/web/css/source/lib/variables/_colors.less:105`
  - `@color-gray60` (gray) — defined at `:27`

**Step 1: Create the LESS file**

```less
//
//  CatalogDataAI — Enrichment status notes on product edit form
//

.catalogai-enrichment-note {
    font-size: 1.2rem;
    margin-top: 4px;

    a {
        margin-left: 4px;
    }

    &--pending {
        color: @grid-severity-minor-color;
    }

    &--approved {
        color: @grid-severity-notice-color;
    }

    &--modified {
        color: @theme__color__primary;
    }

    &--denied {
        color: @color-gray60;
    }
}
```

**Step 2: Verify LESS compiles**

Run: `bin/magento setup:static-content:deploy --area=adminhtml --theme=Magento/backend --language=en_US -f 2>&1 | tail -5`

(Or, if using developer mode, just clear static files: `rm -rf pub/static/adminhtml/Magento/backend/en_US/MageOS_CatalogDataAI/` and let it regenerate on next page load.)

**Step 3: Commit**

```bash
git add view/adminhtml/web/css/source/_module.less
git commit -m "style(#48): add LESS styles for enrichment status notes using admin theme variables"
```

---

## Task 5: Manual Verification and Edge Case Review

**Step 1: Verify the modifier works end-to-end**

1. Enable enrichment cache: **Stores > Configuration > Catalog > AI Data Enrichment > Enrichment Cache = Yes**
2. Open a product that has been previously enriched (has records in `catalogai_product_enrichment`)
3. Verify each enriched field shows the status note below the input
4. Click "View details" — should open enrichment edit form in new tab

**Step 2: Verify guard conditions**

1. Disable enrichment cache — reload product edit — no notes should appear
2. Create a brand-new product (Admin > Catalog > Products > Add Product) — no notes
3. Re-enable cache, open product with no enrichment records — no notes

**Step 3: Verify status logic**

1. Find a product with a "pending" enrichment — verify orange "Pending review" note
2. Approve an enrichment via the review grid — reload product edit — verify green "Approved" note
3. Edit the attribute value directly on the product, save — verify blue "Modified" note
4. Deny an enrichment — verify gray "Denied" note

**Step 4: Verify CSS**

1. Inspect the note elements in browser DevTools
2. Confirm colors match the admin theme variables (no hardcoded hex values)
3. Confirm "View details" link has proper spacing and opens in new tab

**Step 5: Final commit (if any fixes needed)**

```bash
git add -A
git commit -m "fix(#48): address edge cases found during manual verification"
```

---

## Summary

| Task | Files | Type |
|------|-------|------|
| 1. DI config | `etc/adminhtml/di.xml` (create) | Config |
| 2. Unit tests | `Test/Unit/.../EnrichmentStatusTest.php` (create) | Test |
| 3. Modifier | `Ui/DataProvider/.../EnrichmentStatus.php` (create) | PHP |
| 4. LESS styles | `view/adminhtml/web/css/source/_module.less` (create) | CSS |
| 5. Manual verification | — | QA |

**Total new files:** 4 (1 PHP class, 1 test, 1 DI XML, 1 LESS)
**Modified files:** 0
