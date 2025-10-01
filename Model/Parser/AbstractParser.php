<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\Parser;

use MageOS\CatalogDataAI\Api\ParserInterface;
use Magento\Catalog\Model\Product;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

/**
 * Abstract base class for attribute parsers
 *
 * Provides common functionality and attribute metadata access
 */
abstract class AbstractParser implements ParserInterface
{
    protected const DEFAULT_PRIORITY = 10;

    public function __construct(
        protected readonly AttributeRepositoryInterface $attributeRepository,
        protected readonly LoggerInterface $logger
    ) {
    }

    /**
     * Get attribute instance for the given attribute code
     *
     * @param string $attributeCode
     * @return \Magento\Eav\Api\Data\AttributeInterface|null
     */
    protected function getAttribute(string $attributeCode): ?\Magento\Eav\Api\Data\AttributeInterface
    {
        try {
            return $this->attributeRepository->get(
                \Magento\Catalog\Api\Data\ProductAttributeInterface::ENTITY_TYPE_CODE,
                $attributeCode
            );
        } catch (NoSuchEntityException $e) {
            $this->logger->debug(
                'Attribute not found: ' . $attributeCode,
                ['exception' => $e->getMessage()]
            );
            return null;
        }
    }

    /**
     * Get attribute frontend input type
     *
     * @param string $attributeCode
     * @return string|null
     */
    protected function getFrontendInput(string $attributeCode): ?string
    {
        $attribute = $this->getAttribute($attributeCode);
        return $attribute ? $attribute->getFrontendInput() : null;
    }

    /**
     * Safely get product data with fallback
     *
     * @param Product $product
     * @param string $attributeCode
     * @param mixed $default
     * @return mixed
     */
    protected function getProductData(Product $product, string $attributeCode, $default = '')
    {
        $value = $product->getData($attributeCode);
        return $value !== null ? $value : $default;
    }

    /**
     * Escape HTML in string values
     *
     * @param string $value
     * @return string
     */
    protected function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority(): int
    {
        return static::DEFAULT_PRIORITY;
    }
}
