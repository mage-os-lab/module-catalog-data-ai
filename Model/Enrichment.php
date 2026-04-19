<?php

/**
 * Copyright © 2025 MageOS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model;

use MageOS\CatalogDataAI\Api\Data\EnrichmentInterface;
use Magento\Framework\Model\AbstractModel;

class Enrichment extends AbstractModel implements EnrichmentInterface
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'mageos_catalogai_product_enrichment';

    /**
     * @var string
     */
    protected $_eventObject = 'enrichment';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(ResourceModel\Enrichment::class);
    }

    /**
     * @return int|null
     */
    public function getEntityId(): ?int
    {
        $value = $this->getData('entity_id');
        return $value !== null ? (int) $value : null;
    }

    /**
     * @param int $entityId
     * @return $this
     */
    public function setEntityId($entityId): EnrichmentInterface
    {
        return $this->setData('entity_id', (int) $entityId);
    }

    /**
     * @return int
     */
    public function getProductId(): int
    {
        return (int) $this->getData('product_id');
    }

    /**
     * @param int $productId
     * @return $this
     */
    public function setProductId(int $productId): EnrichmentInterface
    {
        return $this->setData('product_id', $productId);
    }

    /**
     * @return int
     */
    public function getStoreId(): int
    {
        return (int) $this->getData('store_id');
    }

    /**
     * @param int $storeId
     * @return $this
     */
    public function setStoreId(int $storeId): EnrichmentInterface
    {
        return $this->setData('store_id', $storeId);
    }

    /**
     * @return string
     */
    public function getAttributeCode(): string
    {
        return (string) $this->getData('attribute_code');
    }

    /**
     * @param string $attributeCode
     * @return $this
     */
    public function setAttributeCode(string $attributeCode): EnrichmentInterface
    {
        return $this->setData('attribute_code', $attributeCode);
    }

    /**
     * @return string
     */
    public function getPromptHash(): string
    {
        return (string) $this->getData('prompt_hash');
    }

    /**
     * @param string $promptHash
     * @return $this
     */
    public function setPromptHash(string $promptHash): EnrichmentInterface
    {
        return $this->setData('prompt_hash', $promptHash);
    }

    /**
     * @return string|null
     */
    public function getParsedPrompt(): ?string
    {
        return $this->getData('parsed_prompt');
    }

    /**
     * @param string|null $parsedPrompt
     * @return $this
     */
    public function setParsedPrompt(?string $parsedPrompt): EnrichmentInterface
    {
        return $this->setData('parsed_prompt', $parsedPrompt);
    }

    /**
     * @return string|null
     */
    public function getGeneratedValue(): ?string
    {
        return $this->getData('generated_value');
    }

    /**
     * @param string|null $generatedValue
     * @return $this
     */
    public function setGeneratedValue(?string $generatedValue): EnrichmentInterface
    {
        return $this->setData('generated_value', $generatedValue);
    }

    /**
     * @return string|null
     */
    public function getAppliedValue(): ?string
    {
        return $this->getData('applied_value');
    }

    /**
     * @param string|null $appliedValue
     * @return $this
     */
    public function setAppliedValue(?string $appliedValue): EnrichmentInterface
    {
        return $this->setData('applied_value', $appliedValue);
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return (string) $this->getData('status');
    }

    /**
     * @param string $status
     * @return $this
     */
    public function setStatus(string $status): EnrichmentInterface
    {
        return $this->setData('status', $status);
    }

    /**
     * @return string|null
     */
    public function getAdminNotes(): ?string
    {
        return $this->getData('admin_notes');
    }

    /**
     * @param string|null $adminNotes
     * @return $this
     */
    public function setAdminNotes(?string $adminNotes): EnrichmentInterface
    {
        return $this->setData('admin_notes', $adminNotes);
    }

    /**
     * @return string
     */
    public function getCreatedAt(): string
    {
        return (string) $this->getData('created_at');
    }

    /**
     * @param string $createdAt
     * @return $this
     */
    public function setCreatedAt(string $createdAt): EnrichmentInterface
    {
        return $this->setData('created_at', $createdAt);
    }

    /**
     * @return string
     */
    public function getUpdatedAt(): string
    {
        return (string) $this->getData('updated_at');
    }

    /**
     * @param string $updatedAt
     * @return $this
     */
    public function setUpdatedAt(string $updatedAt): EnrichmentInterface
    {
        return $this->setData('updated_at', $updatedAt);
    }
}
