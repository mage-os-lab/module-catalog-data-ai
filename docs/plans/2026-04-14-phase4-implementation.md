# Phase 4: Review/Approval Workflow — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add an admin review grid for AI-generated content with approve/reject/revert actions, a "require review" config toggle that holds content in pending state until approved, and prompt hash-based deduplication to avoid redundant API calls.

**Architecture:** The existing `mageos_catalogai_enrichment_log` table gains new columns: `generated_content`, `original_content`, and `prompt_hash`. The `EnrichmentLogger` is extended to store generated content and original values. When "require review" is enabled, the Enricher writes content to the log but does NOT set it on the product — an admin must approve it via a new review grid. The grid provides approve (writes to product), reject (reverts), and re-generate actions. Before calling OpenAI, the Enricher checks the prompt hash to reuse cached responses.

**Tech Stack:** PHP 8.1+, Magento 2.4.x, UI Components (admin grid), PHPUnit 9.5

---

### Task 1: Add new columns to enrichment_log table

**Files:**
- Modify: `etc/db_schema.xml`
- Modify: `etc/db_schema_whitelist.json`

**Step 1: Add columns to the enrichment_log table**

In `etc/db_schema.xml`, add these columns inside the `mageos_catalogai_enrichment_log` table, after the `status` column and before `generated_at`:

```xml
        <column xsi:type="text" name="generated_content" nullable="true"
                comment="AI Generated Content"/>
        <column xsi:type="text" name="original_content" nullable="true"
                comment="Original Content Before Enrichment"/>
        <column xsi:type="varchar" name="prompt_hash" nullable="true" length="64"
                comment="SHA-256 Hash of Resolved Prompt"/>
```

Add an index for prompt_hash after the existing indexes:

```xml
        <index referenceId="MAGEOS_CATALOGAI_ENRICH_LOG_PROMPT_HASH" indexType="btree">
            <column name="prompt_hash"/>
        </index>
```

**Step 2: Update the whitelist**

In `etc/db_schema_whitelist.json`, add to the `mageos_catalogai_enrichment_log.column` object:

```json
            "generated_content": true,
            "original_content": true,
            "prompt_hash": true
```

And add to the `index` object:

```json
            "MAGEOS_CATALOGAI_ENRICH_LOG_PROMPT_HASH": true
```

**Step 3: Commit**

```bash
git add etc/db_schema.xml etc/db_schema_whitelist.json
git commit -m "feat: add generated_content, original_content, prompt_hash columns to enrichment log (#28)"
```

---

### Task 2: Add review mode config toggle

**Files:**
- Modify: `etc/adminhtml/system.xml`
- Modify: `etc/config.xml`
- Modify: `Model/Config.php`

**Step 1: Add the config field to system.xml**

In `etc/adminhtml/system.xml`, inside the `settings` group, add after the `async` field (after line ~20):

```xml
                <field id="require_review" translate="label comment" type="select" sortOrder="22" showInDefault="1" canRestore="1">
                    <label>Require Review Before Publish</label>
                    <source_model>Magento\Config\Model\Config\Source\Yesno</source_model>
                    <comment><![CDATA[When enabled, AI-generated content is held for review and not written to products until approved.]]></comment>
                </field>
```

**Step 2: Add default value to config.xml**

In `etc/config.xml`, inside `<settings>`, add:

```xml
                <require_review>0</require_review>
```

**Step 3: Add getter to Config.php**

In `Model/Config.php`, add a constant and method. Add the constant after the existing ones:

```php
    public const XML_PATH_REQUIRE_REVIEW = 'ai_integration_enrichment/settings/require_review';
```

Add the method after `isAsync()`:

```php
    public function requiresReview(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_REQUIRE_REVIEW
        );
    }
```

**Step 4: Commit**

```bash
git add etc/adminhtml/system.xml etc/config.xml Model/Config.php
git commit -m "feat: add require_review config toggle (#28)"
```

---

### Task 3: Expand EnrichmentLog statuses

**Files:**
- Modify: `Model/EnrichmentLog.php`

**Step 1: Add the new status constants**

In `Model/EnrichmentLog.php`, add after the existing constants:

```php
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
```

**Step 2: Commit**

```bash
git add Model/EnrichmentLog.php
git commit -m "feat: add approved and rejected statuses to EnrichmentLog (#28)"
```

---

### Task 4: Update EnrichmentLogger for review workflow

**Files:**
- Modify: `Model/Product/EnrichmentLogger.php`
- Test: `Test/Unit/Model/Product/EnrichmentLoggerTest.php`

**Step 1: Update the `log` method signature and add new methods**

Replace the entire `Model/Product/EnrichmentLogger.php`:

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

    public function log(
        int $entityId,
        string $attributeCode,
        int $storeId,
        string $generatedContent = '',
        string $originalContent = '',
        string $promptHash = '',
        string $status = EnrichmentLog::STATUS_GENERATED
    ): void {
        $existing = $this->find($entityId, $attributeCode, $storeId);

        if ($existing->getId()) {
            $existing->setData('status', $status);
            $existing->setData('generated_content', $generatedContent);
            $existing->setData('original_content', $originalContent);
            $existing->setData('prompt_hash', $promptHash);
            $this->resource->save($existing);
        } else {
            $log = $this->logFactory->create();
            $log->setData([
                'entity_id' => $entityId,
                'attribute_code' => $attributeCode,
                'store_id' => $storeId,
                'status' => $status,
                'generated_content' => $generatedContent,
                'original_content' => $originalContent,
                'prompt_hash' => $promptHash,
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

    public function approve(int $logId): ?EnrichmentLog
    {
        $log = $this->logFactory->create();
        $this->resource->load($log, $logId);

        if ($log->getId()) {
            $log->setData('status', EnrichmentLog::STATUS_APPROVED);
            $this->resource->save($log);
            return $log;
        }

        return null;
    }

    public function reject(int $logId): ?EnrichmentLog
    {
        $log = $this->logFactory->create();
        $this->resource->load($log, $logId);

        if ($log->getId()) {
            $log->setData('status', EnrichmentLog::STATUS_REJECTED);
            $this->resource->save($log);
            return $log;
        }

        return null;
    }

    public function findByPromptHash(string $promptHash, string $attributeCode, int $storeId): ?string
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('prompt_hash', $promptHash);
        $collection->addFieldToFilter('attribute_code', $attributeCode);
        $collection->addFieldToFilter('store_id', $storeId);
        $collection->addFieldToFilter('generated_content', ['notnull' => true]);
        $collection->setPageSize(1);

        $item = $collection->getFirstItem();

        return $item->getId() ? $item->getData('generated_content') : null;
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

**Step 2: Update the test**

Replace `Test/Unit/Model/Product/EnrichmentLoggerTest.php` to match the new `log()` signature:

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
    public function test_log_creates_new_entry_with_content_and_hash(): void
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
                && $data['generated_content'] === 'AI output'
                && $data['original_content'] === 'old value'
                && $data['prompt_hash'] === 'abc123'
        ));

        $logFactory = $this->createMock(EnrichmentLogFactory::class);
        $logFactory->method('create')->willReturn($newLog);

        $resource = $this->createMock(EnrichmentLogResource::class);
        $resource->expects($this->once())->method('save')->with($newLog);

        $logger = new EnrichmentLogger($collectionFactory, $logFactory, $resource);
        $logger->log(42, 'description', 1, 'AI output', 'old value', 'abc123');
    }

    public function test_find_by_prompt_hash_returns_cached_content(): void
    {
        $item = $this->createMock(EnrichmentLog::class);
        $item->method('getId')->willReturn(1);
        $item->method('getData')->with('generated_content')->willReturn('cached response');

        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($item);

        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $logFactory = $this->createMock(EnrichmentLogFactory::class);
        $resource = $this->createMock(EnrichmentLogResource::class);

        $logger = new EnrichmentLogger($collectionFactory, $logFactory, $resource);
        $result = $logger->findByPromptHash('hash123', 'description', 1);

        $this->assertEquals('cached response', $result);
    }
}
```

**Step 3: Commit**

```bash
git add Model/Product/EnrichmentLogger.php Test/Unit/Model/Product/EnrichmentLoggerTest.php
git commit -m "feat: extend EnrichmentLogger with approve, reject, prompt hash dedup (#28)"
```

---

### Task 5: Update Enricher for review mode and deduplication

**Files:**
- Modify: `Model/Product/Enricher.php`

**Step 1: Update the enrichAttribute method**

In `Model/Product/Enricher.php`, replace the `enrichAttribute` method with:

```php
    public function enrichAttribute(Product $product, string $attributeCode): void
    {
        if (!$product->getData('mageos_catalogai_overwrite') && $product->getData($attributeCode)) {
            return;
        }
        if ($prompt = $this->promptResolver->resolve($attributeCode, $product)) {

            $resolvedPrompt = $this->parsePrompt($prompt, $product);
            $promptHash = hash('sha256', $resolvedPrompt);
            $storeId = (int)$product->getStoreId();
            $originalContent = (string)$product->getData($attributeCode);

            // Check for cached response with same prompt hash
            $cachedContent = $this->enrichmentLogger->findByPromptHash($promptHash, $attributeCode, $storeId);

            if ($cachedContent !== null) {
                $generatedContent = $cachedContent;
            } else {
                $response = $this->getClient()->chat()->create([
                    'model' => $this->config->getApiModel(),
                    'temperature' => $this->config->getTemperature(),
                    'frequency_penalty' => $this->config->getFrequencyPenalty(),
                    'presence_penalty' => $this->config->getPresencePenalty(),
                    'max_completion_tokens' => $this->config->getApiMaxTokens(),
                    'messages' => [
                        [
                            'role' => 'developer',
                            'content' => $this->config->getSystemPrompt()
                        ],
                        [
                            'role' => 'user',
                            'content' => $resolvedPrompt
                        ]
                    ]
                ]);

                if (!$result = $response->choices[0]) {
                    return;
                }
                $generatedContent = $result->message?->content ?? '';
                $this->backoff($response->meta());
            }

            $status = $this->config->requiresReview()
                ? EnrichmentLog::STATUS_PENDING_REVIEW
                : EnrichmentLog::STATUS_GENERATED;

            if (!$this->config->requiresReview()) {
                $product->setData($attributeCode, $generatedContent);
            }

            $this->enrichmentLogger->log(
                (int)$product->getId(),
                $attributeCode,
                $storeId,
                $generatedContent,
                $originalContent,
                $promptHash,
                $status
            );
        }
    }
```

Also add this import at the top:

```php
use MageOS\CatalogDataAI\Model\EnrichmentLog;
```

**Step 2: Commit**

```bash
git add Model/Product/Enricher.php
git commit -m "feat: Enricher supports review mode and prompt hash deduplication (#28)"
```

---

### Task 6: Create review grid — controller, layout, UI component

**Files:**
- Create: `Controller/Adminhtml/Review/Index.php`
- Create: `view/adminhtml/layout/catalogai_review_index.xml`
- Create: `view/adminhtml/ui_component/mageos_catalogai_enrichment_review_listing.xml`
- Create: `Model/ResourceModel/EnrichmentLog/Grid/Collection.php`
- Modify: `etc/adminhtml/menu.xml`
- Modify: `etc/adminhtml/di.xml`
- Modify: `etc/acl.xml`

**Step 1: Create the Index controller**

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Controller\Adminhtml\Review;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_CatalogDataAI::enrichment_review';

    public function __construct(
        Action\Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): Page
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('MageOS_CatalogDataAI::enrichment_review');
        $resultPage->getConfig()->getTitle()->prepend(__('AI Enrichment Review'));
        return $resultPage;
    }
}
```

**Step 2: Create the layout**

`view/adminhtml/layout/catalogai_review_index.xml`:

```xml
<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <referenceContainer name="content">
            <uiComponent name="mageos_catalogai_enrichment_review_listing"/>
        </referenceContainer>
    </body>
</page>
```

**Step 3: Create the grid UI component**

`view/adminhtml/ui_component/mageos_catalogai_enrichment_review_listing.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<listing xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Ui:etc/ui_configuration.xsd">
    <argument name="data" xsi:type="array">
        <item name="js_config" xsi:type="array">
            <item name="provider" xsi:type="string">mageos_catalogai_enrichment_review_listing.mageos_catalogai_enrichment_review_listing_data_source</item>
        </item>
    </argument>
    <settings>
        <spinner>mageos_catalogai_enrichment_review_columns</spinner>
        <deps>
            <dep>mageos_catalogai_enrichment_review_listing.mageos_catalogai_enrichment_review_listing_data_source</dep>
        </deps>
    </settings>
    <dataSource name="mageos_catalogai_enrichment_review_listing_data_source" component="Magento_Ui/js/grid/provider">
        <settings>
            <updateUrl path="mui/index/render"/>
        </settings>
        <dataProvider class="Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider" name="mageos_catalogai_enrichment_review_listing_data_source">
            <settings>
                <requestFieldName>log_id</requestFieldName>
                <primaryFieldName>log_id</primaryFieldName>
            </settings>
        </dataProvider>
    </dataSource>
    <listingToolbar name="listing_top">
        <settings>
            <sticky>true</sticky>
        </settings>
        <bookmark name="bookmarks"/>
        <columnsControls name="columns_controls"/>
        <filters name="listing_filters"/>
        <paging name="listing_paging"/>
        <massaction name="listing_massaction">
            <action name="approve">
                <settings>
                    <url path="catalogai/review/massApprove"/>
                    <type>approve</type>
                    <label translate="true">Approve</label>
                </settings>
            </action>
            <action name="reject">
                <settings>
                    <confirm>
                        <message translate="true">Reject and revert selected items?</message>
                        <title translate="true">Reject</title>
                    </confirm>
                    <url path="catalogai/review/massReject"/>
                    <type>reject</type>
                    <label translate="true">Reject</label>
                </settings>
            </action>
        </massaction>
    </listingToolbar>
    <columns name="mageos_catalogai_enrichment_review_columns">
        <selectionsColumn name="ids">
            <settings>
                <indexField>log_id</indexField>
            </settings>
        </selectionsColumn>
        <column name="log_id">
            <settings>
                <filter>textRange</filter>
                <label translate="true">ID</label>
                <sorting>desc</sorting>
            </settings>
        </column>
        <column name="entity_id">
            <settings>
                <filter>textRange</filter>
                <label translate="true">Product ID</label>
            </settings>
        </column>
        <column name="attribute_code">
            <settings>
                <filter>text</filter>
                <label translate="true">Attribute</label>
            </settings>
        </column>
        <column name="store_id">
            <settings>
                <filter>textRange</filter>
                <label translate="true">Store</label>
            </settings>
        </column>
        <column name="status">
            <settings>
                <filter>select</filter>
                <label translate="true">Status</label>
                <dataType>select</dataType>
                <options class="MageOS\CatalogDataAI\Model\Config\Source\EnrichmentStatus"/>
            </settings>
        </column>
        <column name="generated_content">
            <settings>
                <label translate="true">Generated Content</label>
                <bodyTmpl>ui/grid/cells/html</bodyTmpl>
                <sortable>false</sortable>
            </settings>
        </column>
        <column name="generated_at" class="Magento\Ui\Component\Listing\Columns\Date" component="Magento_Ui/js/grid/columns/date">
            <settings>
                <filter>dateRange</filter>
                <dataType>date</dataType>
                <label translate="true">Generated At</label>
            </settings>
        </column>
    </columns>
</listing>
```

**Step 4: Create the grid collection**

`Model/ResourceModel/EnrichmentLog/Grid/Collection.php`:

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\ResourceModel\EnrichmentLog\Grid;

use Magento\Framework\Api\Search\AggregationInterface;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use MageOS\CatalogDataAI\Model\ResourceModel\EnrichmentLog\Collection as BaseCollection;
use Psr\Log\LoggerInterface;

class Collection extends BaseCollection implements SearchResultInterface
{
    private AggregationInterface $aggregations;

    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        string $mainTable = 'mageos_catalogai_enrichment_log',
        string $resourceModel = \MageOS\CatalogDataAI\Model\ResourceModel\EnrichmentLog::class,
        ?AdapterInterface $connection = null,
        ?AbstractDb $resource = null
    ) {
        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $connection, $resource);
        $this->_mainTable = $mainTable;
        $this->_setIdFieldName('log_id');
        $this->setModel(\Magento\Framework\View\Element\UiComponent\DataProvider\Document::class);
    }

    public function getAggregations(): AggregationInterface
    {
        return $this->aggregations;
    }

    public function setAggregations($aggregations): self
    {
        $this->aggregations = $aggregations;
        return $this;
    }

    public function getSearchCriteria(): ?SearchCriteriaInterface
    {
        return null;
    }

    public function setSearchCriteria(SearchCriteriaInterface $searchCriteria): self
    {
        return $this;
    }

    public function getTotalCount(): int
    {
        return $this->getSize();
    }

    public function setTotalCount($totalCount): self
    {
        return $this;
    }

    public function setItems(?array $items = null): self
    {
        return $this;
    }
}
```

**Step 5: Create the EnrichmentStatus source model**

`Model/Config/Source/EnrichmentStatus.php`:

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use MageOS\CatalogDataAI\Model\EnrichmentLog;

class EnrichmentStatus implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => EnrichmentLog::STATUS_GENERATED, 'label' => __('Generated')],
            ['value' => EnrichmentLog::STATUS_PENDING_REVIEW, 'label' => __('Pending Review')],
            ['value' => EnrichmentLog::STATUS_MODIFIED, 'label' => __('Modified')],
            ['value' => EnrichmentLog::STATUS_APPROVED, 'label' => __('Approved')],
            ['value' => EnrichmentLog::STATUS_REJECTED, 'label' => __('Rejected')],
        ];
    }
}
```

**Step 6: Add menu item**

In `etc/adminhtml/menu.xml`, add a second `<add>` entry inside `<menu>`:

```xml
        <add id="MageOS_CatalogDataAI::enrichment_review"
             title="AI Enrichment Review"
             module="MageOS_CatalogDataAI"
             sortOrder="91"
             parent="Magento_Catalog::catalog"
             action="catalogai/review"
             resource="MageOS_CatalogDataAI::enrichment_review"/>
```

**Step 7: Add ACL resource**

In `etc/acl.xml`, add after the `prompt_rules` resource:

```xml
                <resource id="MageOS_CatalogDataAI::enrichment_review" title="AI Enrichment Review" translate="title" sortOrder="51" />
```

**Step 8: Register the grid data source in adminhtml di.xml**

In `etc/adminhtml/di.xml`, inside the existing `CollectionFactory` `<type>` → `<argument name="collections">` → `<array>`, add:

```xml
                <item name="mageos_catalogai_enrichment_review_listing_data_source" xsi:type="string">MageOS\CatalogDataAI\Model\ResourceModel\EnrichmentLog\Grid\Collection</item>
```

**Step 9: Commit**

```bash
git add Controller/Adminhtml/Review/Index.php \
    view/adminhtml/layout/catalogai_review_index.xml \
    view/adminhtml/ui_component/mageos_catalogai_enrichment_review_listing.xml \
    Model/ResourceModel/EnrichmentLog/Grid/Collection.php \
    Model/Config/Source/EnrichmentStatus.php \
    etc/adminhtml/menu.xml \
    etc/adminhtml/di.xml \
    etc/acl.xml
git commit -m "feat: add enrichment review admin grid (#28)"
```

---

### Task 7: Create MassApprove controller

**Files:**
- Create: `Controller/Adminhtml/Review/MassApprove.php`

**Step 1: Create the controller**

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Controller\Adminhtml\Review;

use Magento\Backend\App\Action;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;
use MageOS\CatalogDataAI\Model\Product\EnrichmentLogger;
use MageOS\CatalogDataAI\Model\ResourceModel\EnrichmentLog\CollectionFactory;

class MassApprove extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_CatalogDataAI::enrichment_review';

    public function __construct(
        Action\Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly EnrichmentLogger $enrichmentLogger,
        private readonly ProductRepositoryInterface $productRepository
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $approved = 0;

        foreach ($collection as $logEntry) {
            $log = $this->enrichmentLogger->approve((int)$logEntry->getId());
            if ($log) {
                $product = $this->productRepository->getById(
                    (int)$log->getData('entity_id'),
                    true,
                    (int)$log->getData('store_id')
                );
                $product->setData($log->getData('attribute_code'), $log->getData('generated_content'));
                $this->productRepository->save($product);
                $approved++;
            }
        }

        $this->messageManager->addSuccessMessage(
            __('A total of %1 entry(ies) have been approved and published.', $approved)
        );
        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
```

**Step 2: Commit**

```bash
git add Controller/Adminhtml/Review/MassApprove.php
git commit -m "feat: add mass approve controller for enrichment review (#28)"
```

---

### Task 8: Create MassReject controller

**Files:**
- Create: `Controller/Adminhtml/Review/MassReject.php`

**Step 1: Create the controller**

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Controller\Adminhtml\Review;

use Magento\Backend\App\Action;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;
use MageOS\CatalogDataAI\Model\Product\EnrichmentLogger;
use MageOS\CatalogDataAI\Model\ResourceModel\EnrichmentLog\CollectionFactory;

class MassReject extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_CatalogDataAI::enrichment_review';

    public function __construct(
        Action\Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly EnrichmentLogger $enrichmentLogger,
        private readonly ProductRepositoryInterface $productRepository
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $rejected = 0;

        foreach ($collection as $logEntry) {
            $log = $this->enrichmentLogger->reject((int)$logEntry->getId());
            if ($log && $log->getData('original_content') !== null) {
                $product = $this->productRepository->getById(
                    (int)$log->getData('entity_id'),
                    true,
                    (int)$log->getData('store_id')
                );
                $product->setData($log->getData('attribute_code'), $log->getData('original_content'));
                $this->productRepository->save($product);
            }
            $rejected++;
        }

        $this->messageManager->addSuccessMessage(
            __('A total of %1 entry(ies) have been rejected.', $rejected)
        );
        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
```

**Step 2: Commit**

```bash
git add Controller/Adminhtml/Review/MassReject.php
git commit -m "feat: add mass reject controller for enrichment review (#28)"
```

---

### Task 9: Smoke test

**Step 1: Run Magento setup commands (inside Warden)**

```bash
warden env exec php-fpm bin/magento setup:upgrade
warden env exec php-fpm bin/magento setup:di:compile
```

**Step 2: Verify new columns exist**

Check that `generated_content`, `original_content`, and `prompt_hash` columns were added to the enrichment log table.

**Step 3: Verify admin pages**

1. Navigate to **Catalog > AI Enrichment Review** — grid should load
2. Navigate to **AI Services > Data Enrichment > Settings** — verify "Require Review Before Publish" toggle exists
3. Enable review mode, trigger an enrichment — verify content goes to pending_review status
4. In the review grid, approve an entry — verify product attribute is updated
5. Reject an entry — verify product attribute reverts to original

**Step 4: Run all tests**

```bash
vendor/bin/phpunit Test/
```

**Step 5: Final commit (if adjustments needed)**

```bash
git add -A
git commit -m "fix: adjustments from smoke testing Phase 4"
```

---

## Summary of Files Changed/Created

| Action | File |
|--------|------|
| **Schema & Config** | |
| Modify | `etc/db_schema.xml` |
| Modify | `etc/db_schema_whitelist.json` |
| Modify | `etc/adminhtml/system.xml` |
| Modify | `etc/config.xml` |
| Modify | `Model/Config.php` |
| Modify | `etc/acl.xml` |
| Modify | `etc/adminhtml/menu.xml` |
| Modify | `etc/adminhtml/di.xml` |
| **Core Logic** | |
| Modify | `Model/EnrichmentLog.php` |
| Modify | `Model/Product/EnrichmentLogger.php` |
| Modify | `Model/Product/Enricher.php` |
| Modify | `Test/Unit/Model/Product/EnrichmentLoggerTest.php` |
| **Review Grid** | |
| Create | `Controller/Adminhtml/Review/Index.php` |
| Create | `Controller/Adminhtml/Review/MassApprove.php` |
| Create | `Controller/Adminhtml/Review/MassReject.php` |
| Create | `Model/ResourceModel/EnrichmentLog/Grid/Collection.php` |
| Create | `Model/Config/Source/EnrichmentStatus.php` |
| Create | `view/adminhtml/layout/catalogai_review_index.xml` |
| Create | `view/adminhtml/ui_component/mageos_catalogai_enrichment_review_listing.xml` |
