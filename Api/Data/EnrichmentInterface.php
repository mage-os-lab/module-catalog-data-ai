<?php

/**
 * Copyright © 2025 MageOS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Api\Data;

interface EnrichmentInterface
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_DENIED = 'denied';
    public const STATUS_APPLIED = 'applied';

    /**
     * @return int|null
     */
    public function getEntityId(): ?int;

    /**
     * @param int $entityId
     * @return $this
     */
    public function setEntityId(int $entityId): self;

    /**
     * @return int
     */
    public function getProductId(): int;

    /**
     * @param int $productId
     * @return $this
     */
    public function setProductId(int $productId): self;

    /**
     * @return int
     */
    public function getStoreId(): int;

    /**
     * @param int $storeId
     * @return $this
     */
    public function setStoreId(int $storeId): self;

    /**
     * @return string
     */
    public function getAttributeCode(): string;

    /**
     * @param string $attributeCode
     * @return $this
     */
    public function setAttributeCode(string $attributeCode): self;

    /**
     * @return string
     */
    public function getPromptHash(): string;

    /**
     * @param string $promptHash
     * @return $this
     */
    public function setPromptHash(string $promptHash): self;

    /**
     * @return string|null
     */
    public function getParsedPrompt(): ?string;

    /**
     * @param string|null $parsedPrompt
     * @return $this
     */
    public function setParsedPrompt(?string $parsedPrompt): self;

    /**
     * @return string|null
     */
    public function getGeneratedValue(): ?string;

    /**
     * @param string|null $generatedValue
     * @return $this
     */
    public function setGeneratedValue(?string $generatedValue): self;

    /**
     * @return string|null
     */
    public function getAppliedValue(): ?string;

    /**
     * @param string|null $appliedValue
     * @return $this
     */
    public function setAppliedValue(?string $appliedValue): self;

    /**
     * @return string
     */
    public function getStatus(): string;

    /**
     * @param string $status
     * @return $this
     */
    public function setStatus(string $status): self;

    /**
     * @return string|null
     */
    public function getAdminNotes(): ?string;

    /**
     * @param string|null $adminNotes
     * @return $this
     */
    public function setAdminNotes(?string $adminNotes): self;

    /**
     * @return string
     */
    public function getCreatedAt(): string;

    /**
     * @param string $createdAt
     * @return $this
     */
    public function setCreatedAt(string $createdAt): self;

    /**
     * @return string
     */
    public function getUpdatedAt(): string;

    /**
     * @param string $updatedAt
     * @return $this
     */
    public function setUpdatedAt(string $updatedAt): self;
}
