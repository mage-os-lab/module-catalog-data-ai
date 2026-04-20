<?php

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\Product;

use Magento\Framework\MessageQueue\PublisherInterface;

class Publisher
{
    public const TOPIC_NAME = 'mageos.product.enrich';

    /**
     * Publisher constructor.
     */
    public function __construct(
        private readonly PublisherInterface $publisher,
        private readonly RequestFactory     $requestFactory,
    ) {
    }

    public function execute(int|string $productId, bool $overwrite = false, int $storeId = 0): void
    {
        $request = $this->requestFactory->create([
            'id' => (int)$productId,
            'overwrite' => $overwrite,
            'storeId' => $storeId,
        ]);
        $this->publisher->publish(self::TOPIC_NAME, $request);
    }
}
