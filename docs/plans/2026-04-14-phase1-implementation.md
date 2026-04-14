# Phase 1: Housekeeping + Configurable Attributes — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Fix code style, composer deps, and system.xml label issues, then replace the hardcoded 5-attribute enrichment list with a dynamic row configuration that lets admins enrich any text attribute.

**Architecture:** The dynamic rows feature replaces the static `catalog_ai/product/*` config fields with a single serialized field using Magento's `AbstractFieldArray` + `ArraySerialized` backend model. A custom select renderer provides an attribute dropdown. The `Config` model and `Enricher` are updated to read from the new serialized format. A data patch migrates existing config.

**Tech Stack:** PHP 8.1+, Magento 2.4.x, PHPUnit 9.5

---

### Task 1: Fix composer.json

**Files:**
- Modify: `composer.json`

**Step 1: Update composer.json**

Replace the entire content of `composer.json` with:

```json
{
    "name": "mage-os/module-catalog-data-ai",
    "description": "Generate product descriptions and similar content with the help of AI.",
    "type": "magento2-module",
    "license": [
        "MIT"
    ],
    "authors": [
        {
            "name": "Ryan Sun",
            "email": "ryansun81@gmail.com",
            "homepage": "https://www.sunmerce.com/"
        }
    ],
    "require": {
        "php": "^8.1",
        "openai-php/client": "*",
        "magento/framework": "*",
        "magento/module-catalog": "*",
        "magento/module-ui": "*",
        "magento/module-backend": "*",
        "magento/module-config": "*",
        "magento/module-store": "*"
    },
    "require-dev": {
        "phpunit/phpunit": "^9.5"
    },
    "autoload": {
        "files": [
            "registration.php"
        ],
        "psr-4": {
            "MageOS\\CatalogDataAI\\": ""
        }
    }
}
```

Changes from current:
- Removed `repositories` block (the mage-os mirror is not needed for the package itself)
- Added explicit Magento module dependencies: `magento/framework`, `magento/module-catalog`, `magento/module-ui`, `magento/module-backend`, `magento/module-config`, `magento/module-store`

**Step 2: Commit**

```bash
git add composer.json
git commit -m "fix: add explicit Magento module dependencies, remove mirror repo (#22)"
```

---

### Task 2: Fix system.xml labels and comments

**Files:**
- Modify: `etc/adminhtml/system.xml:62-75`

**Step 1: Fix the temperature label and add comments to advanced fields**

In `etc/adminhtml/system.xml`, replace the `advanced` group (lines 61-76) with:

```xml
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
```

Key changes:
- Temperature `<label>` changed from "System Prompt" to "Temperature"
- Added `<comment>` to all 4 advanced fields explaining valid ranges
- Presence penalty `sortOrder` changed from `30` to `40` (was duplicate)

**Step 2: Commit**

```bash
git add etc/adminhtml/system.xml
git commit -m "fix: correct temperature label, add range comments to advanced fields (#25)"
```

---

### Task 3: Run PHP-CS-Fixer

**Files:**
- Modify: all `.php` files

**Step 1: Install PHP-CS-Fixer (if not available globally)**

```bash
composer global require friendsofphp/php-cs-fixer --dev
```

**Step 2: Create a `.php-cs-fixer.dist.php` config at module root**

Create file `.php-cs-fixer.dist.php`:

```php
<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_unused_imports' => true,
        'single_quote' => true,
        'no_trailing_whitespace' => true,
        'no_whitespace_in_blank_line' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
    ])
    ->setFinder($finder);
```

**Step 3: Run the fixer**

```bash
php-cs-fixer fix --config=.php-cs-fixer.dist.php --diff
```

Review the diff to make sure nothing weird happened. The changes should be whitespace, brace placement, and import ordering only.

**Step 4: Commit**

```bash
git add -A
git commit -m "style: apply PSR-12 code style fixes (#23)"
```

---

### Task 4: Create the attribute column select renderer

**Files:**
- Create: `Block/Adminhtml/Form/Field/AttributeColumn.php`

**Step 1: Write the test**

Create file `Test/Unit/Block/Adminhtml/Form/Field/AttributeColumnTest.php`:

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Block\Adminhtml\Form\Field;

use MageOS\CatalogDataAI\Block\Adminhtml\Form\Field\AttributeColumn;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteria;
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
```

**Step 2: Run test to verify it fails**

```bash
vendor/bin/phpunit Test/Unit/Block/Adminhtml/Form/Field/AttributeColumnTest.php
```

Expected: FAIL — class `AttributeColumn` does not exist.

**Step 3: Write the implementation**

Create file `Block/Adminhtml/Form/Field/AttributeColumn.php`:

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Block\Adminhtml\Form\Field;

use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\View\Element\Html\Select;
use Magento\Framework\View\Element\Context;

class AttributeColumn extends Select
{
    private array $options = [];

    public function __construct(
        private readonly ProductAttributeRepositoryInterface $attributeRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        Context $context = null,
        array $data = []
    ) {
        // Context may be null in unit tests
        if ($context !== null) {
            parent::__construct($context, $data);
        }
    }

    public function getOptions(): array
    {
        if (empty($this->options)) {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('frontend_input', ['text', 'textarea'], 'in')
                ->create();

            $attributes = $this->attributeRepository->getList($searchCriteria);

            foreach ($attributes->getItems() as $attribute) {
                $this->options[$attribute->getAttributeCode()] = $attribute->getDefaultFrontendLabel()
                    ?? $attribute->getAttributeCode();
            }

            asort($this->options);
        }

        return $this->options;
    }

    public function setInputName(string $value): self
    {
        return $this->setName($value);
    }

    public function setInputId(string $value): self
    {
        return $this->setId($value);
    }

    public function _toHtml(): string
    {
        if (!$this->getOptions()) {
            $this->setOptions($this->getOptions());
        }

        foreach ($this->getOptions() as $code => $label) {
            $this->addOption($code, $label);
        }

        return parent::_toHtml();
    }
}
```

**Step 4: Run test to verify it passes**

```bash
vendor/bin/phpunit Test/Unit/Block/Adminhtml/Form/Field/AttributeColumnTest.php
```

Expected: PASS

**Step 5: Commit**

```bash
git add Block/Adminhtml/Form/Field/AttributeColumn.php Test/Unit/Block/Adminhtml/Form/Field/AttributeColumnTest.php
git commit -m "feat: add attribute column select renderer for dynamic rows (#27)"
```

---

### Task 5: Create the dynamic rows field array block

**Files:**
- Create: `Block/Adminhtml/Form/Field/ProductAttributes.php`

**Step 1: Write the implementation**

This block is mostly wiring — Magento's `AbstractFieldArray` does the heavy lifting. No meaningful unit test possible without the full layout system.

Create file `Block/Adminhtml/Form/Field/ProductAttributes.php`:

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;

class ProductAttributes extends AbstractFieldArray
{
    private ?AttributeColumn $attributeRenderer = null;

    protected function _prepareToRender(): void
    {
        $this->addColumn('attribute', [
            'label' => __('Attribute'),
            'renderer' => $this->getAttributeRenderer(),
            'class' => 'required-entry',
        ]);
        $this->addColumn('prompt', [
            'label' => __('Prompt'),
            'class' => 'required-entry',
        ]);
        $this->addColumn('enabled', [
            'label' => __('Enabled'),
            'class' => 'required-entry',
        ]);

        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add Attribute');
    }

    protected function _prepareArrayRow(DataObject $row): void
    {
        $options = [];
        $attribute = $row->getData('attribute');

        if ($attribute !== null) {
            $key = 'option_' . $this->getAttributeRenderer()->calcOptionHash($attribute);
            $options[$key] = 'selected="selected"';
        }

        $row->setData('option_extra_attrs', $options);
    }

    /**
     * @throws LocalizedException
     */
    private function getAttributeRenderer(): AttributeColumn
    {
        if ($this->attributeRenderer === null) {
            $this->attributeRenderer = $this->getLayout()->createBlock(
                AttributeColumn::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }

        return $this->attributeRenderer;
    }
}
```

**Step 2: Commit**

```bash
git add Block/Adminhtml/Form/Field/ProductAttributes.php
git commit -m "feat: add dynamic rows field array block for product attributes (#27)"
```

---

### Task 6: Update system.xml — replace static fields with dynamic rows

**Files:**
- Modify: `etc/adminhtml/system.xml:38-59`

**Step 1: Replace the product group**

In `etc/adminhtml/system.xml`, replace the entire `product` group (lines 38-59) with:

```xml
            <group id="product" translate="label comment" sortOrder="20" showInDefault="1" showInStore="1">
                <label>Product Fields Auto-Generation</label>
                <comment>
                    <![CDATA[Use {{product_attribute_code}} (e.g. {{name}} for product name) as product attribute data placeholder.
                    <br />
                    To enrich a meta attribute, the corresponding default value (mask) must be removed, see <a href="https://experienceleague.adobe.com/docs/commerce-admin/catalog/products/product-workspace.html#edit-the-placeholder-value">Edit the placeholder value</a>]]>
                </comment>
                <field id="attribute_prompts" translate="label" sortOrder="10" showInDefault="1" showInStore="1">
                    <label>Attribute Prompts</label>
                    <frontend_model>MageOS\CatalogDataAI\Block\Adminhtml\Form\Field\ProductAttributes</frontend_model>
                    <backend_model>Magento\Config\Model\Config\Backend\Serialized\ArraySerialized</backend_model>
                </field>
            </group>
```

This replaces the 5 individual textarea fields with a single dynamic rows field.

**Step 2: Commit**

```bash
git add etc/adminhtml/system.xml
git commit -m "feat: replace static product fields with dynamic rows config (#27)"
```

---

### Task 7: Update config.xml — default values in dynamic row format

**Files:**
- Modify: `etc/config.xml:11-15`

**Step 1: Replace the product defaults**

In `etc/config.xml`, replace the `<product>` block (lines 11-15) with:

```xml
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
```

Note: `meta_title`, `meta_keyword`, and `meta_description` previously had no default prompts. We're adding sensible defaults now since the dynamic rows format requires a prompt value per row.

**Step 2: Commit**

```bash
git add etc/config.xml
git commit -m "feat: add default dynamic row values for all 5 enrichable attributes (#27)"
```

---

### Task 8: Update Config model to read dynamic rows

**Files:**
- Modify: `Model/Config.php:74-80`
- Test: `Test/Unit/Model/ConfigTest.php`

**Step 1: Write the test**

Create file `Test/Unit/Model/ConfigTest.php`:

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Model;

use MageOS\CatalogDataAI\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    private ScopeConfigInterface $scopeConfig;
    private Config $config;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->config = new Config($this->scopeConfig);
    }

    public function test_get_enrichable_attributes_returns_enabled_attributes(): void
    {
        $serializedData = [
            'row1' => ['attribute' => 'description', 'prompt' => 'describe {{name}}', 'enabled' => '1'],
            'row2' => ['attribute' => 'meta_title', 'prompt' => 'title for {{name}}', 'enabled' => '0'],
            'row3' => ['attribute' => 'short_description', 'prompt' => 'short desc for {{name}}', 'enabled' => '1'],
        ];

        $this->scopeConfig->method('getValue')
            ->with('catalog_ai/product/attribute_prompts')
            ->willReturn($serializedData);

        $result = $this->config->getEnrichableAttributes();

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('description', $result);
        $this->assertArrayHasKey('short_description', $result);
        $this->assertArrayNotHasKey('meta_title', $result);
        $this->assertEquals('describe {{name}}', $result['description']);
        $this->assertEquals('short desc for {{name}}', $result['short_description']);
    }

    public function test_get_enrichable_attributes_returns_empty_when_null(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('catalog_ai/product/attribute_prompts')
            ->willReturn(null);

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
            ->with('catalog_ai/product/attribute_prompts')
            ->willReturn($serializedData);

        $this->assertEquals('describe {{name}}', $this->config->getProductPrompt('description'));
        $this->assertNull($this->config->getProductPrompt('nonexistent'));
    }
}
```

**Step 2: Run test to verify it fails**

```bash
vendor/bin/phpunit Test/Unit/Model/ConfigTest.php
```

Expected: FAIL — `getEnrichableAttributes` method does not exist.

**Step 3: Update Config model**

In `Model/Config.php`, replace the `getProductPrompt` method (lines 74-80) with two new methods:

```php
    public function getEnrichableAttributes(): array
    {
        $rows = $this->scopeConfig->getValue(
            'catalog_ai/product/attribute_prompts'
        );

        if (!is_array($rows)) {
            return [];
        }

        $attributes = [];
        foreach ($rows as $row) {
            if (isset($row['attribute'], $row['prompt'], $row['enabled']) && (int)$row['enabled'] === 1) {
                $attributes[$row['attribute']] = $row['prompt'];
            }
        }

        return $attributes;
    }

    public function getProductPrompt(string $attributeCode): ?string
    {
        $attributes = $this->getEnrichableAttributes();

        return $attributes[$attributeCode] ?? null;
    }
```

**Step 4: Run test to verify it passes**

```bash
vendor/bin/phpunit Test/Unit/Model/ConfigTest.php
```

Expected: PASS

**Step 5: Commit**

```bash
git add Model/Config.php Test/Unit/Model/ConfigTest.php
git commit -m "feat: update Config to read attribute prompts from dynamic rows (#27)"
```

---

### Task 9: Update Enricher to use dynamic config

**Files:**
- Modify: `Model/Product/Enricher.php:36-45`
- Test: `Test/Unit/Model/Product/EnricherTest.php`

**Step 1: Write the test**

Create file `Test/Unit/Model/Product/EnricherTest.php`:

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Model\Product;

use MageOS\CatalogDataAI\Model\Product\Enricher;
use MageOS\CatalogDataAI\Model\Config;
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
        $enricher = new Enricher($factory, $config);

        $this->assertEquals(['description', 'custom_field'], $enricher->getAttributes());
    }

    public function test_parse_prompt_replaces_placeholders_with_product_data(): void
    {
        $config = $this->createMock(Config::class);
        $factory = $this->createMock(Factory::class);
        $enricher = new Enricher($factory, $config);

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
```

**Step 2: Run test to verify it fails**

```bash
vendor/bin/phpunit Test/Unit/Model/Product/EnricherTest.php
```

Expected: FAIL — `getAttributes()` returns hardcoded array, not config-driven.

**Step 3: Update Enricher**

In `Model/Product/Enricher.php`, replace the `getAttributes` method (lines 36-45) with:

```php
    public function getAttributes(): array
    {
        return array_keys($this->config->getEnrichableAttributes());
    }
```

**Step 4: Run test to verify it passes**

```bash
vendor/bin/phpunit Test/Unit/Model/Product/EnricherTest.php
```

Expected: PASS

**Step 5: Commit**

```bash
git add Model/Product/Enricher.php Test/Unit/Model/Product/EnricherTest.php
git commit -m "feat: Enricher reads attribute list from dynamic config (#27)"
```

---

### Task 10: Create data patch to migrate existing config

**Files:**
- Create: `Setup/Patch/Data/MigrateProductPromptsToDynamicRows.php`

**Step 1: Write the data patch**

Create file `Setup/Patch/Data/MigrateProductPromptsToDynamicRows.php`:

```php
<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Setup\Patch\Data;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Serialize\Serializer\Json;

class MigrateProductPromptsToDynamicRows implements DataPatchInterface
{
    private const OLD_ATTRIBUTE_PATHS = [
        'short_description',
        'description',
        'meta_title',
        'meta_keyword',
        'meta_description',
    ];

    private const NEW_PATH = 'catalog_ai/product/attribute_prompts';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly WriterInterface $configWriter,
        private readonly Json $json
    ) {
    }

    public function apply(): self
    {
        $rows = [];
        $hasExistingConfig = false;

        foreach (self::OLD_ATTRIBUTE_PATHS as $attributeCode) {
            $oldPath = 'catalog_ai/product/' . $attributeCode;
            $value = $this->scopeConfig->getValue($oldPath);

            if ($value !== null && $value !== '') {
                $hasExistingConfig = true;
                $rows[$attributeCode] = [
                    'attribute' => $attributeCode,
                    'prompt' => $value,
                    'enabled' => '1',
                ];
            }
        }

        if ($hasExistingConfig) {
            $this->configWriter->save(self::NEW_PATH, $this->json->serialize($rows));

            // Clean up old paths
            foreach (self::OLD_ATTRIBUTE_PATHS as $attributeCode) {
                $this->configWriter->delete('catalog_ai/product/' . $attributeCode);
            }
        }

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
```

**Step 2: Commit**

```bash
git add Setup/Patch/Data/MigrateProductPromptsToDynamicRows.php
git commit -m "feat: add data patch to migrate old product prompts to dynamic rows (#27)"
```

---

### Task 11: Smoke test — setup:upgrade and di:compile

**Step 1: Run Magento setup commands**

From the Magento root directory:

```bash
bin/magento setup:upgrade
bin/magento setup:di:compile
```

Both should complete without errors. If `di:compile` fails, check the constructor signatures in the new block classes — Magento's DI compiler is strict about type hints.

**Step 2: Verify in admin**

1. Go to **Stores > Configuration > Catalog > AI Data Enrichment > Product Fields Auto-Generation**
2. Verify the dynamic rows table renders with the 5 default attributes
3. Verify the attribute dropdown loads product text/textarea attributes
4. Try adding a new row, saving, and reloading — confirm persistence

**Step 3: Run all tests**

```bash
vendor/bin/phpunit Test/
```

Expected: All tests pass.

**Step 4: Final commit (if any adjustments were needed)**

```bash
git add -A
git commit -m "fix: adjustments from smoke testing Phase 1"
```

---

## Summary of Files Changed/Created

| Action | File |
|--------|------|
| Modify | `composer.json` |
| Modify | `etc/adminhtml/system.xml` |
| Modify | `etc/config.xml` |
| Modify | `Model/Config.php` |
| Modify | `Model/Product/Enricher.php` |
| Create | `.php-cs-fixer.dist.php` |
| Create | `Block/Adminhtml/Form/Field/AttributeColumn.php` |
| Create | `Block/Adminhtml/Form/Field/ProductAttributes.php` |
| Create | `Setup/Patch/Data/MigrateProductPromptsToDynamicRows.php` |
| Create | `Test/Unit/Block/Adminhtml/Form/Field/AttributeColumnTest.php` |
| Create | `Test/Unit/Model/ConfigTest.php` |
| Create | `Test/Unit/Model/Product/EnricherTest.php` |
