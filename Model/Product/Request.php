<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\Product;

/**
 * Data model for enrichment message queue.
 */
class Request
{
    public function __construct(
        private int $id,
        private bool $overwrite
    ) {}

    /**
     * Retrieve products id.
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Retrieve overwrite flag.
     * @return bool
     */
    public function getOverwrite(): bool
    {
        return $this->overwrite;
    }
}
