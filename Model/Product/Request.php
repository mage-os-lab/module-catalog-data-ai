<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\Product;

/**
 * Data model for enrichment message queue.
 */
class Request
{
    public function __construct(
        private readonly int $id,
        private readonly bool $overwrite
    ) {
    }

    /**
     * Retrieve products id.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Retrieve overwrite flag.
     */
    public function getOverwrite(): bool
    {
        return $this->overwrite;
    }
}
