<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use MageOS\CatalogDataAI\Model\EnrichmentLog;

class EnrichmentStatus implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => EnrichmentLog::STATUS_GENERATED, 'label' => __('Generated')],
            ['value' => EnrichmentLog::STATUS_PENDING_REVIEW, 'label' => __('Pending Review')],
            ['value' => EnrichmentLog::STATUS_MODIFIED, 'label' => __('Modified')],
            ['value' => EnrichmentLog::STATUS_APPROVED, 'label' => __('Approved')],
            ['value' => EnrichmentLog::STATUS_REJECTED, 'label' => __('Rejected')],
        ];
    }
}
