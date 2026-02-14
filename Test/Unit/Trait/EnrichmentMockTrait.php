<?php

/**
 * Copyright © 2025 Mage-OS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Trait;

use MageOS\CatalogDataAI\Api\Data\EnrichmentInterface;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Provides enrichment mock creation helpers for unit tests.
 */
trait EnrichmentMockTrait
{
    /**
     * Create an EnrichmentInterface mock with configurable getter stubs.
     *
     * Supported keys: entity_id, product_id, store_id, attribute_code, prompt_hash,
     * parsed_prompt, generated_value, applied_value, status, admin_notes
     *
     * @param array<string, mixed> $data
     * @return EnrichmentInterface&MockObject
     */
    protected function createEnrichmentMock(array $data = []): EnrichmentInterface&MockObject
    {
        $enrichment = $this->createMock(EnrichmentInterface::class);

        $getterMap = [
            'entity_id' => 'getEntityId',
            'product_id' => 'getProductId',
            'store_id' => 'getStoreId',
            'attribute_code' => 'getAttributeCode',
            'prompt_hash' => 'getPromptHash',
            'parsed_prompt' => 'getParsedPrompt',
            'generated_value' => 'getGeneratedValue',
            'applied_value' => 'getAppliedValue',
            'status' => 'getStatus',
            'admin_notes' => 'getAdminNotes',
        ];

        foreach ($getterMap as $key => $getter) {
            if (array_key_exists($key, $data)) {
                $enrichment->method($getter)->willReturn($data[$key]);
            }
        }

        return $enrichment;
    }

    /**
     * @param int $id
     * @return EnrichmentInterface&MockObject
     */
    protected function createPendingEnrichment(int $id = 1): EnrichmentInterface&MockObject
    {
        return $this->createEnrichmentMock([
            'entity_id' => $id,
            'status' => EnrichmentInterface::STATUS_PENDING,
        ]);
    }

    /**
     * @param int $id
     * @return EnrichmentInterface&MockObject
     */
    protected function createAppliedEnrichment(int $id = 1): EnrichmentInterface&MockObject
    {
        return $this->createEnrichmentMock([
            'entity_id' => $id,
            'status' => EnrichmentInterface::STATUS_APPLIED,
        ]);
    }
}
