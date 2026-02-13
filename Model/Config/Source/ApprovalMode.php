<?php

/**
 * Copyright © 2025 MageOS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ApprovalMode implements OptionSourceInterface
{
    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => '0', 'label' => __('Auto-Approve (apply immediately)')],
            ['value' => '1', 'label' => __('Require Approval (hold for review)')],
        ];
    }
}
