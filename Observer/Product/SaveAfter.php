<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Observer\Product;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use MageOS\CatalogDataAI\Model\Config;
use MageOS\CatalogDataAI\Model\Product\Publisher;

class SaveAfter implements ObserverInterface
{
    public function __construct(
        private Config $config,
        private Publisher $publisher
    ) {}

    public function execute(Observer $observer): void
    {
        /** @var \Magento\Catalog\Model\Product $product */
        $product = $observer->getProduct();

        if($this->config->canEnrich($product) && $this->config->isAsync()) {
            $this->publisher->execute($product->getId(), false);
        }
    }
}
