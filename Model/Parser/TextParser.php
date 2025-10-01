<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\Parser;

use Magento\Catalog\Model\Product;

/**
 * Parser for text and textarea attributes
 *
 * Handles text, textarea, and other string-based attributes with proper HTML escaping
 */
class TextParser extends AbstractParser
{
    private const SUPPORTED_INPUTS = [
        'text',
        'textarea',
        'price',
        'weight',
        'gallery',
        'media_image'
    ];

    protected const DEFAULT_PRIORITY = 50;

    /**
     * {@inheritdoc}
     */
    public function canParse(string $attributeCode, Product $product): bool
    {
        $frontendInput = $this->getFrontendInput($attributeCode);

        if (!$frontendInput) {
            return false;
        }

        return in_array($frontendInput, self::SUPPORTED_INPUTS, true);
    }

    /**
     * {@inheritdoc}
     */
    public function parse(string $attributeCode, Product $product): string
    {
        $value = $this->getProductData($product, $attributeCode);

        if (empty($value)) {
            return '';
        }

        // Convert to string if not already
        $stringValue = (string) $value;

        // For textarea and potentially HTML-containing fields, escape HTML
        $frontendInput = $this->getFrontendInput($attributeCode);
        if (in_array($frontendInput, ['textarea'], true)) {
            // Strip HTML tags and decode entities for cleaner AI prompt text
            $stringValue = html_entity_decode(strip_tags($stringValue), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Trim and clean up whitespace
        $stringValue = trim(preg_replace('/\s+/', ' ', $stringValue));

        return $stringValue;
    }
}
