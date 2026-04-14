# Phase 5: Structured Attribute Extraction — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add a separate "Attribute Extraction" enrichment type that uses AI to fill structured product attributes (dropdowns, multiselects, booleans, decimals) by extracting values from unstructured text like product descriptions, with value mapping, validation, and review integration.

**Architecture:** Extraction rules are stored in a new `mageos_catalogai_extraction_rule` DB table, managed via an admin grid/form (same pattern as Phase 3 prompt rules). Each rule defines a source attribute (where to read), a target attribute (where to write), an extraction prompt, and an optional value mapping (JSON mapping AI output strings to Magento option IDs). The `AttributeExtractor` service calls OpenAI with JSON mode (`response_format: { type: "json_object" }`), validates the response against the target attribute type, resolves dropdown/multiselect values via the mapping, and logs results through the Phase 4 `EnrichmentLogger` with `pending_review` status by default. A new `ExtractAttributes` mass action on the product grid triggers extraction.

**Tech Stack:** PHP 8.1+, Magento 2.4.x, OpenAI JSON mode, UI Components (admin grid/form), PHPUnit 9.5

---

### Task 1: Add extraction_rule DB table schema

**Files:**
- Modify: `etc/db_schema.xml`
- Modify: `etc/db_schema_whitelist.json`

**Step 1: Add the table to db_schema.xml**

Add after the `mageos_catalogai_prompt_rule` table (before `</schema>`):

```xml
    <table name="mageos_catalogai_extraction_rule" resource="default" engine="innodb"
           comment="AI Attribute Extraction Rules">
        <column xsi:type="int" name="rule_id" unsigned="true" nullable="false" identity="true"
                comment="Rule ID"/>
        <column xsi:type="varchar" name="name" nullable="false" length="255"
                comment="Rule Name"/>
        <column xsi:type="varchar" name="source_attributes" nullable="false" length="1024"
                comment="Source Attribute Codes (comma-separated)"/>
        <column xsi:type="varchar" name="target_attribute" nullable="false" length="255"
                comment="Target Attribute Code"/>
        <column xsi:type="text" name="extraction_prompt" nullable="false"
                comment="Extraction Prompt Template"/>
        <column xsi:type="text" name="value_mapping" nullable="true"
                comment="JSON Value Mapping (AI output to option IDs)"/>
        <column xsi:type="text" name="conditions_serialized" nullable="true"
                comment="Serialized Product Conditions"/>
        <column xsi:type="text" name="store_ids" nullable="false"
                comment="Store IDs (comma-separated, 0 = all)"/>
        <column xsi:type="int" name="priority" unsigned="false" nullable="false" default="0"
                comment="Priority (highest wins)"/>
        <column xsi:type="boolean" name="is_active" nullable="false" default="true"
                comment="Is Active"/>
        <column xsi:type="timestamp" name="created_at" nullable="false" default="CURRENT_TIMESTAMP"
                comment="Created At"/>
        <column xsi:type="timestamp" name="updated_at" nullable="false" default="CURRENT_TIMESTAMP"
                on_update="true" comment="Updated At"/>
        <constraint xsi:type="primary" referenceId="PRIMARY">
            <column name="rule_id"/>
        </constraint>
        <index referenceId="MAGEOS_CATALOGAI_EXTRACT_RULE_TARGET" indexType="btree">
            <column name="target_attribute"/>
        </index>
        <index referenceId="MAGEOS_CATALOGAI_EXTRACT_RULE_IS_ACTIVE" indexType="btree">
            <column name="is_active"/>
        </index>
    </table>
```

**Step 2: Update the whitelist**

Add to `etc/db_schema_whitelist.json`:

```json
    "mageos_catalogai_extraction_rule": {
        "column": {
            "rule_id": true,
            "name": true,
            "source_attributes": true,
            "target_attribute": true,
            "extraction_prompt": true,
            "value_mapping": true,
            "conditions_serialized": true,
            "store_ids": true,
            "priority": true,
            "is_active": true,
            "created_at": true,
            "updated_at": true
        },
        "constraint": {
            "PRIMARY": true
        },
        "index": {
            "MAGEOS_CATALOGAI_EXTRACT_RULE_TARGET": true,
            "MAGEOS_CATALOGAI_EXTRACT_RULE_IS_ACTIVE": true
        }
    }
```

**Step 3: Commit**

```bash
git add etc/db_schema.xml etc/db_schema_whitelist.json
git commit -m "feat: add extraction_rule DB table schema (#7)"
```

---

### Task 2: Create ExtractionRule model, resource model, and collection

**Files:**
- Create: `Api/Data/ExtractionRuleInterface.php`
- Create: `Model/ExtractionRule.php`
- Create: `Model/ResourceModel/ExtractionRule.php`
- Create: `Model/ResourceModel/ExtractionRule/Collection.php`

**Step 1: Create the interface**

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Api\Data;

interface ExtractionRuleInterface
{
    public const RULE_ID = 'rule_id';
    public const NAME = 'name';
    public const SOURCE_ATTRIBUTES = 'source_attributes';
    public const TARGET_ATTRIBUTE = 'target_attribute';
    public const EXTRACTION_PROMPT = 'extraction_prompt';
    public const VALUE_MAPPING = 'value_mapping';
    public const CONDITIONS_SERIALIZED = 'conditions_serialized';
    public const STORE_IDS = 'store_ids';
    public const PRIORITY = 'priority';
    public const IS_ACTIVE = 'is_active';
}
```

**Step 2: Create the model**

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model;

use Magento\CatalogRule\Model\Rule\Condition\Combine;
use Magento\Rule\Model\AbstractModel;
use MageOS\CatalogDataAI\Api\Data\ExtractionRuleInterface;
use MageOS\CatalogDataAI\Model\ResourceModel\ExtractionRule as ExtractionRuleResource;

class ExtractionRule extends AbstractModel implements ExtractionRuleInterface
{
    protected $_eventPrefix = 'mageos_catalogai_extraction_rule';

    protected function _construct(): void
    {
        $this->_init(ExtractionRuleResource::class);
    }

    public function getConditionsInstance(): \Magento\Rule\Model\Condition\Combine
    {
        return $this->_conditionFactory->create(Combine::class);
    }

    public function getActionsInstance(): \Magento\Rule\Model\Action\Collection
    {
        return $this->_actionFactory->create(\Magento\Rule\Model\Action\Collection::class);
    }

    public function getSourceAttributes(): array
    {
        $value = (string)$this->getData(self::SOURCE_ATTRIBUTES);
        return $value ? array_map('trim', explode(',', $value)) : [];
    }

    public function getTargetAttribute(): string
    {
        return (string)$this->getData(self::TARGET_ATTRIBUTE);
    }

    public function getExtractionPrompt(): string
    {
        return (string)$this->getData(self::EXTRACTION_PROMPT);
    }

    public function getValueMapping(): array
    {
        $json = $this->getData(self::VALUE_MAPPING);
        if (!$json) {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function matchesProduct(\Magento\Catalog\Model\Product $product): bool
    {
        return $this->getConditions()->validate($product);
    }

    public function matchesStore(int $storeId): bool
    {
        $storeIds = (string)$this->getData(self::STORE_IDS);
        if ($storeIds === '' || $storeIds === '0') {
            return true;
        }
        $ids = array_map('intval', explode(',', $storeIds));
        return in_array(0, $ids, true) || in_array($storeId, $ids, true);
    }
}
```

**Step 3: Create the resource model**

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class ExtractionRule extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('mageos_catalogai_extraction_rule', 'rule_id');
    }
}
```

**Step 4: Create the collection**

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\ResourceModel\ExtractionRule;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use MageOS\CatalogDataAI\Model\ExtractionRule;
use MageOS\CatalogDataAI\Model\ResourceModel\ExtractionRule as ExtractionRuleResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'rule_id';

    protected function _construct(): void
    {
        $this->_init(ExtractionRule::class, ExtractionRuleResource::class);
    }
}
```

**Step 5: Commit**

```bash
git add Api/Data/ExtractionRuleInterface.php Model/ExtractionRule.php Model/ResourceModel/ExtractionRule.php Model/ResourceModel/ExtractionRule/Collection.php
git commit -m "feat: add ExtractionRule model with conditions and value mapping (#7)"
```

---

### Task 3: Create AttributeExtractor service

**Files:**
- Create: `Model/Product/AttributeExtractor.php`
- Test: `Test/Unit/Model/Product/AttributeExtractorTest.php`

This is the core service. It finds matching extraction rules, calls OpenAI with JSON mode, validates the response, maps dropdown values, and logs through EnrichmentLogger.

**Step 1: Write the test**

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Model\Product;

use MageOS\CatalogDataAI\Model\Product\AttributeExtractor;
use PHPUnit\Framework\TestCase;

final class AttributeExtractorTest extends TestCase
{
    public function test_resolve_mapped_value_exact_match(): void
    {
        $mapping = ['Red' => '42', 'Blue' => '43', 'Green' => '44'];
        $result = AttributeExtractor::resolveMappedValue('Red', $mapping);
        $this->assertEquals('42', $result);
    }

    public function test_resolve_mapped_value_case_insensitive(): void
    {
        $mapping = ['Red' => '42', 'Blue' => '43'];
        $result = AttributeExtractor::resolveMappedValue('red', $mapping);
        $this->assertEquals('42', $result);
    }

    public function test_resolve_mapped_value_fuzzy_match(): void
    {
        $mapping = ['Crimson Red' => '42', 'Ocean Blue' => '43'];
        $result = AttributeExtractor::resolveMappedValue('crimson', $mapping);
        $this->assertEquals('42', $result);
    }

    public function test_resolve_mapped_value_returns_null_on_no_match(): void
    {
        $mapping = ['Red' => '42', 'Blue' => '43'];
        $result = AttributeExtractor::resolveMappedValue('Yellow', $mapping);
        $this->assertNull($result);
    }
}
```

**Step 2: Create the service**

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\Product;

use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\Product;
use MageOS\CatalogDataAI\Model\Config;
use MageOS\CatalogDataAI\Model\EnrichmentLog;
use MageOS\CatalogDataAI\Model\ExtractionRule;
use MageOS\CatalogDataAI\Model\ResourceModel\ExtractionRule\CollectionFactory;
use OpenAI\Client;
use OpenAI\Factory;
use Psr\Log\LoggerInterface;

class AttributeExtractor
{
    private Client $client;

    public function __construct(
        private readonly Factory $clientFactory,
        private readonly Config $config,
        private readonly CollectionFactory $ruleCollectionFactory,
        private readonly EnrichmentLogger $enrichmentLogger,
        private readonly ProductAttributeRepositoryInterface $attributeRepository,
        private readonly Enricher $enricher,
        private readonly LoggerInterface $logger
    ) {
    }

    private function getClient(): Client
    {
        if (!isset($this->client)) {
            $this->client = $this->clientFactory
                ->withOrganization($this->config->getOrganizationId())
                ->withApiKey($this->config->getApiKey())
                ->withProject($this->config->getProjectId())
                ->make();
        }

        return $this->client;
    }

    public function execute(Product $product): void
    {
        $storeId = (int)$product->getStoreId();

        $collection = $this->ruleCollectionFactory->create();
        $collection->addFieldToFilter('is_active', 1);
        $collection->setOrder('priority', 'DESC');

        $processedTargets = [];

        /** @var ExtractionRule $rule */
        foreach ($collection as $rule) {
            $target = $rule->getTargetAttribute();

            if (isset($processedTargets[$target])) {
                continue;
            }

            if (!$rule->matchesStore($storeId) || !$rule->matchesProduct($product)) {
                continue;
            }

            $this->extractAttribute($product, $rule);
            $processedTargets[$target] = true;
        }
    }

    private function extractAttribute(Product $product, ExtractionRule $rule): void
    {
        $sourceData = $this->buildSourceData($product, $rule->getSourceAttributes());
        if (!$sourceData) {
            return;
        }

        $resolvedPrompt = $this->enricher->parsePrompt($rule->getExtractionPrompt(), $product);
        $promptHash = hash('sha256', $resolvedPrompt . $sourceData);
        $storeId = (int)$product->getStoreId();
        $targetAttribute = $rule->getTargetAttribute();
        $originalContent = (string)$product->getData($targetAttribute);

        // Check cache
        $cached = $this->enrichmentLogger->findByPromptHash($promptHash, $targetAttribute, $storeId);
        if ($cached !== null) {
            $extractedValue = $cached;
        } else {
            $extractedValue = $this->callOpenAI($resolvedPrompt, $sourceData);
            if ($extractedValue === null) {
                return;
            }
        }

        // Map and validate
        $finalValue = $this->mapAndValidate($extractedValue, $targetAttribute, $rule->getValueMapping());
        if ($finalValue === null) {
            $this->logger->warning(sprintf(
                'Extraction validation failed for product %d, attribute %s: AI returned "%s"',
                $product->getId(),
                $targetAttribute,
                $extractedValue
            ));
            return;
        }

        // Always pending_review for extractions
        $this->enrichmentLogger->log(
            (int)$product->getId(),
            $targetAttribute,
            $storeId,
            (string)$finalValue,
            $originalContent,
            $promptHash,
            EnrichmentLog::STATUS_PENDING_REVIEW
        );
    }

    private function buildSourceData(Product $product, array $sourceAttributes): string
    {
        $parts = [];
        foreach ($sourceAttributes as $code) {
            $value = $product->getData($code);
            if ($value) {
                $parts[] = $code . ': ' . $value;
            }
        }
        return implode("\n", $parts);
    }

    private function callOpenAI(string $prompt, string $sourceData): ?string
    {
        $response = $this->getClient()->chat()->create([
            'model' => $this->config->getApiModel(),
            'temperature' => 0,
            'max_completion_tokens' => 256,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                [
                    'role' => 'developer',
                    'content' => 'You are a data extraction assistant. Extract the requested value and return ONLY a JSON object with a "value" key. Example: {"value": "extracted data"}'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt . "\n\nSource data:\n" . $sourceData
                ]
            ]
        ]);

        if (!$result = $response->choices[0]) {
            return null;
        }

        $content = $result->message?->content ?? '';
        $decoded = json_decode($content, true);

        $this->enricher->backoff($response->meta());

        return isset($decoded['value']) ? (string)$decoded['value'] : null;
    }

    private function mapAndValidate(string $value, string $targetAttributeCode, array $valueMapping): ?string
    {
        try {
            $attribute = $this->attributeRepository->get($targetAttributeCode);
        } catch (\Exception $e) {
            return null;
        }

        $frontendInput = $attribute->getFrontendInput();

        switch ($frontendInput) {
            case 'select':
            case 'multiselect':
                if (!empty($valueMapping)) {
                    return self::resolveMappedValue($value, $valueMapping);
                }
                // Try matching against existing option labels
                $options = $attribute->getSource()->getAllOptions(false);
                foreach ($options as $option) {
                    if (strcasecmp((string)$option['label'], $value) === 0) {
                        return (string)$option['value'];
                    }
                }
                return null;

            case 'boolean':
                $lower = strtolower(trim($value));
                if (in_array($lower, ['yes', 'true', '1'], true)) {
                    return '1';
                }
                if (in_array($lower, ['no', 'false', '0'], true)) {
                    return '0';
                }
                return null;

            case 'price':
            case 'weight':
                return is_numeric($value) ? $value : null;

            case 'text':
            case 'textarea':
                return $value;

            default:
                return $value;
        }
    }

    public static function resolveMappedValue(string $aiOutput, array $mapping): ?string
    {
        // Exact match (case-insensitive)
        foreach ($mapping as $label => $optionId) {
            if (strcasecmp($label, $aiOutput) === 0) {
                return (string)$optionId;
            }
        }

        // Fuzzy: AI output is a substring of a mapping key (case-insensitive)
        $lowerOutput = strtolower($aiOutput);
        foreach ($mapping as $label => $optionId) {
            if (str_contains(strtolower($label), $lowerOutput)) {
                return (string)$optionId;
            }
        }

        return null;
    }
}
```

**Step 3: Commit**

```bash
git add Model/Product/AttributeExtractor.php Test/Unit/Model/Product/AttributeExtractorTest.php
git commit -m "feat: add AttributeExtractor service with JSON mode and value mapping (#7)"
```

---

### Task 4: Admin grid for extraction rules

**Files:**
- Create: `Controller/Adminhtml/ExtractionRule/Index.php`
- Create: `view/adminhtml/layout/catalogai_extractionrule_index.xml`
- Create: `view/adminhtml/ui_component/mageos_catalogai_extraction_rule_listing.xml`
- Create: `Ui/Component/Listing/Column/ExtractionRuleActions.php`
- Create: `Model/ResourceModel/ExtractionRule/Grid/Collection.php`
- Modify: `etc/adminhtml/menu.xml`
- Modify: `etc/adminhtml/di.xml`
- Modify: `etc/acl.xml`

**Step 1: Create the Index controller**

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Controller\Adminhtml\ExtractionRule;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_CatalogDataAI::extraction_rules';

    public function __construct(
        Action\Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): Page
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('MageOS_CatalogDataAI::extraction_rules');
        $resultPage->getConfig()->getTitle()->prepend(__('AI Extraction Rules'));
        return $resultPage;
    }
}
```

**Step 2: Create the layout**

`view/adminhtml/layout/catalogai_extractionrule_index.xml`:

```xml
<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <referenceContainer name="content">
            <uiComponent name="mageos_catalogai_extraction_rule_listing"/>
        </referenceContainer>
    </body>
</page>
```

**Step 3: Create the listing UI component**

`view/adminhtml/ui_component/mageos_catalogai_extraction_rule_listing.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<listing xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Ui:etc/ui_configuration.xsd">
    <argument name="data" xsi:type="array">
        <item name="js_config" xsi:type="array">
            <item name="provider" xsi:type="string">mageos_catalogai_extraction_rule_listing.mageos_catalogai_extraction_rule_listing_data_source</item>
        </item>
    </argument>
    <settings>
        <buttons>
            <button name="add">
                <url path="catalogai/extractionrule/new"/>
                <class>primary</class>
                <label translate="true">Add New Extraction Rule</label>
            </button>
        </buttons>
        <spinner>mageos_catalogai_extraction_rule_columns</spinner>
        <deps>
            <dep>mageos_catalogai_extraction_rule_listing.mageos_catalogai_extraction_rule_listing_data_source</dep>
        </deps>
    </settings>
    <dataSource name="mageos_catalogai_extraction_rule_listing_data_source" component="Magento_Ui/js/grid/provider">
        <settings>
            <updateUrl path="mui/index/render"/>
        </settings>
        <dataProvider class="Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider" name="mageos_catalogai_extraction_rule_listing_data_source">
            <settings>
                <requestFieldName>rule_id</requestFieldName>
                <primaryFieldName>rule_id</primaryFieldName>
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
    </listingToolbar>
    <columns name="mageos_catalogai_extraction_rule_columns">
        <column name="rule_id">
            <settings>
                <filter>textRange</filter>
                <label translate="true">ID</label>
                <sorting>asc</sorting>
            </settings>
        </column>
        <column name="name">
            <settings>
                <filter>text</filter>
                <label translate="true">Name</label>
            </settings>
        </column>
        <column name="source_attributes">
            <settings>
                <filter>text</filter>
                <label translate="true">Source</label>
            </settings>
        </column>
        <column name="target_attribute">
            <settings>
                <filter>text</filter>
                <label translate="true">Target</label>
            </settings>
        </column>
        <column name="priority">
            <settings>
                <filter>textRange</filter>
                <label translate="true">Priority</label>
            </settings>
        </column>
        <column name="is_active" component="Magento_Ui/js/grid/columns/select">
            <settings>
                <filter>select</filter>
                <label translate="true">Active</label>
                <dataType>select</dataType>
                <options class="Magento\Config\Model\Config\Source\Yesno"/>
            </settings>
        </column>
        <actionsColumn name="actions" class="MageOS\CatalogDataAI\Ui\Component\Listing\Column\ExtractionRuleActions">
            <settings>
                <indexField>rule_id</indexField>
            </settings>
        </actionsColumn>
    </columns>
</listing>
```

**Step 4: Create the actions column**

`Ui/Component/Listing/Column/ExtractionRuleActions.php`:

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class ExtractionRuleActions extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                if (isset($item['rule_id'])) {
                    $item[$this->getData('name')] = [
                        'edit' => [
                            'href' => $this->urlBuilder->getUrl(
                                'catalogai/extractionrule/edit',
                                ['rule_id' => $item['rule_id']]
                            ),
                            'label' => __('Edit'),
                        ],
                        'delete' => [
                            'href' => $this->urlBuilder->getUrl(
                                'catalogai/extractionrule/delete',
                                ['rule_id' => $item['rule_id']]
                            ),
                            'label' => __('Delete'),
                            'confirm' => [
                                'title' => __('Delete Rule'),
                                'message' => __('Are you sure?'),
                            ],
                        ],
                    ];
                }
            }
        }
        return $dataSource;
    }
}
```

**Step 5: Create the grid collection**

`Model/ResourceModel/ExtractionRule/Grid/Collection.php`:

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\ResourceModel\ExtractionRule\Grid;

use Magento\Framework\Api\Search\AggregationInterface;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use MageOS\CatalogDataAI\Model\ResourceModel\ExtractionRule\Collection as BaseCollection;
use Psr\Log\LoggerInterface;

class Collection extends BaseCollection implements SearchResultInterface
{
    private AggregationInterface $aggregations;

    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        string $mainTable = 'mageos_catalogai_extraction_rule',
        string $resourceModel = \MageOS\CatalogDataAI\Model\ResourceModel\ExtractionRule::class,
        ?AdapterInterface $connection = null,
        ?AbstractDb $resource = null
    ) {
        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $connection, $resource);
        $this->_mainTable = $mainTable;
        $this->_setIdFieldName('rule_id');
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

**Step 6: Update menu.xml** — add after enrichment_review:

```xml
        <add id="MageOS_CatalogDataAI::extraction_rules"
             title="AI Extraction Rules"
             module="MageOS_CatalogDataAI"
             sortOrder="92"
             parent="Magento_Catalog::catalog"
             action="catalogai/extractionrule"
             resource="MageOS_CatalogDataAI::extraction_rules"/>
```

**Step 7: Update acl.xml** — add after enrichment_review resource:

```xml
                <resource id="MageOS_CatalogDataAI::extraction_rules" title="AI Extraction Rules" translate="title" sortOrder="52" />
```

**Step 8: Update adminhtml/di.xml** — add to the collections array:

```xml
                <item name="mageos_catalogai_extraction_rule_listing_data_source" xsi:type="string">MageOS\CatalogDataAI\Model\ResourceModel\ExtractionRule\Grid\Collection</item>
```

**Step 9: Commit**

```bash
git add Controller/Adminhtml/ExtractionRule/Index.php \
    view/adminhtml/layout/catalogai_extractionrule_index.xml \
    view/adminhtml/ui_component/mageos_catalogai_extraction_rule_listing.xml \
    Ui/Component/Listing/Column/ExtractionRuleActions.php \
    Model/ResourceModel/ExtractionRule/Grid/Collection.php \
    etc/adminhtml/menu.xml etc/adminhtml/di.xml etc/acl.xml
git commit -m "feat: add extraction rules admin grid with listing and menu (#7)"
```

---

### Task 5: Admin form for extraction rules — CRUD controllers + UI component

**Files:**
- Create: `Controller/Adminhtml/ExtractionRule/Edit.php`
- Create: `Controller/Adminhtml/ExtractionRule/NewAction.php`
- Create: `Controller/Adminhtml/ExtractionRule/Save.php`
- Create: `Controller/Adminhtml/ExtractionRule/Delete.php`
- Create: `Model/ExtractionRule/DataProvider.php`
- Create: `Block/Adminhtml/ExtractionRule/Edit/SaveButton.php`
- Create: `Block/Adminhtml/ExtractionRule/Edit/DeleteButton.php`
- Create: `view/adminhtml/layout/catalogai_extractionrule_edit.xml`
- Create: `view/adminhtml/layout/catalogai_extractionrule_new.xml`
- Create: `view/adminhtml/ui_component/mageos_catalogai_extraction_rule_form.xml`

**Step 1: Create Edit controller**

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Controller\Adminhtml\ExtractionRule;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use MageOS\CatalogDataAI\Model\ExtractionRuleFactory;
use MageOS\CatalogDataAI\Model\ResourceModel\ExtractionRule as ExtractionRuleResource;

class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_CatalogDataAI::extraction_rules';

    public function __construct(
        Action\Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly ExtractionRuleFactory $ruleFactory,
        private readonly ExtractionRuleResource $ruleResource
    ) {
        parent::__construct($context);
    }

    public function execute(): Page
    {
        $ruleId = (int)$this->getRequest()->getParam('rule_id');
        $rule = $this->ruleFactory->create();

        if ($ruleId) {
            $this->ruleResource->load($rule, $ruleId);
            if (!$rule->getId()) {
                $this->messageManager->addErrorMessage(__('This rule no longer exists.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('MageOS_CatalogDataAI::extraction_rules');
        $resultPage->getConfig()->getTitle()->prepend(
            $ruleId ? __('Edit Extraction Rule: %1', $rule->getData('name')) : __('New Extraction Rule')
        );

        return $resultPage;
    }
}
```

**Step 2: Create NewAction controller**

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Controller\Adminhtml\ExtractionRule;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;

class NewAction extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_CatalogDataAI::extraction_rules';

    public function execute()
    {
        return $this->resultFactory->create(ResultFactory::TYPE_FORWARD)->forward('edit');
    }
}
```

**Step 3: Create Save controller**

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Controller\Adminhtml\ExtractionRule;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use MageOS\CatalogDataAI\Model\ExtractionRuleFactory;
use MageOS\CatalogDataAI\Model\ResourceModel\ExtractionRule as ExtractionRuleResource;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_CatalogDataAI::extraction_rules';

    public function __construct(
        Action\Context $context,
        private readonly ExtractionRuleFactory $ruleFactory,
        private readonly ExtractionRuleResource $ruleResource
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $data = $this->getRequest()->getPostValue();
        $redirect = $this->resultRedirectFactory->create();

        if (!$data) {
            return $redirect->setPath('*/*/');
        }

        $ruleId = (int)($data['rule_id'] ?? 0);
        $rule = $this->ruleFactory->create();

        if ($ruleId) {
            $this->ruleResource->load($rule, $ruleId);
            if (!$rule->getId()) {
                $this->messageManager->addErrorMessage(__('This rule no longer exists.'));
                return $redirect->setPath('*/*/');
            }
        }

        if (isset($data['store_ids']) && is_array($data['store_ids'])) {
            $data['store_ids'] = implode(',', $data['store_ids']);
        }

        if (isset($data['source_attributes']) && is_array($data['source_attributes'])) {
            $data['source_attributes'] = implode(',', $data['source_attributes']);
        }

        if (isset($data['rule']['conditions'])) {
            $rule->loadPost(['conditions' => $data['rule']['conditions']]);
        }

        $rule->addData($data);

        try {
            $this->ruleResource->save($rule);
            $this->messageManager->addSuccessMessage(__('The extraction rule has been saved.'));

            if ($this->getRequest()->getParam('back')) {
                return $redirect->setPath('*/*/edit', ['rule_id' => $rule->getId()]);
            }
            return $redirect->setPath('*/*/');
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $redirect->setPath('*/*/edit', ['rule_id' => $ruleId]);
        }
    }
}
```

**Step 4: Create Delete controller**

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Controller\Adminhtml\ExtractionRule;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use MageOS\CatalogDataAI\Model\ExtractionRuleFactory;
use MageOS\CatalogDataAI\Model\ResourceModel\ExtractionRule as ExtractionRuleResource;

class Delete extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_CatalogDataAI::extraction_rules';

    public function __construct(
        Action\Context $context,
        private readonly ExtractionRuleFactory $ruleFactory,
        private readonly ExtractionRuleResource $ruleResource
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $ruleId = (int)$this->getRequest()->getParam('rule_id');
        $redirect = $this->resultRedirectFactory->create()->setPath('*/*/');

        if ($ruleId) {
            $rule = $this->ruleFactory->create();
            $this->ruleResource->load($rule, $ruleId);
            if ($rule->getId()) {
                try {
                    $this->ruleResource->delete($rule);
                    $this->messageManager->addSuccessMessage(__('The rule has been deleted.'));
                } catch (\Exception $e) {
                    $this->messageManager->addErrorMessage($e->getMessage());
                }
            }
        }

        return $redirect;
    }
}
```

**Step 5: Create DataProvider**

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\ExtractionRule;

use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use MageOS\CatalogDataAI\Model\ExtractionRuleFactory;
use MageOS\CatalogDataAI\Model\ResourceModel\ExtractionRule as ExtractionRuleResource;
use MageOS\CatalogDataAI\Model\ResourceModel\ExtractionRule\CollectionFactory;

class DataProvider extends AbstractDataProvider
{
    private array $loadedData = [];

    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly ExtractionRuleFactory $ruleFactory,
        private readonly ExtractionRuleResource $ruleResource,
        private readonly RequestInterface $request,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData(): array
    {
        if (!empty($this->loadedData)) {
            return $this->loadedData;
        }

        $ruleId = (int)$this->request->getParam('rule_id');
        if ($ruleId) {
            $rule = $this->ruleFactory->create();
            $this->ruleResource->load($rule, $ruleId);

            if ($rule->getId()) {
                $data = $rule->getData();
                if (isset($data['store_ids']) && is_string($data['store_ids'])) {
                    $data['store_ids'] = explode(',', $data['store_ids']);
                }
                if (isset($data['source_attributes']) && is_string($data['source_attributes'])) {
                    $data['source_attributes'] = explode(',', $data['source_attributes']);
                }
                $this->loadedData[$ruleId] = $data;
            }
        }

        return $this->loadedData;
    }
}
```

**Step 6: Create button blocks**

`Block/Adminhtml/ExtractionRule/Edit/SaveButton.php`:

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Block\Adminhtml\ExtractionRule\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class SaveButton implements ButtonProviderInterface
{
    public function getButtonData(): array
    {
        return [
            'label' => __('Save Rule'),
            'class' => 'save primary',
            'data_attribute' => [
                'mage-init' => ['button' => ['event' => 'save']],
                'form-role' => 'save',
            ],
            'sort_order' => 90,
        ];
    }
}
```

`Block/Adminhtml/ExtractionRule/Edit/DeleteButton.php`:

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Block\Adminhtml\ExtractionRule\Edit;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class DeleteButton implements ButtonProviderInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    public function getButtonData(): array
    {
        $ruleId = (int)$this->request->getParam('rule_id');
        if (!$ruleId) {
            return [];
        }

        return [
            'label' => __('Delete Rule'),
            'class' => 'delete',
            'on_click' => sprintf(
                "deleteConfirm('%s', '%s', {data: {}})",
                __('Are you sure you want to delete this rule?'),
                $this->urlBuilder->getUrl('*/*/delete', ['rule_id' => $ruleId])
            ),
            'sort_order' => 20,
        ];
    }
}
```

**Step 7: Create layout files**

`view/adminhtml/layout/catalogai_extractionrule_edit.xml` and `view/adminhtml/layout/catalogai_extractionrule_new.xml` — both identical:

```xml
<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <referenceContainer name="content">
            <uiComponent name="mageos_catalogai_extraction_rule_form"/>
        </referenceContainer>
    </body>
</page>
```

**Step 8: Create the form UI component**

`view/adminhtml/ui_component/mageos_catalogai_extraction_rule_form.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<form xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Ui:etc/ui_configuration.xsd">
    <argument name="data" xsi:type="array">
        <item name="js_config" xsi:type="array">
            <item name="provider" xsi:type="string">mageos_catalogai_extraction_rule_form.mageos_catalogai_extraction_rule_form_data_source</item>
        </item>
        <item name="label" xsi:type="string" translate="true">Extraction Rule</item>
        <item name="template" xsi:type="string">templates/form/collapsible</item>
    </argument>
    <settings>
        <buttons>
            <button name="back">
                <url path="*/*/"/>
                <class>back</class>
                <label translate="true">Back</label>
            </button>
            <button name="delete" class="MageOS\CatalogDataAI\Block\Adminhtml\ExtractionRule\Edit\DeleteButton"/>
            <button name="save" class="MageOS\CatalogDataAI\Block\Adminhtml\ExtractionRule\Edit\SaveButton"/>
        </buttons>
        <namespace>mageos_catalogai_extraction_rule_form</namespace>
        <dataScope>data</dataScope>
        <deps>
            <dep>mageos_catalogai_extraction_rule_form.mageos_catalogai_extraction_rule_form_data_source</dep>
        </deps>
    </settings>
    <dataSource name="mageos_catalogai_extraction_rule_form_data_source">
        <argument name="data" xsi:type="array">
            <item name="js_config" xsi:type="array">
                <item name="component" xsi:type="string">Magento_Ui/js/form/provider</item>
            </item>
        </argument>
        <settings>
            <submitUrl path="catalogai/extractionrule/save"/>
        </settings>
        <dataProvider class="MageOS\CatalogDataAI\Model\ExtractionRule\DataProvider" name="mageos_catalogai_extraction_rule_form_data_source">
            <settings>
                <requestFieldName>rule_id</requestFieldName>
                <primaryFieldName>rule_id</primaryFieldName>
            </settings>
        </dataProvider>
    </dataSource>
    <fieldset name="general">
        <settings>
            <label translate="true">Rule Information</label>
        </settings>
        <field name="rule_id" formElement="input">
            <settings>
                <dataType>text</dataType>
                <visible>false</visible>
            </settings>
        </field>
        <field name="name" formElement="input">
            <settings>
                <dataType>text</dataType>
                <label translate="true">Rule Name</label>
                <validation>
                    <rule name="required-entry" xsi:type="boolean">true</rule>
                </validation>
            </settings>
        </field>
        <field name="is_active" formElement="checkbox">
            <argument name="data" xsi:type="array">
                <item name="config" xsi:type="array">
                    <item name="default" xsi:type="number">1</item>
                </item>
            </argument>
            <settings>
                <dataType>boolean</dataType>
                <label translate="true">Active</label>
            </settings>
            <formElements>
                <checkbox>
                    <settings>
                        <valueMap>
                            <map name="false" xsi:type="number">0</map>
                            <map name="true" xsi:type="number">1</map>
                        </valueMap>
                        <prefer>toggle</prefer>
                    </settings>
                </checkbox>
            </formElements>
        </field>
        <field name="source_attributes" formElement="multiselect">
            <settings>
                <dataType>text</dataType>
                <label translate="true">Source Attributes</label>
                <notice translate="true">Attributes to read data from (e.g., description, short_description).</notice>
                <validation>
                    <rule name="required-entry" xsi:type="boolean">true</rule>
                </validation>
            </settings>
            <formElements>
                <multiselect>
                    <settings>
                        <options class="MageOS\CatalogDataAI\Model\Config\Source\EnrichableAttributes"/>
                    </settings>
                </multiselect>
            </formElements>
        </field>
        <field name="target_attribute" formElement="input">
            <settings>
                <dataType>text</dataType>
                <label translate="true">Target Attribute Code</label>
                <notice translate="true">The attribute to fill (e.g., color, size, brand). Supports any frontend input type.</notice>
                <validation>
                    <rule name="required-entry" xsi:type="boolean">true</rule>
                </validation>
            </settings>
        </field>
        <field name="store_ids" formElement="multiselect">
            <settings>
                <dataType>text</dataType>
                <label translate="true">Store Views</label>
            </settings>
            <formElements>
                <multiselect>
                    <settings>
                        <options class="Magento\Store\Ui\Component\Listing\Column\Store\Options"/>
                    </settings>
                </multiselect>
            </formElements>
        </field>
        <field name="priority" formElement="input">
            <settings>
                <dataType>number</dataType>
                <label translate="true">Priority</label>
                <notice translate="true">Higher value = higher priority.</notice>
            </settings>
        </field>
    </fieldset>
    <fieldset name="extraction_fieldset">
        <settings>
            <label translate="true">Extraction</label>
        </settings>
        <field name="extraction_prompt" formElement="textarea">
            <settings>
                <dataType>text</dataType>
                <label translate="true">Extraction Prompt</label>
                <notice translate="true">Tell the AI what to extract. Use {{attribute_code}} placeholders. Example: "Extract the primary color from this product description."</notice>
                <validation>
                    <rule name="required-entry" xsi:type="boolean">true</rule>
                </validation>
            </settings>
        </field>
        <field name="value_mapping" formElement="textarea">
            <settings>
                <dataType>text</dataType>
                <label translate="true">Value Mapping (JSON)</label>
                <notice translate="true">For dropdown/multiselect targets: map AI output to option IDs. Example: {"Red": "42", "Blue": "43", "Green": "44"}</notice>
            </settings>
        </field>
    </fieldset>
</form>
```

**Step 9: Commit**

```bash
git add Controller/Adminhtml/ExtractionRule/Edit.php \
    Controller/Adminhtml/ExtractionRule/NewAction.php \
    Controller/Adminhtml/ExtractionRule/Save.php \
    Controller/Adminhtml/ExtractionRule/Delete.php \
    Model/ExtractionRule/DataProvider.php \
    Block/Adminhtml/ExtractionRule/Edit/SaveButton.php \
    Block/Adminhtml/ExtractionRule/Edit/DeleteButton.php \
    view/adminhtml/layout/catalogai_extractionrule_edit.xml \
    view/adminhtml/layout/catalogai_extractionrule_new.xml \
    view/adminhtml/ui_component/mageos_catalogai_extraction_rule_form.xml
git commit -m "feat: add extraction rule edit form with CRUD controllers (#7)"
```

---

### Task 6: Add MassExtract action to product grid

**Files:**
- Create: `Controller/Adminhtml/Product/MassExtract.php`
- Modify: `view/adminhtml/ui_component/product_listing.xml`

**Step 1: Create the mass extract controller**

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Controller\Adminhtml\Product;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\Component\MassAction\Filter;
use MageOS\CatalogDataAI\Model\Config;
use MageOS\CatalogDataAI\Model\Product\Publisher;

class MassExtract extends Action implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly Config $config,
        private readonly Publisher $publisher,
        private readonly StoreManagerInterface $storeManager,
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $scheduled = 0;

        if ($this->config->isEnabled()) {
            $storeId = (int)$this->storeManager->getStore()->getId();
            foreach ($collection->getItems() as $product) {
                $this->publisher->execute($product->getId(), false, $storeId);
                $scheduled++;
            }
            $this->messageManager->addSuccessMessage(
                __('A total of %1 product(s) are scheduled for attribute extraction.', $scheduled)
            );
        } else {
            $this->messageManager->addErrorMessage(
                __('Data enrichment is disabled. Please enable it in the configuration.')
            );
        }

        return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('catalog/*/index');
    }
}
```

**Step 2: Add the mass action to the product listing**

In `view/adminhtml/ui_component/product_listing.xml`, add after the existing `enrich_safe` action:

```xml
            <action name="extract_attributes">
                <settings>
                    <confirm>
                        <message translate="true">Run AI attribute extraction on selected products? Results will be held for review.</message>
                        <title translate="true">Extract Attributes</title>
                    </confirm>
                    <url path="catalogai/product/massExtract"/>
                    <type>extract_attributes</type>
                    <label translate="true">AI Extract Attributes</label>
                </settings>
            </action>
```

**Step 3: Commit**

```bash
git add Controller/Adminhtml/Product/MassExtract.php view/adminhtml/ui_component/product_listing.xml
git commit -m "feat: add MassExtract action to product grid (#7)"
```

---

### Task 7: Smoke test

**Step 1: Run Magento setup commands (inside Warden)**

```bash
warden env exec php-fpm bin/magento setup:upgrade
warden env exec php-fpm bin/magento setup:di:compile
```

**Step 2: Verify the DB table**

Check that `mageos_catalogai_extraction_rule` table was created.

**Step 3: Verify admin pages**

1. Navigate to **Catalog > AI Extraction Rules** — grid should load
2. Click **Add New Extraction Rule** — form should render with source/target/prompt/mapping fields
3. Create a test rule, save — should persist
4. Navigate to product grid — "AI Extract Attributes" mass action should appear

**Step 4: Run all tests**

```bash
vendor/bin/phpunit Test/
```

**Step 5: Final commit (if adjustments needed)**

```bash
git add -A
git commit -m "fix: adjustments from smoke testing Phase 5"
```

---

## Summary of Files Changed/Created

| Action | File |
|--------|------|
| **Schema** | |
| Modify | `etc/db_schema.xml` |
| Modify | `etc/db_schema_whitelist.json` |
| **Data Layer** | |
| Create | `Api/Data/ExtractionRuleInterface.php` |
| Create | `Model/ExtractionRule.php` |
| Create | `Model/ResourceModel/ExtractionRule.php` |
| Create | `Model/ResourceModel/ExtractionRule/Collection.php` |
| Create | `Model/ResourceModel/ExtractionRule/Grid/Collection.php` |
| **Core Service** | |
| Create | `Model/Product/AttributeExtractor.php` |
| Create | `Test/Unit/Model/Product/AttributeExtractorTest.php` |
| **Admin Grid** | |
| Create | `Controller/Adminhtml/ExtractionRule/Index.php` |
| Create | `Ui/Component/Listing/Column/ExtractionRuleActions.php` |
| Create | `view/adminhtml/layout/catalogai_extractionrule_index.xml` |
| Create | `view/adminhtml/ui_component/mageos_catalogai_extraction_rule_listing.xml` |
| Modify | `etc/adminhtml/menu.xml` |
| Modify | `etc/adminhtml/di.xml` |
| Modify | `etc/acl.xml` |
| **Admin Form** | |
| Create | `Controller/Adminhtml/ExtractionRule/Edit.php` |
| Create | `Controller/Adminhtml/ExtractionRule/NewAction.php` |
| Create | `Controller/Adminhtml/ExtractionRule/Save.php` |
| Create | `Controller/Adminhtml/ExtractionRule/Delete.php` |
| Create | `Model/ExtractionRule/DataProvider.php` |
| Create | `Block/Adminhtml/ExtractionRule/Edit/SaveButton.php` |
| Create | `Block/Adminhtml/ExtractionRule/Edit/DeleteButton.php` |
| Create | `view/adminhtml/layout/catalogai_extractionrule_edit.xml` |
| Create | `view/adminhtml/layout/catalogai_extractionrule_new.xml` |
| Create | `view/adminhtml/ui_component/mageos_catalogai_extraction_rule_form.xml` |
| **Mass Action** | |
| Create | `Controller/Adminhtml/Product/MassExtract.php` |
| Modify | `view/adminhtml/ui_component/product_listing.xml` |
