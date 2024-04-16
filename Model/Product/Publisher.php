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
     * @param Publisher $publisher
     */
    public function __construct
    (
        private PublisherInterface $publisher,
        private RequestFactory $requestFactory,
    ) {}

    /**
     * @param data
     */
    public function execute(int|string $productId, $overwrite = false)
    {
        $request = $this->requestFactory->create([
            'id' => (int)$productId,
            'overwrite' => $overwrite
        ]);
        $this->publisher->publish(self::TOPIC_NAME, $request);
    }
}
