<?php

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Controller\Adminhtml\Product;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\Component\MassAction\Filter;
use MageOS\CatalogDataAI\Model\Config;
use MageOS\CatalogDataAI\Model\Product\Publisher;

class MassExtract extends Action implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly Config $config,
        private readonly Publisher $publisher,
        private readonly StoreManagerInterface $storeManager,
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $scheduled = 0;

        if ($this->config->isEnabled()) {
            $storeId = (int)$this->storeManager->getStore()->getId();
            foreach ($collection->getItems() as $product) {
                $this->publisher->execute($product->getId(), false, $storeId);
                $scheduled++;
            }
            $this->messageManager->addSuccessMessage(
                __('A total of %1 product(s) are scheduled for attribute extraction.', $scheduled)
            );
        } else {
            $this->messageManager->addErrorMessage(
                __('Data enrichment is disabled. Please enable it in the configuration.')
            );
        }

        return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('catalog/*/index');
    }
}
