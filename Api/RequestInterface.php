<?php

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Api;

interface RequestInterface
{
    /**
     * Retrieve product id.
     * @return int
     */
    public function getId(): int;

    /**
     * Retrieve overwrite flag.
     * @return bool
     */
    public function getOverwrite(): bool;

    /**
     * Retrieve store id.
     * @return int
     */
    public function getStoreId(): int;
}
