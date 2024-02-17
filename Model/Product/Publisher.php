<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\Product;
use Magento\Framework\MessageQueue\PublisherInterface;

class Publisher
{
    const TOPIC_NAME = 'mageos.product.enrich';

    /**
     * Publisher constructor.
     * @param Publisher $publisher
     */
    public function __construct
    (
        private PublisherInterface $publisher
    ) {}

    /**
     * @param data
     */
    public function execute(int|string $productId)
    {
        $this->publisher->publish(self::TOPIC_NAME, (int)$productId);
    }
}
