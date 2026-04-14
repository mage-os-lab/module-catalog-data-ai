<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model;

use Magento\Framework\Model\AbstractModel;
use MageOS\CatalogDataAI\Model\ResourceModel\EnrichmentLog as EnrichmentLogResource;

class EnrichmentLog extends AbstractModel
{
    public const STATUS_GENERATED = 'generated';
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_MODIFIED = 'modified';

    protected function _construct(): void
    {
        $this->_init(EnrichmentLogResource::class);
    }
}
