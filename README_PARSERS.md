# Attribute Parsers Documentation

## Overview

The Catalog Data AI module uses a extensible parser system to handle different attribute types when parsing prompt placeholders. This system allows for proper conversion of product attribute values into meaningful text for AI prompts.

## Architecture

The parser system consists of:

1. **ParserInterface** - Defines the contract for all parsers
2. **AbstractParser** - Base class providing common functionality
3. **Specific Parsers** - Handle different attribute types
4. **ParserPool** - Manages and resolves parsers

## Built-in Parsers

### TextParser
- **Priority**: 50
- **Handles**: text, textarea, price, weight, gallery, media_image
- **Functionality**: 
  - Strips HTML tags from textarea fields
  - Normalizes whitespace
  - Converts values to clean strings

### SelectParser  
- **Priority**: 60
- **Handles**: select attributes
- **Functionality**:
  - Converts option IDs to human-readable labels
  - Falls back to raw values if labels can't be found
  - Uses attribute source and frontend models

### MultiselectParser
- **Priority**: 60
- **Handles**: multiselect attributes
- **Functionality**:
  - Converts comma-separated option IDs to labels
  - Joins multiple labels with ", "
  - Handles both string and array values

### BooleanParser
- **Priority**: 70
- **Handles**: boolean attributes and yes/no selects
- **Functionality**:
  - Converts various boolean representations to "Yes"/"No"
  - Auto-detects boolean-like select attributes
  - Handles numeric, string, and actual boolean values

### DefaultParser
- **Priority**: 1 (lowest)
- **Handles**: any attribute (fallback)
- **Functionality**:
  - Provides basic string conversion
  - Handles arrays and objects intelligently
  - Strips HTML when detected
  - Always available as last resort

## How Parsers Work

1. When `{{attribute_code}}` is found in a prompt, the ParserPool is consulted
2. Parsers are sorted by priority (highest first)
3. Each parser's `canParse()` method is checked
4. The first parser that can handle the attribute is used
5. The parser's `parse()` method converts the value to string
6. If all parsers fail, raw product data is returned

## Creating Custom Parsers

### Step 1: Create Parser Class

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Model\Parser;

use MageOS\CatalogDataAI\Model\Parser\AbstractParser;
use Magento\Catalog\Model\Product;

class CustomParser extends AbstractParser
{
    protected const DEFAULT_PRIORITY = 80; // Higher priority

    public function canParse(string $attributeCode, Product $product): bool
    {
        // Logic to determine if this parser can handle the attribute
        $frontendInput = $this->getFrontendInput($attributeCode);
        return $frontendInput === 'custom_input_type';
    }

    public function parse(string $attributeCode, Product $product): string
    {
        $value = $this->getProductData($product, $attributeCode);
        
        // Custom parsing logic here
        return $this->customFormat($value);
    }
    
    private function customFormat($value): string
    {
        // Your custom formatting logic
        return (string) $value;
    }
}
```

### Step 2: Register Parser via DI

Create or extend `etc/di.xml` in your module:

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">
    
    <!-- Add your custom parser to the pool -->
    <type name="MageOS\CatalogDataAI\Model\ParserPool">
        <arguments>
            <argument name="parsers" xsi:type="array">
                <item name="custom_parser" xsi:type="object">Vendor\Module\Model\Parser\CustomParser</item>
            </argument>
        </arguments>
    </type>
    
</config>
```

### Step 3: Clear Cache

After adding a new parser:

```bash
ddev exec bin/magento cache:clean
```

## Parser Priority System

Parsers are executed in priority order (highest to lowest). Use these guidelines:

- **90-100**: Critical system parsers
- **70-89**: Specialized parsers (boolean, date, etc.)  
- **50-69**: Standard type parsers (text, select)
- **10-49**: Generic parsers
- **1-9**: Fallback parsers

## Available Helper Methods in AbstractParser

### Attribute Information
- `getAttribute(string $attributeCode)` - Get attribute instance
- `getFrontendInput(string $attributeCode)` - Get frontend input type

### Data Access
- `getProductData(Product $product, string $attributeCode, $default = '')` - Safe data retrieval

### Utilities
- `escapeHtml(string $value)` - HTML escape for safety

## Best Practices

### 1. Error Handling
Always include try-catch blocks and fallbacks:

```php
public function parse(string $attributeCode, Product $product): string
{
    try {
        return $this->complexParsing($attributeCode, $product);
    } catch (\Exception $e) {
        $this->logger->warning('Parser error', ['exception' => $e->getMessage()]);
        return $this->getProductData($product, $attributeCode);
    }
}
```

### 2. Performance
- Keep `canParse()` method lightweight
- Cache expensive operations when possible
- Use early returns for empty values

### 3. Logging
Log important events for debugging:

```php
$this->logger->debug('Custom parser selected', [
    'attribute_code' => $attributeCode,
    'value' => $value
]);
```
