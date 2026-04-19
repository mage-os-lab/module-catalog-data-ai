<?php

/**
 * Copyright © 2025 MageOS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Ui\Component\Listing\Column\Status;

use Magento\Framework\Data\OptionSourceInterface;
use MageOS\CatalogDataAI\Api\Data\EnrichmentInterface;

class Options implements OptionSourceInterface
{
    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => EnrichmentInterface::STATUS_PENDING, 'label' => __('Pending')],
            ['value' => EnrichmentInterface::STATUS_APPROVED, 'label' => __('Approved')],
            ['value' => EnrichmentInterface::STATUS_DENIED, 'label' => __('Denied')],
            ['value' => EnrichmentInterface::STATUS_APPLIED, 'label' => __('Applied')],
        ];
    }
}
