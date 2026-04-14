<?php

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Observer\Product;

use Magento\Catalog\Model\Product;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use MageOS\CatalogDataAI\Model\Config;
use MageOS\CatalogDataAI\Model\Product\EnrichmentRecorder;
use MageOS\CatalogDataAI\Model\Product\Publisher;

class SaveAfter implements ObserverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly Publisher $publisher,
        private readonly EnrichmentRecorder $enrichmentRecorder
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var Product $product */
        $product = $observer->getProduct();

        $this->persistDeferredEnrichments($product);

        if ($this->config->canEnrich($product) && $this->config->isAsync()) {
            $this->publisher->execute(
                $product->getId(),
                false,
                (int)$product->getStoreId()
            );
        }
    }

    /**
     * Persist enrichment records that were deferred because the product had no ID yet.
     */
    private function persistDeferredEnrichments(Product $product): void
    {
        $deferred = $product->getData('mageos_catalogai_deferred_enrichments');
        if (!is_array($deferred) || !$product->getId()) {
            return;
        }

        $productId = (int) $product->getId();
        foreach ($deferred as $item) {
            $this->enrichmentRecorder->record(
                $productId,
                (int) $item['store_id'],
                $item['attribute_code'],
                $item['prompt_hash'],
                $item['parsed_prompt'],
                $item['generated_value']
            );
        }

        $product->unsetData('mageos_catalogai_deferred_enrichments');
    }
}
