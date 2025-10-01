<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\Parser;

use Magento\Catalog\Model\Product;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Parser for multiselect attributes
 *
 * Converts multiple option IDs to comma-separated labels for better AI prompt context
 */
class MultiselectParser extends AbstractParser
{
    protected const DEFAULT_PRIORITY = 60;

    public function __construct(
        AttributeRepositoryInterface $attributeRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($attributeRepository, $logger);
    }

    /**
     * {@inheritdoc}
     */
    public function canParse(string $attributeCode, Product $product): bool
    {
        $frontendInput = $this->getFrontendInput($attributeCode);
        return $frontendInput === 'multiselect';
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

        try {
            $attribute = $this->getAttribute($attributeCode);
            if (!$attribute) {
                return (string) $value;
            }

            // Handle comma-separated string values
            $optionIds = is_string($value) ? explode(',', $value) : (array) $value;
            $optionIds = array_filter(array_map('trim', $optionIds));

            if (empty($optionIds)) {
                return '';
            }

            $labels = [];
            $source = $attribute->getSource();

            foreach ($optionIds as $optionId) {
                if ($source) {
                    $optionText = $source->getOptionText($optionId);
                    if ($optionText && $optionText !== false) {
                        $labels[] = (string) $optionText;
                    } else {
                        // Fallback to option ID if text not found
                        $labels[] = (string) $optionId;
                    }
                } else {
                    $labels[] = (string) $optionId;
                }
            }

            // If we couldn't get any labels via source, try frontend approach
            if (empty($labels) && $source) {
                $frontend = $attribute->getFrontend();
                if ($frontend) {
                    $displayValue = $frontend->getValue($product);
                    if ($displayValue && is_string($displayValue)) {
                        return trim($displayValue);
                    }
                }
            }

            return implode(', ', array_filter($labels));

        } catch (\Exception $e) {
            $this->logger->warning(
                'Error parsing multiselect attribute: ' . $attributeCode,
                [
                    'product_id' => $product->getId(),
                    'value' => $value,
                    'exception' => $e->getMessage()
                ]
            );

            // Fallback to raw value
            return (string) $value;
        }
    }
}
