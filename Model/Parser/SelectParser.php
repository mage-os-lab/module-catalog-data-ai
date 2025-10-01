<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\Parser;

use Magento\Catalog\Model\Product;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Parser for select attributes
 *
 * Converts option IDs to their corresponding labels for better AI prompt context
 */
class SelectParser extends AbstractParser
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
        return $frontendInput === 'select';
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

            // Get the option text/label for the value
            $source = $attribute->getSource();
            if ($source) {
                $optionText = $source->getOptionText($value);
                if ($optionText && $optionText !== false) {
                    return (string) $optionText;
                }
            }

            // If we can't get the option text, try getting it from frontend
            $frontend = $attribute->getFrontend();
            if ($frontend) {
                $displayValue = $frontend->getValue($product);
                if ($displayValue && is_string($displayValue)) {
                    return trim($displayValue);
                }
            }

            // Fallback to raw value
            return (string) $value;

        } catch (\Exception $e) {
            $this->logger->warning(
                'Error parsing select attribute: ' . $attributeCode,
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
