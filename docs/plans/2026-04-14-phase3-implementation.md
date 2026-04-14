# Phase 3: Prompt Rules Engine + Config Migration — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Move all module config from `catalog_ai/` to `ai_integration/catalog_data_ai/` under the Services tab (coordinating with the translation module), and add a prompt rules engine that lets admins define different prompts per attribute based on product conditions, store scope, and priority.

**Architecture:** Config migration is a straightforward path rename across system.xml, config.xml, Config.php constants, di.xml, and a data patch. The rules engine uses Magento's `Rule` framework for conditions (same as catalog price rules), stores rules in a new `mageos_catalogai_prompt_rule` DB table, and provides an admin grid/form for CRUD. A `PromptResolver` service is injected into the Enricher to resolve the highest-priority matching rule for a given product/attribute/store, falling back to the default dynamic rows config from Phase 1. A preview/test controller lets admins test a rule against a specific SKU.

**Tech Stack:** PHP 8.1+, Magento 2.4.x, Magento Rule Framework (`Magento\Rule`), UI Components (admin grid/form), PHPUnit 9.5

---

## Part A: Config Migration (#43)

### Task 1: Update system.xml — move to ai_integration section

**Files:**
- Modify: `etc/adminhtml/system.xml`

**Step 1: Replace the entire file**

Move the section from `catalog_ai` (under Catalog tab) to `ai_integration` (under Services tab). Change the section ID, tab, and config path prefix. The group IDs and field IDs stay the same — only the section wrapper changes.

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Config:etc/system_file.xsd">
    <system>
        <tab id="ai_services" translate="label" sortOrder="500">
            <label>AI Services</label>
        </tab>
        <section id="ai_integration_enrichment" translate="label" type="text" sortOrder="10" showInDefault="1">
            <class>separator-top</class>
            <label>Data Enrichment</label>
            <tab>ai_services</tab>
            <resource>MageOS_CatalogDataAI::config</resource>
            <group id="settings" translate="label" sortOrder="10" showInDefault="1">
                <label>Settings</label>
                <field id="active" translate="label" type="select" sortOrder="10" showInDefault="1" canRestore="1">
                    <label>Enabled</label>
                    <source_model>Magento\Config\Model\Config\Source\Yesno</source_model>
                </field>
                <field id="async" translate="label" type="select" sortOrder="20" showInDefault="1" canRestore="1">
                    <label>Asynchronous enrichment</label>
                    <source_model>Magento\Config\Model\Config\Source\Yesno</source_model>
                </field>
                <field id="openai_organization_id" translate="label comment" sortOrder="25" showInDefault="1" canRestore="1">
                    <label>OpenAI Organization ID</label>
                </field>
                <field id="openai_key" translate="label comment" type="obscure" sortOrder="30" showInDefault="1" canRestore="1">
                    <label>OpenAI API key</label>
                    <backend_model>Magento\Config\Model\Config\Backend\Encrypted</backend_model>
                </field>
                <field id="openai_project_id" translate="label" sortOrder="35" showInDefault="1" canRestore="1">
                    <label>OpenAI Project ID</label>
                </field>
                <field id="openai_model" translate="label comment" type="select" sortOrder="40" showInDefault="1" canRestore="1">
                    <label>OpenAI API Model</label>
                    <source_model>MageOS\CatalogDataAI\Model\Config\Source\OpenAIModel</source_model>
                    <comment>Insert API key and organization ID and save to see options. Refer to the documentation to understand which model you should select: https://platform.openai.com/docs/models/overview</comment>
                </field>
                <field id="openai_max_tokens" translate="label comment" type="text" sortOrder="45" showInDefault="1" canRestore="1">
                    <label>OpenAI API Max Tokens</label>
                </field>
            </group>
            <group id="product" translate="label comment" sortOrder="20" showInDefault="1" showInStore="1">
                <label>Default Product Prompts</label>
                <comment>
                    <![CDATA[Default prompts used when no matching prompt rule exists. Use {{product_attribute_code}} (e.g. {{name}} for product name) as a placeholder.
                    <br />
                    To enrich a meta attribute, the corresponding default value (mask) must be removed, see <a href="https://experienceleague.adobe.com/docs/commerce-admin/catalog/products/product-workspace.html#edit-the-placeholder-value">Edit the placeholder value</a>]]>
                </comment>
                <field id="attribute_prompts" translate="label" sortOrder="10" showInDefault="1" showInStore="1">
                    <label>Attribute Prompts</label>
                    <frontend_model>MageOS\CatalogDataAI\Block\Adminhtml\Form\Field\ProductAttributes</frontend_model>
                    <backend_model>Magento\Config\Model\Config\Backend\Serialized\ArraySerialized</backend_model>
                </field>
            </group>
            <group id="advanced" translate="label" sortOrder="30" showInDefault="1">
                <label>Advanced Settings</label>
                <field id="system_prompt" translate="label comment" type="text" sortOrder="10" showInDefault="1" canRestore="1">
                    <label>System Prompt</label>
                    <comment><![CDATA[Instructions sent to the AI model as the developer/system role. Defines the AI's behavior.]]></comment>
                </field>
                <field id="temperature" translate="label comment" type="text" sortOrder="20" showInDefault="1" canRestore="1">
                    <label>Temperature</label>
                    <comment><![CDATA[Controls randomness. 0.0 = deterministic, 1.0 = creative. Default: 0]]></comment>
                </field>
                <field id="frequency_penalty" translate="label comment" type="text" sortOrder="30" showInDefault="1" canRestore="1">
                    <label>Frequency Penalty</label>
                    <comment><![CDATA[Reduces repetition of token sequences. Range: -2.0 to 2.0. Default: 0]]></comment>
                </field>
                <field id="presence_penalty" translate="label comment" type="text" sortOrder="40" showInDefault="1" canRestore="1">
                    <label>Presence Penalty</label>
                    <comment><![CDATA[Encourages new topics. Range: -2.0 to 2.0. Default: 0]]></comment>
                </field>
            </group>
        </section>
    </system>
</config>
```

Key changes:
- New `ai_services` tab
- Section ID: `catalog_ai` → `ai_integration_enrichment`
- Tab: `catalog` → `ai_services`
- Resource: `Magento_Catalog::config_catalog_ai` → `MageOS_CatalogDataAI::config`
- Product group label: "Product Fields Auto-Generation" → "Default Product Prompts" (clarifies relationship with rules engine)

**Step 2: Commit**

```bash
git add etc/adminhtml/system.xml
git commit -m "feat: move config to AI Services tab (#43)"
```

---

### Task 2: Update config.xml defaults to new paths

**Files:**
- Modify: `etc/config.xml`

**Step 1: Replace the entire file**

Change the root config path from `<catalog_ai>` to `<ai_integration_enrichment>`:

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Store:etc/config.xsd">
    <default>
        <ai_integration_enrichment>
            <settings>
                <openai_organization_id />
                <openai_key backend_model="Magento\Config\Model\Config\Backend\Encrypted" />
                <openai_project_id />
                <openai_model>gpt-4o</openai_model>
                <openai_max_tokens>1000</openai_max_tokens>
            </settings>
            <product>
                <attribute_prompts>
                    <short_description>
                        <attribute>short_description</attribute>
                        <prompt>write a very short product description for {{name}} to highlight reasoning for purchase, under 100 words</prompt>
                        <enabled>1</enabled>
                    </short_description>
                    <description>
                        <attribute>description</attribute>
                        <prompt>write a detailed product description for {{name}} with features in bullet list, under 1000 words</prompt>
                        <enabled>1</enabled>
                    </description>
                    <meta_title>
                        <attribute>meta_title</attribute>
                        <prompt>write a concise SEO meta title for {{name}}, under 60 characters</prompt>
                        <enabled>1</enabled>
                    </meta_title>
                    <meta_keyword>
                        <attribute>meta_keyword</attribute>
                        <prompt>generate comma-separated SEO keywords for {{name}}, max 10 keywords</prompt>
                        <enabled>1</enabled>
                    </meta_keyword>
                    <meta_description>
                        <attribute>meta_description</attribute>
                        <prompt>write an SEO meta description for {{name}}, under 160 characters</prompt>
                        <enabled>1</enabled>
                    </meta_description>
                </attribute_prompts>
            </product>
            <advanced>
                <system_prompt>Be a content generator, just reply with the content, skip all introductions.</system_prompt>
                <temperature>0</temperature>
                <frequency_penalty>0</frequency_penalty>
                <presence_penalty>0</presence_penalty>
            </advanced>
        </ai_integration_enrichment>
    </default>
</config>
```

**Step 2: Commit**

```bash
git add etc/config.xml
git commit -m "feat: update config.xml defaults to new ai_integration_enrichment path (#43)"
```

---

### Task 3: Update Config.php constants and ACL

**Files:**
- Modify: `Model/Config.php`
- Modify: `etc/acl.xml`
- Modify: `etc/di.xml`

**Step 1: Update all XML_PATH constants in Config.php**

Replace all `catalog_ai/` prefixes with `ai_integration_enrichment/`:

```php
    public const XML_PATH_ENRICH_ENABLED = 'ai_integration_enrichment/settings/active';
    public const XML_PATH_USE_ASYNC = 'ai_integration_enrichment/settings/async';
    public const XML_PATH_OPENAI_ORGANIZATION_ID = 'ai_integration_enrichment/settings/openai_organization_id';
    public const XML_PATH_OPENAI_API_KEY = 'ai_integration_enrichment/settings/openai_key';
    public const XML_PATH_OPENAI_PROJECT_ID = 'ai_integration_enrichment/settings/openai_project_id';
    public const XML_PATH_OPENAI_API_MODEL = 'ai_integration_enrichment/settings/openai_model';
    public const XML_PATH_OPENAI_API_MAX_TOKENS = 'ai_integration_enrichment/settings/openai_max_tokens';
    public const XML_PATH_OPENAI_API_ADVANCED_SYSTEM_PROMPT = 'ai_integration_enrichment/advanced/system_prompt';
    public const XML_PATH_OPENAI_API_ADVANCED_TEMPERATURE = 'ai_integration_enrichment/advanced/temperature';
    public const XML_PATH_OPENAI_API_ADVANCED_FREQUENCY_PENALTY = 'ai_integration_enrichment/advanced/frequency_penalty';
    public const XML_PATH_OPENAI_API_ADVANCED_PRESENCE_PENALTY = 'ai_integration_enrichment/advanced/presence_penalty';
```

Also update the hardcoded path in `getEnrichableAttributes()`:

```php
        $rows = $this->scopeConfig->getValue(
            'ai_integration_enrichment/product/attribute_prompts'
        );
```

**Step 2: Update acl.xml**

Replace the entire file — use module's own ACL resource instead of piggy-backing on Magento_Catalog:

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:Acl/etc/acl.xsd">
    <acl>
        <resources>
            <resource id="Magento_Backend::admin">
                <resource id="Magento_Backend::stores">
                    <resource id="Magento_Backend::stores_settings">
                        <resource id="Magento_Config::config">
                            <resource id="MageOS_CatalogDataAI::config" title="AI Data Enrichment" translate="title" />
                        </resource>
                    </resource>
                </resource>
                <resource id="MageOS_CatalogDataAI::prompt_rules" title="AI Prompt Rules" translate="title" sortOrder="50" />
            </resource>
        </resources>
    </acl>
</config>
```

**Step 3: Update di.xml sensitive config paths**

Replace `etc/di.xml`:

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">
    <type name="Magento\Config\Model\Config\TypePool">
        <arguments>
            <argument name="sensitive" xsi:type="array">
                <item name="ai_integration_enrichment/settings/openai_organization_id" xsi:type="string">1</item>
                <item name="ai_integration_enrichment/settings/openai_key" xsi:type="string">1</item>
                <item name="ai_integration_enrichment/settings/openai_project_id" xsi:type="string">1</item>
            </argument>
        </arguments>
    </type>
</config>
```

**Step 4: Update tests**

In `Test/Unit/Model/ConfigTest.php`, update all `willReturnMap` entries to use the new path prefix. Every occurrence of `'catalog_ai/product/attribute_prompts'` becomes `'ai_integration_enrichment/product/attribute_prompts'`.

**Step 5: Commit**

```bash
git add Model/Config.php etc/acl.xml etc/di.xml Test/Unit/Model/ConfigTest.php
git commit -m "feat: update Config paths, ACL, and sensitive config to new section (#43)"
```

---

### Task 4: Config migration data patch

**Files:**
- Create: `Setup/Patch/Data/MigrateConfigToAiIntegration.php`

**Step 1: Create the data patch**

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Setup\Patch\Data;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class MigrateConfigToAiIntegration implements DataPatchInterface
{
    private const PATH_MAP = [
        'catalog_ai/settings/active' => 'ai_integration_enrichment/settings/active',
        'catalog_ai/settings/async' => 'ai_integration_enrichment/settings/async',
        'catalog_ai/settings/openai_organization_id' => 'ai_integration_enrichment/settings/openai_organization_id',
        'catalog_ai/settings/openai_key' => 'ai_integration_enrichment/settings/openai_key',
        'catalog_ai/settings/openai_project_id' => 'ai_integration_enrichment/settings/openai_project_id',
        'catalog_ai/settings/openai_model' => 'ai_integration_enrichment/settings/openai_model',
        'catalog_ai/settings/openai_max_tokens' => 'ai_integration_enrichment/settings/openai_max_tokens',
        'catalog_ai/product/attribute_prompts' => 'ai_integration_enrichment/product/attribute_prompts',
        'catalog_ai/advanced/system_prompt' => 'ai_integration_enrichment/advanced/system_prompt',
        'catalog_ai/advanced/temperature' => 'ai_integration_enrichment/advanced/temperature',
        'catalog_ai/advanced/frequency_penalty' => 'ai_integration_enrichment/advanced/frequency_penalty',
        'catalog_ai/advanced/presence_penalty' => 'ai_integration_enrichment/advanced/presence_penalty',
    ];

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly WriterInterface $configWriter
    ) {
    }

    public function apply(): self
    {
        foreach (self::PATH_MAP as $oldPath => $newPath) {
            $value = $this->scopeConfig->getValue($oldPath);
            if ($value !== null) {
                $this->configWriter->save($newPath, $value);
                $this->configWriter->delete($oldPath);
            }
        }

        return $this;
    }

    public static function getDependencies(): array
    {
        return [MigrateProductPromptsToDynamicRows::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
```

**Step 2: Commit**

```bash
git add Setup/Patch/Data/MigrateConfigToAiIntegration.php
git commit -m "feat: add data patch to migrate config from catalog_ai to ai_integration_enrichment (#43)"
```

---

## Part B: Prompt Rules Engine (#32)

### Task 5: Add prompt_rule DB table schema

**Files:**
- Modify: `etc/db_schema.xml`
- Modify: `etc/db_schema_whitelist.json`

**Step 1: Add the rules table to db_schema.xml**

Add this table after the existing `mageos_catalogai_enrichment_log` table:

```xml
    <table name="mageos_catalogai_prompt_rule" resource="default" engine="innodb"
           comment="AI Prompt Rules">
        <column xsi:type="int" name="rule_id" unsigned="true" nullable="false" identity="true"
                comment="Rule ID"/>
        <column xsi:type="varchar" name="name" nullable="false" length="255"
                comment="Rule Name"/>
        <column xsi:type="varchar" name="attribute_code" nullable="false" length="255"
                comment="Target Attribute Code"/>
        <column xsi:type="text" name="store_ids" nullable="false"
                comment="Store IDs (comma-separated, 0 = all)"/>
        <column xsi:type="text" name="conditions_serialized" nullable="true"
                comment="Serialized Product Conditions"/>
        <column xsi:type="text" name="prompt" nullable="false"
                comment="Prompt Template"/>
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
        <index referenceId="MAGEOS_CATALOGAI_PROMPT_RULE_ATTR_CODE" indexType="btree">
            <column name="attribute_code"/>
        </index>
        <index referenceId="MAGEOS_CATALOGAI_PROMPT_RULE_IS_ACTIVE" indexType="btree">
            <column name="is_active"/>
        </index>
    </table>
```

**Step 2: Update the whitelist JSON**

Add the new table to `etc/db_schema_whitelist.json`:

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
    },
    "mageos_catalogai_prompt_rule": {
        "column": {
            "rule_id": true,
            "name": true,
            "attribute_code": true,
            "store_ids": true,
            "conditions_serialized": true,
            "prompt": true,
            "priority": true,
            "is_active": true,
            "created_at": true,
            "updated_at": true
        },
        "constraint": {
            "PRIMARY": true
        },
        "index": {
            "MAGEOS_CATALOGAI_PROMPT_RULE_ATTR_CODE": true,
            "MAGEOS_CATALOGAI_PROMPT_RULE_IS_ACTIVE": true
        }
    }
}
```

**Step 3: Commit**

```bash
git add etc/db_schema.xml etc/db_schema_whitelist.json
git commit -m "feat: add prompt_rule DB table schema (#32)"
```

---

### Task 6: Create PromptRule model, resource model, and collection

**Files:**
- Create: `Model/PromptRule.php`
- Create: `Model/ResourceModel/PromptRule.php`
- Create: `Model/ResourceModel/PromptRule/Collection.php`
- Create: `Api/Data/PromptRuleInterface.php`

**Step 1: Create the interface**

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Api\Data;

interface PromptRuleInterface
{
    public const RULE_ID = 'rule_id';
    public const NAME = 'name';
    public const ATTRIBUTE_CODE = 'attribute_code';
    public const STORE_IDS = 'store_ids';
    public const CONDITIONS_SERIALIZED = 'conditions_serialized';
    public const PROMPT = 'prompt';
    public const PRIORITY = 'priority';
    public const IS_ACTIVE = 'is_active';

    public function getRuleId(): ?int;
    public function getName(): string;
    public function getAttributeCode(): string;
    public function getStoreIds(): string;
    public function getConditionsSerialized(): ?string;
    public function getPrompt(): string;
    public function getPriority(): int;
    public function getIsActive(): bool;
}
```

**Step 2: Create the model**

The model extends `Magento\Rule\Model\AbstractModel` to get the conditions framework for free:

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model;

use Magento\CatalogRule\Model\Rule\Condition\Combine;
use Magento\Rule\Model\AbstractModel;
use MageOS\CatalogDataAI\Api\Data\PromptRuleInterface;
use MageOS\CatalogDataAI\Model\ResourceModel\PromptRule as PromptRuleResource;

class PromptRule extends AbstractModel implements PromptRuleInterface
{
    protected $_eventPrefix = 'mageos_catalogai_prompt_rule';

    protected function _construct(): void
    {
        $this->_init(PromptRuleResource::class);
    }

    public function getConditionsInstance(): \Magento\Rule\Model\Condition\Combine
    {
        return $this->_conditionFactory->create(Combine::class);
    }

    public function getActionsInstance(): \Magento\Rule\Model\Action\Collection
    {
        return $this->_actionFactory->create(\Magento\Rule\Model\Action\Collection::class);
    }

    public function getRuleId(): ?int
    {
        return $this->getData(self::RULE_ID) ? (int)$this->getData(self::RULE_ID) : null;
    }

    public function getName(): string
    {
        return (string)$this->getData(self::NAME);
    }

    public function getAttributeCode(): string
    {
        return (string)$this->getData(self::ATTRIBUTE_CODE);
    }

    public function getStoreIds(): string
    {
        return (string)$this->getData(self::STORE_IDS);
    }

    public function getConditionsSerialized(): ?string
    {
        return $this->getData(self::CONDITIONS_SERIALIZED);
    }

    public function getPrompt(): string
    {
        return (string)$this->getData(self::PROMPT);
    }

    public function getPriority(): int
    {
        return (int)$this->getData(self::PRIORITY);
    }

    public function getIsActive(): bool
    {
        return (bool)$this->getData(self::IS_ACTIVE);
    }

    public function matchesProduct(\Magento\Catalog\Model\Product $product): bool
    {
        return $this->getConditions()->validate($product);
    }

    public function matchesStore(int $storeId): bool
    {
        $storeIds = $this->getStoreIds();
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

class PromptRule extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('mageos_catalogai_prompt_rule', 'rule_id');
    }
}
```

**Step 4: Create the collection**

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\ResourceModel\PromptRule;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use MageOS\CatalogDataAI\Model\PromptRule;
use MageOS\CatalogDataAI\Model\ResourceModel\PromptRule as PromptRuleResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'rule_id';

    protected function _construct(): void
    {
        $this->_init(PromptRule::class, PromptRuleResource::class);
    }
}
```

**Step 5: Commit**

```bash
git add Api/Data/PromptRuleInterface.php Model/PromptRule.php Model/ResourceModel/PromptRule.php Model/ResourceModel/PromptRule/Collection.php
git commit -m "feat: add PromptRule model with conditions support (#32)"
```

---

### Task 7: Create PromptResolver service

**Files:**
- Create: `Model/Product/PromptResolver.php`
- Test: `Test/Unit/Model/Product/PromptResolverTest.php`

This is the core logic — given a product, attribute, and store, find the highest-priority matching rule. Fall back to the default config prompt if no rule matches.

**Step 1: Write the test**

```php
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
```

**Step 2: Create the service**

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\Product;

use Magento\Catalog\Model\Product;
use MageOS\CatalogDataAI\Model\Config;
use MageOS\CatalogDataAI\Model\PromptRule;
use MageOS\CatalogDataAI\Model\ResourceModel\PromptRule\CollectionFactory;

class PromptResolver
{
    public function __construct(
        private readonly CollectionFactory $ruleCollectionFactory,
        private readonly Config $config
    ) {
    }

    public function resolve(string $attributeCode, Product $product): ?string
    {
        $storeId = (int)$product->getStoreId();

        $collection = $this->ruleCollectionFactory->create();
        $collection->addFieldToFilter('attribute_code', $attributeCode);
        $collection->addFieldToFilter('is_active', 1);
        $collection->setOrder('priority', 'DESC');

        /** @var PromptRule $rule */
        foreach ($collection as $rule) {
            if ($rule->matchesStore($storeId) && $rule->matchesProduct($product)) {
                return $rule->getPrompt();
            }
        }

        return $this->config->getProductPrompt($attributeCode);
    }
}
```

**Step 3: Commit**

```bash
git add Model/Product/PromptResolver.php Test/Unit/Model/Product/PromptResolverTest.php
git commit -m "feat: add PromptResolver service with priority-based rule matching (#32)"
```

---

### Task 8: Wire PromptResolver into Enricher

**Files:**
- Modify: `Model/Product/Enricher.php`
- Modify: `Test/Unit/Model/Product/EnricherTest.php`

**Step 1: Add PromptResolver to Enricher**

In `Model/Product/Enricher.php`:

Add import:
```php
use MageOS\CatalogDataAI\Model\Product\PromptResolver;
```

Update constructor:
```php
    public function __construct(
        private readonly Factory $clientFactory,
        private readonly Config $config,
        private readonly EnrichmentLogger $enrichmentLogger,
        private readonly PromptResolver $promptResolver
    ) {
    }
```

In `enrichAttribute()`, replace the line:
```php
        if ($prompt = $this->config->getProductPrompt($attributeCode)) {
```
with:
```php
        if ($prompt = $this->promptResolver->resolve($attributeCode, $product)) {
```

**Step 2: Update the EnricherTest**

Add `PromptResolver` mock to both test methods' setup. In the constructor calls, add the fourth parameter:

```php
        $promptResolver = $this->createMock(PromptResolver::class);
        $enricher = new Enricher($factory, $config, $logger, $promptResolver);
```

Add the import:
```php
use MageOS\CatalogDataAI\Model\Product\PromptResolver;
```

**Step 3: Commit**

```bash
git add Model/Product/Enricher.php Test/Unit/Model/Product/EnricherTest.php
git commit -m "feat: Enricher uses PromptResolver for rule-based prompt selection (#32)"
```

---

### Task 9: Admin grid — controllers, layout, and UI component

**Files:**
- Create: `Controller/Adminhtml/PromptRule/Index.php`
- Create: `view/adminhtml/layout/catalogai_promptrule_index.xml`
- Create: `view/adminhtml/ui_component/mageos_catalogai_prompt_rule_listing.xml`
- Modify: `etc/adminhtml/routes.xml` (already exists)

**Step 1: Create the Index controller**

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Controller\Adminhtml\PromptRule;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_CatalogDataAI::prompt_rules';

    public function __construct(
        Action\Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): Page
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('MageOS_CatalogDataAI::prompt_rules');
        $resultPage->getConfig()->getTitle()->prepend(__('AI Prompt Rules'));
        return $resultPage;
    }
}
```

**Step 2: Create the layout XML**

Create `view/adminhtml/layout/catalogai_promptrule_index.xml`:

```xml
<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <referenceContainer name="content">
            <uiComponent name="mageos_catalogai_prompt_rule_listing"/>
        </referenceContainer>
    </body>
</page>
```

**Step 3: Create the grid UI component**

Create `view/adminhtml/ui_component/mageos_catalogai_prompt_rule_listing.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<listing xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Ui:etc/ui_configuration.xsd">
    <argument name="data" xsi:type="array">
        <item name="js_config" xsi:type="array">
            <item name="provider" xsi:type="string">mageos_catalogai_prompt_rule_listing.mageos_catalogai_prompt_rule_listing_data_source</item>
        </item>
    </argument>
    <settings>
        <buttons>
            <button name="add">
                <url path="catalogai/promptrule/new"/>
                <class>primary</class>
                <label translate="true">Add New Rule</label>
            </button>
        </buttons>
        <spinner>mageos_catalogai_prompt_rule_columns</spinner>
        <deps>
            <dep>mageos_catalogai_prompt_rule_listing.mageos_catalogai_prompt_rule_listing_data_source</dep>
        </deps>
    </settings>
    <dataSource name="mageos_catalogai_prompt_rule_listing_data_source" component="Magento_Ui/js/grid/provider">
        <settings>
            <updateUrl path="mui/index/render"/>
        </settings>
        <dataProvider class="Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider" name="mageos_catalogai_prompt_rule_listing_data_source">
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
        <massaction name="listing_massaction">
            <action name="delete">
                <settings>
                    <confirm>
                        <message translate="true">Delete selected rules?</message>
                        <title translate="true">Delete</title>
                    </confirm>
                    <url path="catalogai/promptrule/massDelete"/>
                    <type>delete</type>
                    <label translate="true">Delete</label>
                </settings>
            </action>
        </massaction>
    </listingToolbar>
    <columns name="mageos_catalogai_prompt_rule_columns">
        <selectionsColumn name="ids">
            <settings>
                <indexField>rule_id</indexField>
            </settings>
        </selectionsColumn>
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
        <column name="attribute_code">
            <settings>
                <filter>text</filter>
                <label translate="true">Attribute</label>
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
        <column name="updated_at" class="Magento\Ui\Component\Listing\Columns\Date" component="Magento_Ui/js/grid/columns/date">
            <settings>
                <filter>dateRange</filter>
                <dataType>date</dataType>
                <label translate="true">Updated</label>
            </settings>
        </column>
        <actionsColumn name="actions" class="MageOS\CatalogDataAI\Ui\Component\Listing\Column\PromptRuleActions">
            <settings>
                <indexField>rule_id</indexField>
            </settings>
        </actionsColumn>
    </columns>
</listing>
```

**Step 4: Create the actions column class**

Create `Ui/Component/Listing/Column/PromptRuleActions.php`:

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class PromptRuleActions extends Column
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
                                'catalogai/promptrule/edit',
                                ['rule_id' => $item['rule_id']]
                            ),
                            'label' => __('Edit'),
                        ],
                        'delete' => [
                            'href' => $this->urlBuilder->getUrl(
                                'catalogai/promptrule/delete',
                                ['rule_id' => $item['rule_id']]
                            ),
                            'label' => __('Delete'),
                            'confirm' => [
                                'title' => __('Delete Rule'),
                                'message' => __('Are you sure you want to delete this rule?'),
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

**Step 5: Register the grid data source in adminhtml di.xml**

In `etc/adminhtml/di.xml`, add the collection provider for the grid data source (append to existing file, inside the `<config>` tag):

```xml
    <type name="Magento\Framework\View\Element\UiComponent\DataProvider\CollectionFactory">
        <arguments>
            <argument name="collections" xsi:type="array">
                <item name="mageos_catalogai_prompt_rule_listing_data_source" xsi:type="string">MageOS\CatalogDataAI\Model\ResourceModel\PromptRule\Grid\Collection</item>
            </argument>
        </arguments>
    </type>
```

**Step 6: Create the grid collection (search result compatible)**

Create `Model/ResourceModel/PromptRule/Grid/Collection.php`:

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\ResourceModel\PromptRule\Grid;

use Magento\Framework\Api\Search\AggregationInterface;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use MageOS\CatalogDataAI\Model\ResourceModel\PromptRule\Collection as BaseCollection;
use Psr\Log\LoggerInterface;

class Collection extends BaseCollection implements SearchResultInterface
{
    private AggregationInterface $aggregations;

    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        string $mainTable = 'mageos_catalogai_prompt_rule',
        string $resourceModel = \MageOS\CatalogDataAI\Model\ResourceModel\PromptRule::class,
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

**Step 7: Add admin menu**

Create `etc/adminhtml/menu.xml`:

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Backend:etc/menu.xsd">
    <menu>
        <add id="MageOS_CatalogDataAI::prompt_rules"
             title="AI Prompt Rules"
             module="MageOS_CatalogDataAI"
             sortOrder="90"
             parent="Magento_Catalog::catalog"
             action="catalogai/promptrule"
             resource="MageOS_CatalogDataAI::prompt_rules"/>
    </menu>
</config>
```

**Step 8: Commit**

```bash
git add Controller/Adminhtml/PromptRule/Index.php \
    view/adminhtml/layout/catalogai_promptrule_index.xml \
    view/adminhtml/ui_component/mageos_catalogai_prompt_rule_listing.xml \
    Ui/Component/Listing/Column/PromptRuleActions.php \
    Model/ResourceModel/PromptRule/Grid/Collection.php \
    etc/adminhtml/di.xml \
    etc/adminhtml/menu.xml
git commit -m "feat: add prompt rules admin grid with listing and menu (#32)"
```

---

### Task 10: Admin form — Edit/New controllers and form UI component

**Files:**
- Create: `Controller/Adminhtml/PromptRule/Edit.php`
- Create: `Controller/Adminhtml/PromptRule/NewAction.php`
- Create: `Controller/Adminhtml/PromptRule/Save.php`
- Create: `Controller/Adminhtml/PromptRule/Delete.php`
- Create: `view/adminhtml/layout/catalogai_promptrule_edit.xml`
- Create: `view/adminhtml/layout/catalogai_promptrule_new.xml`
- Create: `view/adminhtml/ui_component/mageos_catalogai_prompt_rule_form.xml`
- Create: `Model/PromptRule/DataProvider.php`

**Step 1: Create Edit controller**

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Controller\Adminhtml\PromptRule;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use MageOS\CatalogDataAI\Model\PromptRuleFactory;
use MageOS\CatalogDataAI\Model\ResourceModel\PromptRule as PromptRuleResource;

class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_CatalogDataAI::prompt_rules';

    public function __construct(
        Action\Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly PromptRuleFactory $ruleFactory,
        private readonly PromptRuleResource $ruleResource
    ) {
        parent::__construct($context);
    }

    public function execute(): Page
    {
        $ruleId = (int)$this->getRequest()->getParam('rule_id');
        $rule = $this->ruleFactory->create();

        if ($ruleId) {
            $this->ruleResource->load($rule, $ruleId);
            if (!$rule->getRuleId()) {
                $this->messageManager->addErrorMessage(__('This rule no longer exists.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('MageOS_CatalogDataAI::prompt_rules');
        $resultPage->getConfig()->getTitle()->prepend(
            $ruleId ? __('Edit Rule: %1', $rule->getName()) : __('New Prompt Rule')
        );

        return $resultPage;
    }
}
```

**Step 2: Create NewAction controller**

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Controller\Adminhtml\PromptRule;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;

class NewAction extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_CatalogDataAI::prompt_rules';

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

namespace MageOS\CatalogDataAI\Controller\Adminhtml\PromptRule;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use MageOS\CatalogDataAI\Model\PromptRuleFactory;
use MageOS\CatalogDataAI\Model\ResourceModel\PromptRule as PromptRuleResource;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_CatalogDataAI::prompt_rules';

    public function __construct(
        Action\Context $context,
        private readonly PromptRuleFactory $ruleFactory,
        private readonly PromptRuleResource $ruleResource
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
            if (!$rule->getRuleId()) {
                $this->messageManager->addErrorMessage(__('This rule no longer exists.'));
                return $redirect->setPath('*/*/');
            }
        }

        if (isset($data['store_ids']) && is_array($data['store_ids'])) {
            $data['store_ids'] = implode(',', $data['store_ids']);
        }

        if (isset($data['rule']['conditions'])) {
            $rule->loadPost(['conditions' => $data['rule']['conditions']]);
        }

        $rule->addData($data);

        try {
            $this->ruleResource->save($rule);
            $this->messageManager->addSuccessMessage(__('The rule has been saved.'));

            if ($this->getRequest()->getParam('back')) {
                return $redirect->setPath('*/*/edit', ['rule_id' => $rule->getRuleId()]);
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

namespace MageOS\CatalogDataAI\Controller\Adminhtml\PromptRule;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use MageOS\CatalogDataAI\Model\PromptRuleFactory;
use MageOS\CatalogDataAI\Model\ResourceModel\PromptRule as PromptRuleResource;

class Delete extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_CatalogDataAI::prompt_rules';

    public function __construct(
        Action\Context $context,
        private readonly PromptRuleFactory $ruleFactory,
        private readonly PromptRuleResource $ruleResource
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $ruleId = (int)$this->getRequest()->getParam('rule_id');
        $redirect = $this->resultRedirectFactory->create()->setPath('*/*/');

        if (!$ruleId) {
            $this->messageManager->addErrorMessage(__('Rule ID is required.'));
            return $redirect;
        }

        $rule = $this->ruleFactory->create();
        $this->ruleResource->load($rule, $ruleId);

        if (!$rule->getRuleId()) {
            $this->messageManager->addErrorMessage(__('This rule no longer exists.'));
            return $redirect;
        }

        try {
            $this->ruleResource->delete($rule);
            $this->messageManager->addSuccessMessage(__('The rule has been deleted.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $redirect;
    }
}
```

**Step 5: Create layout files**

`view/adminhtml/layout/catalogai_promptrule_edit.xml`:
```xml
<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <referenceContainer name="content">
            <uiComponent name="mageos_catalogai_prompt_rule_form"/>
        </referenceContainer>
    </body>
</page>
```

`view/adminhtml/layout/catalogai_promptrule_new.xml`:
```xml
<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <referenceContainer name="content">
            <uiComponent name="mageos_catalogai_prompt_rule_form"/>
        </referenceContainer>
    </body>
</page>
```

**Step 6: Create the form DataProvider**

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\PromptRule;

use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use MageOS\CatalogDataAI\Model\PromptRuleFactory;
use MageOS\CatalogDataAI\Model\ResourceModel\PromptRule as PromptRuleResource;
use MageOS\CatalogDataAI\Model\ResourceModel\PromptRule\CollectionFactory;

class DataProvider extends AbstractDataProvider
{
    private array $loadedData = [];

    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly PromptRuleFactory $ruleFactory,
        private readonly PromptRuleResource $ruleResource,
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

            if ($rule->getRuleId()) {
                $data = $rule->getData();
                if (isset($data['store_ids']) && is_string($data['store_ids'])) {
                    $data['store_ids'] = explode(',', $data['store_ids']);
                }
                $this->loadedData[$ruleId] = $data;
            }
        }

        return $this->loadedData;
    }
}
```

**Step 7: Create the form UI component**

Create `view/adminhtml/ui_component/mageos_catalogai_prompt_rule_form.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<form xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Ui:etc/ui_configuration.xsd">
    <argument name="data" xsi:type="array">
        <item name="js_config" xsi:type="array">
            <item name="provider" xsi:type="string">mageos_catalogai_prompt_rule_form.mageos_catalogai_prompt_rule_form_data_source</item>
        </item>
        <item name="label" xsi:type="string" translate="true">Prompt Rule</item>
        <item name="template" xsi:type="string">templates/form/collapsible</item>
    </argument>
    <settings>
        <buttons>
            <button name="back">
                <url path="*/*/"/>
                <class>back</class>
                <label translate="true">Back</label>
            </button>
            <button name="delete" class="MageOS\CatalogDataAI\Block\Adminhtml\PromptRule\Edit\DeleteButton"/>
            <button name="save" class="MageOS\CatalogDataAI\Block\Adminhtml\PromptRule\Edit\SaveButton"/>
        </buttons>
        <namespace>mageos_catalogai_prompt_rule_form</namespace>
        <dataScope>data</dataScope>
        <deps>
            <dep>mageos_catalogai_prompt_rule_form.mageos_catalogai_prompt_rule_form_data_source</dep>
        </deps>
    </settings>
    <dataSource name="mageos_catalogai_prompt_rule_form_data_source">
        <argument name="data" xsi:type="array">
            <item name="js_config" xsi:type="array">
                <item name="component" xsi:type="string">Magento_Ui/js/form/provider</item>
            </item>
        </argument>
        <settings>
            <submitUrl path="catalogai/promptrule/save"/>
        </settings>
        <dataProvider class="MageOS\CatalogDataAI\Model\PromptRule\DataProvider" name="mageos_catalogai_prompt_rule_form_data_source">
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
        <field name="attribute_code" formElement="select">
            <settings>
                <dataType>text</dataType>
                <label translate="true">Target Attribute</label>
                <validation>
                    <rule name="required-entry" xsi:type="boolean">true</rule>
                </validation>
            </settings>
            <formElements>
                <select>
                    <settings>
                        <options class="MageOS\CatalogDataAI\Model\Config\Source\EnrichableAttributes"/>
                    </settings>
                </select>
            </formElements>
        </field>
        <field name="store_ids" formElement="multiselect">
            <settings>
                <dataType>text</dataType>
                <label translate="true">Store Views</label>
                <tooltip>
                    <description translate="true">Select store views this rule applies to. Leave empty or select "All Store Views" for all stores.</description>
                </tooltip>
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
                <notice translate="true">Higher value = higher priority. When multiple rules match, the one with the highest priority wins.</notice>
                <validation>
                    <rule name="validate-digits" xsi:type="boolean">true</rule>
                </validation>
            </settings>
        </field>
    </fieldset>
    <fieldset name="prompt_fieldset">
        <settings>
            <label translate="true">Prompt</label>
        </settings>
        <field name="prompt" formElement="textarea">
            <settings>
                <dataType>text</dataType>
                <label translate="true">Prompt Template</label>
                <notice translate="true">Use {{attribute_code}} placeholders for product data (e.g. {{name}}, {{price}}).</notice>
                <validation>
                    <rule name="required-entry" xsi:type="boolean">true</rule>
                </validation>
            </settings>
        </field>
    </fieldset>
</form>
```

**Step 8: Create the EnrichableAttributes source model**

Create `Model/Config/Source/EnrichableAttributes.php`:

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\Config\Source;

use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Data\OptionSourceInterface;

class EnrichableAttributes implements OptionSourceInterface
{
    public function __construct(
        private readonly ProductAttributeRepositoryInterface $attributeRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
    }

    public function toOptionArray(): array
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('frontend_input', ['text', 'textarea'], 'in')
            ->create();

        $attributes = $this->attributeRepository->getList($searchCriteria);

        $options = [['value' => '', 'label' => __('-- Please Select --')]];
        foreach ($attributes->getItems() as $attribute) {
            $options[] = [
                'value' => $attribute->getAttributeCode(),
                'label' => ($attribute->getDefaultFrontendLabel() ?? $attribute->getAttributeCode()),
            ];
        }

        usort($options, fn($a, $b) => strcmp((string)$a['label'], (string)$b['label']));

        return $options;
    }
}
```

**Step 9: Create Save/Delete button blocks**

Create `Block/Adminhtml/PromptRule/Edit/SaveButton.php`:

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Block\Adminhtml\PromptRule\Edit;

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

Create `Block/Adminhtml/PromptRule/Edit/DeleteButton.php`:

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Block\Adminhtml\PromptRule\Edit;

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

**Step 10: Commit**

```bash
git add Controller/Adminhtml/PromptRule/Edit.php \
    Controller/Adminhtml/PromptRule/NewAction.php \
    Controller/Adminhtml/PromptRule/Save.php \
    Controller/Adminhtml/PromptRule/Delete.php \
    view/adminhtml/layout/catalogai_promptrule_edit.xml \
    view/adminhtml/layout/catalogai_promptrule_new.xml \
    view/adminhtml/ui_component/mageos_catalogai_prompt_rule_form.xml \
    Model/PromptRule/DataProvider.php \
    Model/Config/Source/EnrichableAttributes.php \
    Block/Adminhtml/PromptRule/Edit/SaveButton.php \
    Block/Adminhtml/PromptRule/Edit/DeleteButton.php
git commit -m "feat: add prompt rule edit form with CRUD controllers (#32)"
```

---

### Task 11: Add conditions fieldset via form modifier

**Files:**
- Create: `Ui/DataProvider/PromptRule/Form/Modifier/Conditions.php`
- Modify: `etc/adminhtml/di.xml`

The Magento Rule conditions widget requires a special UI modifier to render conditions HTML within the UI component form. This follows the same pattern as `Magento_CatalogRule`.

**Step 1: Create the conditions modifier**

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Ui\DataProvider\PromptRule\Form\Modifier;

use Magento\Framework\App\RequestInterface;
use Magento\Rule\Model\Condition\AbstractCondition;
use Magento\Ui\DataProvider\Modifier\ModifierInterface;
use MageOS\CatalogDataAI\Model\PromptRuleFactory;
use MageOS\CatalogDataAI\Model\ResourceModel\PromptRule as PromptRuleResource;

class Conditions implements ModifierInterface
{
    public function __construct(
        private readonly PromptRuleFactory $ruleFactory,
        private readonly PromptRuleResource $ruleResource,
        private readonly RequestInterface $request
    ) {
    }

    public function modifyData(array $data): array
    {
        $ruleId = (int)$this->request->getParam('rule_id');
        if ($ruleId && isset($data[$ruleId])) {
            $rule = $this->ruleFactory->create();
            $this->ruleResource->load($rule, $ruleId);

            $conditions = $rule->getConditions()->asArray();
            $data[$ruleId]['rule']['conditions'] = $this->convertConditions($conditions);
        }

        return $data;
    }

    public function modifyMeta(array $meta): array
    {
        $meta['conditions_fieldset'] = [
            'arguments' => [
                'data' => [
                    'config' => [
                        'label' => __('Conditions'),
                        'componentType' => 'fieldset',
                        'collapsible' => true,
                        'sortOrder' => 20,
                    ],
                ],
            ],
            'children' => [
                'conditions_notice' => [
                    'arguments' => [
                        'data' => [
                            'config' => [
                                'componentType' => 'container',
                                'component' => 'Magento_Ui/js/form/components/html',
                                'content' => (string)__(
                                    'Define product conditions that must match for this rule to apply. '
                                    . 'If no conditions are set, the rule applies to all products.'
                                ),
                            ],
                        ],
                    ],
                ],
            ],
        ];

        return $meta;
    }

    private function convertConditions(array $conditions): array
    {
        $result = [];
        if (isset($conditions['type'])) {
            $result['type'] = $conditions['type'];
            $result['attribute'] = $conditions['attribute'] ?? '';
            $result['operator'] = $conditions['operator'] ?? '';
            $result['value'] = $conditions['value'] ?? '';
            $result['aggregator'] = $conditions['aggregator'] ?? 'all';
            if (isset($conditions['conditions'])) {
                foreach ($conditions['conditions'] as $key => $condition) {
                    $result['conditions'][$key] = $this->convertConditions($condition);
                }
            }
        }
        return $result;
    }
}
```

**Step 2: Register the modifier in adminhtml di.xml**

Add to `etc/adminhtml/di.xml`:

```xml
    <virtualType name="MageOS\CatalogDataAI\PromptRule\Form\Modifier\Pool" type="Magento\Ui\DataProvider\Modifier\Pool">
        <arguments>
            <argument name="modifiers" xsi:type="array">
                <item name="conditions" xsi:type="array">
                    <item name="class" xsi:type="string">MageOS\CatalogDataAI\Ui\DataProvider\PromptRule\Form\Modifier\Conditions</item>
                    <item name="sortOrder" xsi:type="number">10</item>
                </item>
            </argument>
        </arguments>
    </virtualType>
```

**Step 3: Commit**

```bash
git add Ui/DataProvider/PromptRule/Form/Modifier/Conditions.php etc/adminhtml/di.xml
git commit -m "feat: add conditions fieldset modifier for prompt rule form (#32)"
```

---

### Task 12: Add preview/test AJAX controller

**Files:**
- Create: `Controller/Adminhtml/PromptRule/Preview.php`

**Step 1: Create the preview controller**

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Controller\Adminhtml\PromptRule;

use Magento\Backend\App\Action;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use MageOS\CatalogDataAI\Model\Product\Enricher;

class Preview extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_CatalogDataAI::prompt_rules';

    public function __construct(
        Action\Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly Enricher $enricher
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $sku = $this->getRequest()->getParam('sku');
        $prompt = $this->getRequest()->getParam('prompt');

        if (!$sku || !$prompt) {
            return $result->setData([
                'success' => false,
                'message' => 'SKU and prompt are required.',
            ]);
        }

        try {
            $product = $this->productRepository->get($sku);
            $resolvedPrompt = $this->enricher->parsePrompt($prompt, $product);

            return $result->setData([
                'success' => true,
                'resolved_prompt' => $resolvedPrompt,
                'product_name' => $product->getName(),
            ]);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return $result->setData([
                'success' => false,
                'message' => sprintf('Product with SKU "%s" not found.', $sku),
            ]);
        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
```

**Step 2: Commit**

```bash
git add Controller/Adminhtml/PromptRule/Preview.php
git commit -m "feat: add preview/test controller for prompt rules (#32)"
```

---

### Task 13: Add MassDelete controller

**Files:**
- Create: `Controller/Adminhtml/PromptRule/MassDelete.php`

**Step 1: Create the mass delete controller**

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Controller\Adminhtml\PromptRule;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;
use MageOS\CatalogDataAI\Model\ResourceModel\PromptRule as PromptRuleResource;
use MageOS\CatalogDataAI\Model\ResourceModel\PromptRule\CollectionFactory;

class MassDelete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_CatalogDataAI::prompt_rules';

    public function __construct(
        Action\Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly PromptRuleResource $ruleResource
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $deleted = 0;

        foreach ($collection as $rule) {
            $this->ruleResource->delete($rule);
            $deleted++;
        }

        $this->messageManager->addSuccessMessage(__('A total of %1 rule(s) have been deleted.', $deleted));
        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
```

**Step 2: Commit**

```bash
git add Controller/Adminhtml/PromptRule/MassDelete.php
git commit -m "feat: add mass delete controller for prompt rules (#32)"
```

---

### Task 14: Smoke test

**Step 1: Run Magento setup commands (inside Warden)**

```bash
warden env exec php-fpm bin/magento setup:upgrade
warden env exec php-fpm bin/magento setup:di:compile
```

Both should complete without errors.

**Step 2: Verify the DB table**

Check that `mageos_catalogai_prompt_rule` table was created with correct columns.

**Step 3: Verify admin pages**

1. Navigate to **Catalog > AI Prompt Rules** — grid should load
2. Click **Add New Rule** — form should render with all fields
3. Create a test rule: name, attribute, prompt, priority, save — should persist
4. Navigate to **AI Services > Data Enrichment** — config should render at the new location
5. Verify old config at Catalog > AI Data Enrichment no longer exists

**Step 4: Run all tests**

```bash
vendor/bin/phpunit Test/
```

Expected: All tests pass.

**Step 5: Final commit (if adjustments needed)**

```bash
git add -A
git commit -m "fix: adjustments from smoke testing Phase 3"
```

---

## Summary of Files Changed/Created

| Action | File |
|--------|------|
| **Config Migration** | |
| Modify | `etc/adminhtml/system.xml` |
| Modify | `etc/config.xml` |
| Modify | `Model/Config.php` |
| Modify | `etc/acl.xml` |
| Modify | `etc/di.xml` |
| Modify | `Test/Unit/Model/ConfigTest.php` |
| Create | `Setup/Patch/Data/MigrateConfigToAiIntegration.php` |
| **Rules Engine — Data** | |
| Modify | `etc/db_schema.xml` |
| Modify | `etc/db_schema_whitelist.json` |
| Create | `Api/Data/PromptRuleInterface.php` |
| Create | `Model/PromptRule.php` |
| Create | `Model/ResourceModel/PromptRule.php` |
| Create | `Model/ResourceModel/PromptRule/Collection.php` |
| Create | `Model/ResourceModel/PromptRule/Grid/Collection.php` |
| Create | `Model/Product/PromptResolver.php` |
| Create | `Test/Unit/Model/Product/PromptResolverTest.php` |
| Modify | `Model/Product/Enricher.php` |
| Modify | `Test/Unit/Model/Product/EnricherTest.php` |
| **Rules Engine — Admin UI** | |
| Create | `etc/adminhtml/menu.xml` |
| Modify | `etc/adminhtml/di.xml` |
| Create | `Controller/Adminhtml/PromptRule/Index.php` |
| Create | `Controller/Adminhtml/PromptRule/Edit.php` |
| Create | `Controller/Adminhtml/PromptRule/NewAction.php` |
| Create | `Controller/Adminhtml/PromptRule/Save.php` |
| Create | `Controller/Adminhtml/PromptRule/Delete.php` |
| Create | `Controller/Adminhtml/PromptRule/MassDelete.php` |
| Create | `Controller/Adminhtml/PromptRule/Preview.php` |
| Create | `Model/PromptRule/DataProvider.php` |
| Create | `Model/Config/Source/EnrichableAttributes.php` |
| Create | `Block/Adminhtml/PromptRule/Edit/SaveButton.php` |
| Create | `Block/Adminhtml/PromptRule/Edit/DeleteButton.php` |
| Create | `Ui/Component/Listing/Column/PromptRuleActions.php` |
| Create | `Ui/DataProvider/PromptRule/Form/Modifier/Conditions.php` |
| Create | `view/adminhtml/layout/catalogai_promptrule_index.xml` |
| Create | `view/adminhtml/layout/catalogai_promptrule_edit.xml` |
| Create | `view/adminhtml/layout/catalogai_promptrule_new.xml` |
| Create | `view/adminhtml/ui_component/mageos_catalogai_prompt_rule_listing.xml` |
| Create | `view/adminhtml/ui_component/mageos_catalogai_prompt_rule_form.xml` |
