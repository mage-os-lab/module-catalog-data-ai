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
        private readonly bool $overwrite
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @inheritDoc
     */
    public function getOverwrite(): bool
    {
        return $this->overwrite;
    }
}
