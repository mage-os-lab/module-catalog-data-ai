<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\Product;

use Magento\Catalog\Model\ProductRepository;
use Magento\Store\Model\StoreManagerInterface;

class Consumer
{
    public function __construct(
        private readonly Enricher              $enricher,
        private readonly ProductRepository     $productRepository,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function execute(Request $request): void
    {
        $this->storeManager->setCurrentStore($request->getStoreId());
        $product = $this->productRepository->getById(
            $request->getId(),
            false,
            $request->getStoreId()
        );
        $product->setData('mageos_catalogai_overwrite', $request->getOverwrite());
        $this->enricher->execute($product);
        $this->productRepository->save($product);
    }
}
