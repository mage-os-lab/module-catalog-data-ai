<?php

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\Product;

use MageOS\CatalogDataAI\Api\RequestInterface;

/**
 * Data model for enrichment message queue.
 */
class Request implements RequestInterface
{
    public function __construct(
        private readonly int $id,
        private readonly bool $overwrite,
        private readonly int $storeId = 0
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getOverwrite(): bool
    {
        return $this->overwrite;
    }

    public function getStoreId(): int
    {
        return $this->storeId;
    }
}
