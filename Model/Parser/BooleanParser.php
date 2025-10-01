<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\Parser;

use Magento\Catalog\Model\Product;

/**
 * Parser for boolean attributes
 *
 * Converts boolean and yes/no attributes to human-readable text for AI prompts
 */
class BooleanParser extends AbstractParser
{
    private const SUPPORTED_INPUTS = [
        'boolean',
        'select' // Some yes/no attributes use select frontend
    ];

    protected const DEFAULT_PRIORITY = 70;

    /**
     * {@inheritdoc}
     */
    public function canParse(string $attributeCode, Product $product): bool
    {
        $frontendInput = $this->getFrontendInput($attributeCode);

        if (!$frontendInput) {
            return false;
        }

        // Check if it's a boolean input type
        if ($frontendInput === 'boolean') {
            return true;
        }

        // Check if it's a select that acts as yes/no (common pattern in Magento)
        if ($frontendInput === 'select') {
            $attribute = $this->getAttribute($attributeCode);
            if ($attribute && $attribute->getSource()) {
                $options = $attribute->getSource()->getAllOptions(false);

                // Check if it has exactly 2 options that look like yes/no
                if (count($options) === 2) {
                    $labels = array_column($options, 'label');
                    $lowerLabels = array_map('strtolower', $labels);

                    // Common yes/no patterns
                    $yesNoPatterns = [
                        ['yes', 'no'],
                        ['enabled', 'disabled'],
                        ['true', 'false'],
                        ['1', '0']
                    ];

                    foreach ($yesNoPatterns as $pattern) {
                        if (array_intersect($pattern, $lowerLabels) === $pattern) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function parse(string $attributeCode, Product $product): string
    {
        $value = $this->getProductData($product, $attributeCode);

        if ($value === null || $value === '') {
            return 'No';
        }

        $frontendInput = $this->getFrontendInput($attributeCode);

        // Handle boolean input type
        if ($frontendInput === 'boolean') {
            return $this->convertBooleanValue($value);
        }

        // Handle select-based yes/no attributes
        if ($frontendInput === 'select') {
            try {
                $attribute = $this->getAttribute($attributeCode);
                if ($attribute && $attribute->getSource()) {
                    $optionText = $attribute->getSource()->getOptionText($value);
                    if ($optionText && $optionText !== false) {
                        return (string) $optionText;
                    }
                }
            } catch (\Exception $e) {
                $this->logger->debug(
                    'Error getting option text for boolean-like select: ' . $attributeCode,
                    ['exception' => $e->getMessage()]
                );
            }
        }

        // Fallback to boolean conversion
        return $this->convertBooleanValue($value);
    }

    /**
     * Convert various boolean representations to Yes/No
     *
     * @param mixed $value
     * @return string
     */
    private function convertBooleanValue($value): string
    {
        // Handle numeric boolean
        if (is_numeric($value)) {
            return (int) $value === 1 ? 'Yes' : 'No';
        }

        // Handle string boolean
        if (is_string($value)) {
            $lowerValue = strtolower(trim($value));
            $trueValues = ['yes', 'true', '1', 'enabled', 'on'];
            return in_array($lowerValue, $trueValues, true) ? 'Yes' : 'No';
        }

        // Handle actual boolean
        return $value ? 'Yes' : 'No';
    }
}
