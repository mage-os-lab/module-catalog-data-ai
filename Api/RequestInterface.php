<?php
/**
 * Copyright Elgentos BV. All rights reserved.
 * https://www.elgentos.nl/
 */

namespace MageOS\CatalogDataAI\Api;

interface RequestInterface
{
    /**
     * Retrieve products id.
     * @return int
     */
    public function getId(): int;

    /**
     * Retrieve overwrite flag.
     * @return bool
     */
    public function getOverwrite(): bool;
}
