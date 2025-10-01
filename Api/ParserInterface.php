<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Api;

use Magento\Catalog\Model\Product;

/**
 * Interface for product attribute value parsers
 *
 * Parsers are responsible for converting product attribute values
 * into appropriate string representations for use in AI prompts.
 */
interface ParserInterface
{
    /**
     * Parse product attribute value for use in prompts
     *
     * @param string $attributeCode The attribute code to parse
     * @param Product $product The product instance
     * @return string The parsed attribute value
     */
    public function parse(string $attributeCode, Product $product): string;

    /**
     * Check if this parser can handle the given attribute
     *
     * @param string $attributeCode The attribute code to check
     * @param Product $product The product instance
     * @return bool True if this parser can handle the attribute
     */
    public function canParse(string $attributeCode, Product $product): bool;

    /**
     * Get the priority of this parser (higher = more priority)
     * Used when multiple parsers can handle the same attribute type
     *
     * @return int The parser priority
     */
    public function getPriority(): int;
}
