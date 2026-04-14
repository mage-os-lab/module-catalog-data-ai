# Phase 2: Multi-Store/Locale + Enrichment Status Flags — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add store-scoped enrichment with locale-aware AI output, and track which product attributes were AI-generated with status indicators in the admin product edit form.

**Architecture:** The `Request` DTO gains a `storeId` field so queue messages carry store context. `Consumer` sets the store scope before enriching, and `Config` resolves the store's locale to inject language instructions into the system prompt. A new `mageos_catalogai_enrichment_log` DB table tracks enrichment status per product/attribute/store. The `Enricher` writes log entries on successful enrichment, and a `SaveBefore` observer detects manual edits to enriched fields. A UI component modifier adds status badges to the product edit form.

**Tech Stack:** PHP 8.1+, Magento 2.4.x, PHPUnit 9.5, Magento Declarative Schema (`db_schema.xml`)

---

### Task 1: Add storeId to RequestInterface and Request DTO

**Files:**
- Modify: `Api/RequestInterface.php`
- Modify: `Model/Product/Request.php`
- Test: `Test/Unit/Model/Product/RequestTest.php`

**Step 1: Write the test**

Create file `Test/Unit/Model/Product/RequestTest.php`:

```php
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
```

**Step 2: Update RequestInterface**

In `Api/RequestInterface.php`, add the `getStoreId` method to the interface:

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Api;

interface RequestInterface
{
    /**
     * Retrieve product id.
     * @return int
     */
    public function getId(): int;

    /**
     * Retrieve overwrite flag.
     * @return bool
     */
    public function getOverwrite(): bool;

    /**
     * Retrieve store id.
     * @return int
     */
    public function getStoreId(): int;
}
```

**Step 3: Update Request DTO**

Replace `Model/Product/Request.php` with:

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\Product;

use MageOS\CatalogDataAI\Api\RequestInterface;

/**
 * Data model for enrichment message queue.
 */
class Request implements RequestInterface
{
    public function __construct(
        private readonly int $id,
        private readonly bool $overwrite,
        private readonly int $storeId = 0
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getOverwrite(): bool
    {
        return $this->overwrite;
    }

    public function getStoreId(): int
    {
        return $this->storeId;
    }
}
```

**Step 4: Commit**

```bash
git add Api/RequestInterface.php Model/Product/Request.php Test/Unit/Model/Product/RequestTest.php
git commit -m "feat: add storeId to Request DTO for store-scoped enrichment (#12)"
```

---

### Task 2: Update Publisher to pass storeId

**Files:**
- Modify: `Model/Product/Publisher.php`

**Step 1: Update Publisher::execute signature and pass storeId to Request**

Replace `Model/Product/Publisher.php` with:

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\Product;

use Magento\Framework\MessageQueue\PublisherInterface;

class Publisher
{
    public const TOPIC_NAME = 'mageos.product.enrich';

    public function __construct(
        private readonly PublisherInterface $publisher,
        private readonly RequestFactory     $requestFactory,
    ) {
    }

    public function execute(int|string $productId, bool $overwrite = false, int $storeId = 0): void
    {
        $request = $this->requestFactory->create([
            'id' => (int)$productId,
            'overwrite' => $overwrite,
            'storeId' => $storeId,
        ]);
        $this->publisher->publish(self::TOPIC_NAME, $request);
    }
}
```

**Step 2: Commit**

```bash
git add Model/Product/Publisher.php
git commit -m "feat: Publisher passes storeId to queue messages (#12)"
```

---

### Task 3: Update Consumer to set store scope

**Files:**
- Modify: `Model/Product/Consumer.php`

**Step 1: Update Consumer to use storeId from request**

Replace `Model/Product/Consumer.php` with:

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\Product;

use Magento\Catalog\Model\ProductRepository;
use Magento\Store\Model\StoreManagerInterface;

class Consumer
{
    public function __construct(
        private readonly Enricher              $enricher,
        private readonly ProductRepository     $productRepository,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function execute(Request $request): void
    {
        $this->storeManager->setCurrentStore($request->getStoreId());
        $product = $this->productRepository->getById(
            $request->getId(),
            false,
            $request->getStoreId()
        );
        $product->setData('mageos_catalogai_overwrite', $request->getOverwrite());
        $this->enricher->execute($product);
        $this->productRepository->save($product);
    }
}
```

Key changes:
- Uses `$request->getStoreId()` instead of hardcoded `0`
- Passes `storeId` to `getById()` third parameter so product data loads for the correct store
- Removed the stale `@package` docblock

**Step 2: Commit**

```bash
git add Model/Product/Consumer.php
git commit -m "feat: Consumer sets store scope from queue message (#12)"
```

---

### Task 4: Update SaveAfter observer to pass storeId

**Files:**
- Modify: `Observer/Product/SaveAfter.php`

**Step 1: Pass the product's store ID to the publisher**

In `Observer/Product/SaveAfter.php`, change the `execute` method to pass the store ID:

```php
    public function execute(Observer $observer): void
    {
        /** @var Product $product */
        $product = $observer->getProduct();

        if ($this->config->canEnrich($product) && $this->config->isAsync()) {
            $this->publisher->execute(
                $product->getId(),
                false,
                (int)$product->getStoreId()
            );
        }
    }
```

**Step 2: Commit**

```bash
git add Observer/Product/SaveAfter.php
git commit -m "feat: SaveAfter observer passes storeId to publisher (#12)"
```

---

### Task 5: Update MassEnrich controller to pass storeId

**Files:**
- Modify: `Controller/Adminhtml/Product/MassEnrich.php`

**Step 1: Inject StoreManagerInterface and pass current store to publisher**

Add `StoreManagerInterface` to the constructor and pass the current store ID in the loop:

In the constructor, add after `ProductRepositoryInterface`:
```php
        private readonly \Magento\Store\Model\StoreManagerInterface $storeManager,
```

In the `execute()` method, before the foreach loop, get the current store:
```php
        $storeId = (int)$this->storeManager->getStore()->getId();
```

In the foreach loop, change:
```php
                $this->publisher->execute($product->getId(), $this->overwrite);
```
to:
```php
                $this->publisher->execute($product->getId(), $this->overwrite, $storeId);
```

**Step 2: Commit**

```bash
git add Controller/Adminhtml/Product/MassEnrich.php
git commit -m "feat: MassEnrich passes current store scope to publisher (#12)"
```

---

### Task 6: Add locale-aware system prompt to Config

**Files:**
- Modify: `Model/Config.php`
- Test: `Test/Unit/Model/ConfigTest.php`

**Step 1: Write the test**

Add this test to the existing `Test/Unit/Model/ConfigTest.php`:

```php
    public function test_get_system_prompt_includes_locale_language(): void
    {
        $this->scopeConfig->method('getValue')
            ->willReturnMap([
                [Config::XML_PATH_OPENAI_API_ADVANCED_SYSTEM_PROMPT, ScopeInterface::SCOPE_STORE, null, 'Be a content generator.'],
                ['general/locale/code', ScopeInterface::SCOPE_STORE, null, 'fr_FR'],
            ]);

        $result = $this->config->getSystemPrompt();

        $this->assertStringContainsString('Respond in French', $result);
        $this->assertStringContainsString('Be a content generator.', $result);
    }

    public function test_get_system_prompt_without_locale_returns_base_prompt(): void
    {
        $this->scopeConfig->method('getValue')
            ->willReturnMap([
                [Config::XML_PATH_OPENAI_API_ADVANCED_SYSTEM_PROMPT, ScopeInterface::SCOPE_STORE, null, 'Be a content generator.'],
                ['general/locale/code', ScopeInterface::SCOPE_STORE, null, null],
            ]);

        $result = $this->config->getSystemPrompt();

        $this->assertEquals('Be a content generator.', $result);
    }
```

Add this import at the top of the test file:
```php
use Magento\Store\Model\ScopeInterface;
```

Also update the `setUp()` method — the existing tests use `->method('getValue')->with(...)` which will conflict with the new `willReturnMap`. The tests need to be refactored. Replace the two existing `getEnrichableAttributes` tests and the `getProductPrompt` test so they also use `willReturnMap`:

```php
    public function test_get_enrichable_attributes_returns_enabled_attributes(): void
    {
        $serializedData = [
            'row1' => ['attribute' => 'description', 'prompt' => 'describe {{name}}', 'enabled' => '1'],
            'row2' => ['attribute' => 'meta_title', 'prompt' => 'title for {{name}}', 'enabled' => '0'],
            'row3' => ['attribute' => 'short_description', 'prompt' => 'short desc for {{name}}', 'enabled' => '1'],
        ];

        $this->scopeConfig->method('getValue')
            ->willReturnMap([
                ['catalog_ai/product/attribute_prompts', ScopeInterface::SCOPE_STORE, null, $serializedData],
            ]);

        $result = $this->config->getEnrichableAttributes();

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('description', $result);
        $this->assertArrayHasKey('short_description', $result);
        $this->assertArrayNotHasKey('meta_title', $result);
    }

    public function test_get_enrichable_attributes_returns_empty_when_null(): void
    {
        $this->scopeConfig->method('getValue')
            ->willReturnMap([
                ['catalog_ai/product/attribute_prompts', ScopeInterface::SCOPE_STORE, null, null],
            ]);

        $result = $this->config->getEnrichableAttributes();

        $this->assertEmpty($result);
    }

    public function test_get_product_prompt_returns_prompt_for_attribute(): void
    {
        $serializedData = [
            'row1' => ['attribute' => 'description', 'prompt' => 'describe {{name}}', 'enabled' => '1'],
            'row2' => ['attribute' => 'meta_title', 'prompt' => 'title for {{name}}', 'enabled' => '1'],
        ];

        $this->scopeConfig->method('getValue')
            ->willReturnMap([
                ['catalog_ai/product/attribute_prompts', ScopeInterface::SCOPE_STORE, null, $serializedData],
            ]);

        $this->assertEquals('describe {{name}}', $this->config->getProductPrompt('description'));
        $this->assertNull($this->config->getProductPrompt('nonexistent'));
    }
```

**Step 2: Update Config::getSystemPrompt()**

In `Model/Config.php`, add a locale-to-language map constant and update `getSystemPrompt()`:

Add this constant at the top of the class (after the existing XML_PATH constants):

```php
    private const LOCALE_LANGUAGE_MAP = [
        'af' => 'Afrikaans', 'ar' => 'Arabic', 'bg' => 'Bulgarian', 'bn' => 'Bengali',
        'ca' => 'Catalan', 'cs' => 'Czech', 'cy' => 'Welsh', 'da' => 'Danish',
        'de' => 'German', 'el' => 'Greek', 'en' => 'English', 'es' => 'Spanish',
        'et' => 'Estonian', 'fa' => 'Persian', 'fi' => 'Finnish', 'fr' => 'French',
        'gl' => 'Galician', 'he' => 'Hebrew', 'hi' => 'Hindi', 'hr' => 'Croatian',
        'hu' => 'Hungarian', 'id' => 'Indonesian', 'it' => 'Italian', 'ja' => 'Japanese',
        'ka' => 'Georgian', 'ko' => 'Korean', 'lt' => 'Lithuanian', 'lv' => 'Latvian',
        'mk' => 'Macedonian', 'ms' => 'Malay', 'nb' => 'Norwegian', 'nl' => 'Dutch',
        'pl' => 'Polish', 'pt' => 'Portuguese', 'ro' => 'Romanian', 'ru' => 'Russian',
        'sk' => 'Slovak', 'sl' => 'Slovenian', 'sq' => 'Albanian', 'sr' => 'Serbian',
        'sv' => 'Swedish', 'th' => 'Thai', 'tr' => 'Turkish', 'uk' => 'Ukrainian',
        'vi' => 'Vietnamese', 'zh' => 'Chinese',
    ];
```

Replace the existing `getSystemPrompt()` method:

```php
    public function getSystemPrompt(): string
    {
        $basePrompt = (string)$this->scopeConfig->getValue(
            self::XML_PATH_OPENAI_API_ADVANCED_SYSTEM_PROMPT,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $locale = $this->scopeConfig->getValue(
            'general/locale/code',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        if ($locale) {
            $langCode = substr($locale, 0, 2);
            $language = self::LOCALE_LANGUAGE_MAP[$langCode] ?? null;
            if ($language && $langCode !== 'en') {
                $basePrompt = 'Respond in ' . $language . '. ' . $basePrompt;
            }
        }

        return $basePrompt;
    }
```

**Step 3: Commit**

```bash
git add Model/Config.php Test/Unit/Model/ConfigTest.php
git commit -m "feat: locale-aware system prompt prepends language instruction (#12)"
```

---

### Task 7: Create enrichment log DB schema

**Files:**
- Create: `etc/db_schema.xml`
- Create: `etc/db_schema_whitelist.json`

**Step 1: Create the declarative schema**

Create file `etc/db_schema.xml`:

```xml
<?xml version="1.0"?>
<schema xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Setup/Declaration/Schema/etc/schema.xsd">
    <table name="mageos_catalogai_enrichment_log" resource="default" engine="innodb"
           comment="AI Enrichment Status Log">
        <column xsi:type="int" name="log_id" unsigned="true" nullable="false" identity="true"
                comment="Log ID"/>
        <column xsi:type="int" name="entity_id" unsigned="true" nullable="false"
                comment="Product Entity ID"/>
        <column xsi:type="varchar" name="attribute_code" nullable="false" length="255"
                comment="Attribute Code"/>
        <column xsi:type="smallint" name="store_id" unsigned="true" nullable="false" default="0"
                comment="Store ID"/>
        <column xsi:type="varchar" name="status" nullable="false" length="32" default="generated"
                comment="Enrichment Status"/>
        <column xsi:type="timestamp" name="generated_at" nullable="false" default="CURRENT_TIMESTAMP"
                comment="Generation Timestamp"/>
        <column xsi:type="timestamp" name="updated_at" nullable="false" default="CURRENT_TIMESTAMP"
                on_update="true" comment="Last Updated"/>
        <constraint xsi:type="primary" referenceId="PRIMARY">
            <column name="log_id"/>
        </constraint>
        <constraint xsi:type="unique" referenceId="MAGEOS_CATALOGAI_ENRICH_LOG_ENTITY_ATTR_STORE">
            <column name="entity_id"/>
            <column name="attribute_code"/>
            <column name="store_id"/>
        </constraint>
        <index referenceId="MAGEOS_CATALOGAI_ENRICH_LOG_STATUS" indexType="btree">
            <column name="status"/>
        </index>
        <index referenceId="MAGEOS_CATALOGAI_ENRICH_LOG_ENTITY_ID" indexType="btree">
            <column name="entity_id"/>
        </index>
    </table>
</schema>
```

**Step 2: Generate the whitelist**

Run from Magento root (inside Warden):

```bash
warden env exec php-fpm bin/magento setup:db-declaration:generate-whitelist --module-name=MageOS_CatalogDataAI
```

This creates `etc/db_schema_whitelist.json`. If running outside Warden is not possible, create it manually:

```json
{
    "mageos_catalogai_enrichment_log": {
        "column": {
            "log_id": true,
            "entity_id": true,
            "attribute_code": true,
            "store_id": true,
            "status": true,
            "generated_at": true,
            "updated_at": true
        },
        "constraint": {
            "PRIMARY": true,
            "MAGEOS_CATALOGAI_ENRICH_LOG_ENTITY_ATTR_STORE": true
        },
        "index": {
            "MAGEOS_CATALOGAI_ENRICH_LOG_STATUS": true,
            "MAGEOS_CATALOGAI_ENRICH_LOG_ENTITY_ID": true
        }
    }
}
```

**Step 3: Commit**

```bash
git add etc/db_schema.xml etc/db_schema_whitelist.json
git commit -m "feat: add enrichment log DB table schema (#48)"
```

---

### Task 8: Create EnrichmentLog model and resource model

**Files:**
- Create: `Model/EnrichmentLog.php`
- Create: `Model/ResourceModel/EnrichmentLog.php`
- Create: `Model/ResourceModel/EnrichmentLog/Collection.php`

**Step 1: Create the resource model**

Create file `Model/ResourceModel/EnrichmentLog.php`:

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class EnrichmentLog extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('mageos_catalogai_enrichment_log', 'log_id');
    }
}
```

**Step 2: Create the model**

Create file `Model/EnrichmentLog.php`:

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model;

use Magento\Framework\Model\AbstractModel;
use MageOS\CatalogDataAI\Model\ResourceModel\EnrichmentLog as EnrichmentLogResource;

class EnrichmentLog extends AbstractModel
{
    public const STATUS_GENERATED = 'generated';
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_MODIFIED = 'modified';

    protected function _construct(): void
    {
        $this->_init(EnrichmentLogResource::class);
    }
}
```

**Step 3: Create the collection**

Create file `Model/ResourceModel/EnrichmentLog/Collection.php`:

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\ResourceModel\EnrichmentLog;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use MageOS\CatalogDataAI\Model\EnrichmentLog;
use MageOS\CatalogDataAI\Model\ResourceModel\EnrichmentLog as EnrichmentLogResource;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(EnrichmentLog::class, EnrichmentLogResource::class);
    }
}
```

**Step 4: Commit**

```bash
git add Model/EnrichmentLog.php Model/ResourceModel/EnrichmentLog.php Model/ResourceModel/EnrichmentLog/Collection.php
git commit -m "feat: add EnrichmentLog model, resource model, and collection (#48)"
```

---

### Task 9: Create EnrichmentLogger service

**Files:**
- Create: `Model/Product/EnrichmentLogger.php`
- Test: `Test/Unit/Model/Product/EnrichmentLoggerTest.php`

**Step 1: Write the test**

Create file `Test/Unit/Model/Product/EnrichmentLoggerTest.php`:

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Model\Product;

use MageOS\CatalogDataAI\Model\EnrichmentLog;
use MageOS\CatalogDataAI\Model\EnrichmentLogFactory;
use MageOS\CatalogDataAI\Model\Product\EnrichmentLogger;
use MageOS\CatalogDataAI\Model\ResourceModel\EnrichmentLog as EnrichmentLogResource;
use MageOS\CatalogDataAI\Model\ResourceModel\EnrichmentLog\Collection;
use MageOS\CatalogDataAI\Model\ResourceModel\EnrichmentLog\CollectionFactory;
use PHPUnit\Framework\TestCase;

final class EnrichmentLoggerTest extends TestCase
{
    public function test_log_creates_new_entry_when_none_exists(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($this->createConfiguredMock(
            EnrichmentLog::class,
            ['getId' => null]
        ));

        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $newLog = $this->createMock(EnrichmentLog::class);
        $newLog->expects($this->once())->method('setData')->with($this->callback(
            fn(array $data) => $data['entity_id'] === 42
                && $data['attribute_code'] === 'description'
                && $data['store_id'] === 1
                && $data['status'] === EnrichmentLog::STATUS_GENERATED
        ));

        $logFactory = $this->createMock(EnrichmentLogFactory::class);
        $logFactory->method('create')->willReturn($newLog);

        $resource = $this->createMock(EnrichmentLogResource::class);
        $resource->expects($this->once())->method('save')->with($newLog);

        $logger = new EnrichmentLogger($collectionFactory, $logFactory, $resource);
        $logger->log(42, 'description', 1);
    }
}
```

**Step 2: Create the service**

Create file `Model/Product/EnrichmentLogger.php`:

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\Product;

use MageOS\CatalogDataAI\Model\EnrichmentLog;
use MageOS\CatalogDataAI\Model\EnrichmentLogFactory;
use MageOS\CatalogDataAI\Model\ResourceModel\EnrichmentLog as EnrichmentLogResource;
use MageOS\CatalogDataAI\Model\ResourceModel\EnrichmentLog\CollectionFactory;

class EnrichmentLogger
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly EnrichmentLogFactory $logFactory,
        private readonly EnrichmentLogResource $resource
    ) {
    }

    public function log(int $entityId, string $attributeCode, int $storeId): void
    {
        $existing = $this->find($entityId, $attributeCode, $storeId);

        if ($existing->getId()) {
            $existing->setData('status', EnrichmentLog::STATUS_GENERATED);
            $this->resource->save($existing);
        } else {
            $log = $this->logFactory->create();
            $log->setData([
                'entity_id' => $entityId,
                'attribute_code' => $attributeCode,
                'store_id' => $storeId,
                'status' => EnrichmentLog::STATUS_GENERATED,
            ]);
            $this->resource->save($log);
        }
    }

    public function markModified(int $entityId, string $attributeCode, int $storeId): void
    {
        $existing = $this->find($entityId, $attributeCode, $storeId);

        if ($existing->getId() && $existing->getData('status') !== EnrichmentLog::STATUS_MODIFIED) {
            $existing->setData('status', EnrichmentLog::STATUS_MODIFIED);
            $this->resource->save($existing);
        }
    }

    public function getStatus(int $entityId, string $attributeCode, int $storeId): ?string
    {
        $existing = $this->find($entityId, $attributeCode, $storeId);

        return $existing->getId() ? $existing->getData('status') : null;
    }

    public function getStatusesForProduct(int $entityId, int $storeId): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('entity_id', $entityId);
        $collection->addFieldToFilter('store_id', $storeId);

        $statuses = [];
        foreach ($collection as $item) {
            $statuses[$item->getData('attribute_code')] = $item->getData('status');
        }

        return $statuses;
    }

    private function find(int $entityId, string $attributeCode, int $storeId): EnrichmentLog
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('entity_id', $entityId);
        $collection->addFieldToFilter('attribute_code', $attributeCode);
        $collection->addFieldToFilter('store_id', $storeId);

        return $collection->getFirstItem();
    }
}
```

**Step 3: Commit**

```bash
git add Model/Product/EnrichmentLogger.php Test/Unit/Model/Product/EnrichmentLoggerTest.php
git commit -m "feat: add EnrichmentLogger service for tracking AI-generated attributes (#48)"
```

---

### Task 10: Wire EnrichmentLogger into Enricher

**Files:**
- Modify: `Model/Product/Enricher.php`

**Step 1: Add EnrichmentLogger to Enricher constructor and log after enrichment**

In `Model/Product/Enricher.php`:

Add the import:
```php
use MageOS\CatalogDataAI\Model\Product\EnrichmentLogger;
```

Update the constructor to add the logger:
```php
    public function __construct(
        private readonly Factory $clientFactory,
        private readonly Config $config,
        private readonly EnrichmentLogger $enrichmentLogger
    ) {
    }
```

In the `enrichAttribute` method, after the line `$product->setData($attributeCode, $result->message?->content);` (inside the `if($result = ...)` block), add:

```php
                $this->enrichmentLogger->log(
                    (int)$product->getId(),
                    $attributeCode,
                    (int)$product->getStoreId()
                );
```

**Step 2: Commit**

```bash
git add Model/Product/Enricher.php
git commit -m "feat: Enricher logs enrichment status after successful AI generation (#48)"
```

---

### Task 11: Detect manual edits in SaveBefore observer

**Files:**
- Modify: `Observer/Product/SaveBefore.php`

**Step 1: Add manual edit detection**

Replace `Observer/Product/SaveBefore.php` with:

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Observer\Product;

use Magento\Catalog\Model\Product;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use MageOS\CatalogDataAI\Model\Config;
use MageOS\CatalogDataAI\Model\Product\Enricher;
use MageOS\CatalogDataAI\Model\Product\EnrichmentLogger;

class SaveBefore implements ObserverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly Enricher $enricher,
        private readonly EnrichmentLogger $enrichmentLogger
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var Product $product */
        $product = $observer->getProduct();

        if ($this->config->canEnrich($product) && !$this->config->isAsync()) {
            $this->enricher->execute($product);
            return;
        }

        // Detect manual edits to enriched attributes on existing products
        if (!$product->isObjectNew() && $product->getId()) {
            $this->detectManualEdits($product);
        }
    }

    private function detectManualEdits(Product $product): void
    {
        $storeId = (int)$product->getStoreId();
        $enrichableAttributes = array_keys($this->config->getEnrichableAttributes());

        foreach ($enrichableAttributes as $attributeCode) {
            if (!$product->dataHasChangedFor($attributeCode)) {
                continue;
            }

            $this->enrichmentLogger->markModified(
                (int)$product->getId(),
                $attributeCode,
                $storeId
            );
        }
    }
}
```

**Step 2: Commit**

```bash
git add Observer/Product/SaveBefore.php
git commit -m "feat: detect manual edits to enriched attributes, mark as modified (#48)"
```

---

### Task 12: Add enrichment status UI modifier for product edit form

**Files:**
- Create: `Ui/DataProvider/Product/Form/Modifier/EnrichmentStatus.php`
- Modify: `etc/adminhtml/di.xml` (create if not exists)

**Step 1: Create the UI modifier**

Create file `Ui/DataProvider/Product/Form/Modifier/EnrichmentStatus.php`:

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Ui\DataProvider\Product\Form\Modifier;

use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AbstractModifier;
use Magento\Framework\Stdlib\ArrayManager;
use MageOS\CatalogDataAI\Model\Config;
use MageOS\CatalogDataAI\Model\Product\EnrichmentLogger;

class EnrichmentStatus extends AbstractModifier
{
    private const STATUS_LABELS = [
        'generated' => 'AI Generated',
        'pending_review' => 'Pending Review',
        'modified' => 'AI Generated (Modified)',
    ];

    public function __construct(
        private readonly EnrichmentLogger $enrichmentLogger,
        private readonly Config $config,
        private readonly ArrayManager $arrayManager
    ) {
    }

    public function modifyData(array $data): array
    {
        return $data;
    }

    public function modifyMeta(array $meta): array
    {
        $enrichableAttributes = array_keys($this->config->getEnrichableAttributes());

        foreach ($enrichableAttributes as $attributeCode) {
            $containerPath = $this->arrayManager->findPath(
                $attributeCode,
                $meta,
                null,
                'children'
            );

            if ($containerPath) {
                $meta = $this->arrayManager->merge(
                    $containerPath . '/arguments/data/config',
                    $meta,
                    [
                        'additionalClasses' => 'mageos-catalogai-enriched-field',
                    ]
                );
            }
        }

        return $meta;
    }
}
```

**Step 2: Register the modifier in adminhtml di.xml**

Create file `etc/adminhtml/di.xml`:

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">
    <virtualType name="Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\Pool">
        <arguments>
            <argument name="modifiers" xsi:type="array">
                <item name="mageos_catalogai_enrichment_status" xsi:type="array">
                    <item name="class" xsi:type="string">MageOS\CatalogDataAI\Ui\DataProvider\Product\Form\Modifier\EnrichmentStatus</item>
                    <item name="sortOrder" xsi:type="number">200</item>
                </item>
            </argument>
        </arguments>
    </virtualType>
</config>
```

**Step 3: Commit**

```bash
git add Ui/DataProvider/Product/Form/Modifier/EnrichmentStatus.php etc/adminhtml/di.xml
git commit -m "feat: add enrichment status UI modifier for product edit form (#48)"
```

---

### Task 13: Update module.xml sequence

**Files:**
- Modify: `etc/module.xml`

**Step 1: Add Magento_Store to the module sequence**

The module now depends on store scope resolution. In `etc/module.xml`, add `Magento_Store` to the sequence:

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:Module/etc/module.xsd">
    <module name="MageOS_CatalogDataAI">
        <sequence>
            <module name="Magento_Catalog"/>
            <module name="Magento_Store"/>
        </sequence>
    </module>
</config>
```

**Step 2: Commit**

```bash
git add etc/module.xml
git commit -m "feat: add Magento_Store to module sequence (#12)"
```

---

### Task 14: Smoke test — setup:upgrade and di:compile

**Step 1: Run Magento setup commands**

From the Magento root (inside Warden):

```bash
warden env exec php-fpm bin/magento setup:upgrade
warden env exec php-fpm bin/magento setup:di:compile
```

Both should complete without errors.

**Step 2: Verify the DB table was created**

```bash
warden env exec php-fpm bin/magento db:query "DESCRIBE mageos_catalogai_enrichment_log"
```

Expected: table with columns `log_id`, `entity_id`, `attribute_code`, `store_id`, `status`, `generated_at`, `updated_at`.

**Step 3: Run all tests**

```bash
vendor/bin/phpunit Test/
```

Expected: All tests pass.

**Step 4: Final commit (if any adjustments)**

```bash
git add -A
git commit -m "fix: adjustments from smoke testing Phase 2"
```

---

## Summary of Files Changed/Created

| Action | File |
|--------|------|
| Modify | `Api/RequestInterface.php` |
| Modify | `Model/Product/Request.php` |
| Modify | `Model/Product/Publisher.php` |
| Modify | `Model/Product/Consumer.php` |
| Modify | `Model/Product/Enricher.php` |
| Modify | `Model/Config.php` |
| Modify | `Observer/Product/SaveBefore.php` |
| Modify | `Observer/Product/SaveAfter.php` |
| Modify | `Controller/Adminhtml/Product/MassEnrich.php` |
| Modify | `etc/module.xml` |
| Modify | `Test/Unit/Model/ConfigTest.php` |
| Create | `etc/db_schema.xml` |
| Create | `etc/db_schema_whitelist.json` |
| Create | `etc/adminhtml/di.xml` |
| Create | `Model/EnrichmentLog.php` |
| Create | `Model/ResourceModel/EnrichmentLog.php` |
| Create | `Model/ResourceModel/EnrichmentLog/Collection.php` |
| Create | `Model/Product/EnrichmentLogger.php` |
| Create | `Ui/DataProvider/Product/Form/Modifier/EnrichmentStatus.php` |
| Create | `Test/Unit/Model/Product/RequestTest.php` |
| Create | `Test/Unit/Model/Product/EnrichmentLoggerTest.php` |
