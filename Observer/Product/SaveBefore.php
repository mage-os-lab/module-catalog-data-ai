<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Observer\Product;

use Magento\Catalog\Model\Product;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use MageOS\CatalogDataAI\Model\Config;
use MageOS\CatalogDataAI\Model\Product\Enricher;
use MageOS\CatalogDataAI\Model\Product\EnrichmentLogger;

class SaveBefore implements ObserverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly Enricher $enricher,
        private readonly EnrichmentLogger $enrichmentLogger
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var Product $product */
        $product = $observer->getProduct();

        if ($this->config->canEnrich($product) && !$this->config->isAsync()) {
            $this->enricher->execute($product);
            return;
        }

        if (!$product->isObjectNew() && $product->getId()) {
            $this->detectManualEdits($product);
        }
    }

    private function detectManualEdits(Product $product): void
    {
        $storeId = (int)$product->getStoreId();
        $enrichableAttributes = array_keys($this->config->getEnrichableAttributes());

        foreach ($enrichableAttributes as $attributeCode) {
            if (!$product->dataHasChangedFor($attributeCode)) {
                continue;
            }

            $this->enrichmentLogger->markModified(
                (int)$product->getId(),
                $attributeCode,
                $storeId
            );
        }
    }
}
