<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\Product;

use Magento\Framework\MessageQueue\PublisherInterface;
use MageOS\CatalogDataAI\Model\Product\RequestFactory;

class Publisher
{
    const TOPIC_NAME = 'mageos.product.enrich';

    /**
     * Publisher constructor.
     */
    public function __construct(
        private readonly PublisherInterface $publisher,
        private readonly RequestFactory     $requestFactory,
    ) {}

    /**
     * @param int|string $productId
     * @param bool $overwrite
     */
    public function execute(int|string $productId, bool $overwrite = false): void
    {
        $request = $this->requestFactory->create([
            'id' => (int)$productId,
            'overwrite' => $overwrite
        ]);
        $this->publisher->publish(self::TOPIC_NAME, $request);
    }
}
