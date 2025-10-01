<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\Parser;

use Magento\Catalog\Model\Product;

/**
 * Default fallback parser for any attribute type
 *
 * Provides basic string conversion with minimal processing for unknown attribute types
 */
class DefaultParser extends AbstractParser
{
    protected const DEFAULT_PRIORITY = 1; // Lowest priority - fallback only

    /**
     * {@inheritdoc}
     */
    public function canParse(string $attributeCode, Product $product): bool
    {
        // Default parser can handle any attribute as a fallback
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function parse(string $attributeCode, Product $product): string
    {
        $value = $this->getProductData($product, $attributeCode);

        if ($value === null || $value === '') {
            return '';
        }

        // Handle arrays (might be from complex attributes)
        if (is_array($value)) {
            // For arrays, try to get a meaningful string representation
            $filtered = array_filter($value, function($item) {
                return $item !== null && $item !== '';
            });

            if (empty($filtered)) {
                return '';
            }

            // If it's a simple array of scalars, join them
            if (count($filtered) <= 10) { // Limit to prevent huge strings
                return implode(', ', array_map('strval', $filtered));
            }

            // For large arrays, just indicate count
            return 'Multiple values (' . count($filtered) . ' items)';
        }

        // Handle objects
        if (is_object($value)) {
            // Try to get string representation
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }

            // If it's a data object, try to get useful info
            if (method_exists($value, 'getData')) {
                $data = $value->getData();
                if (is_array($data) && !empty($data)) {
                    // Get first few meaningful values
                    $meaningful = array_filter($data, function($item) {
                        return is_scalar($item) && $item !== null && $item !== '';
                    });

                    if (!empty($meaningful)) {
                        $sample = array_slice($meaningful, 0, 3, true);
                        $parts = [];
                        foreach ($sample as $key => $val) {
                            $parts[] = $key . ': ' . $val;
                        }
                        return implode(', ', $parts);
                    }
                }
            }

            return get_class($value) . ' object';
        }

        // For scalar values, convert to string with basic cleanup
        $stringValue = (string) $value;

        // Basic cleanup - trim and normalize whitespace
        $stringValue = trim(preg_replace('/\s+/', ' ', $stringValue));

        // If it looks like HTML, strip tags for cleaner AI prompt
        if (strlen($stringValue) > 50 && (strpos($stringValue, '<') !== false || strpos($stringValue, '&') !== false)) {
            $cleaned = html_entity_decode(strip_tags($stringValue), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($cleaned !== $stringValue) {
                $stringValue = trim($cleaned);
            }
        }

        return $stringValue;
    }
}
