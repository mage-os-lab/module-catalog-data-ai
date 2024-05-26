<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Controller\Adminhtml\Product;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Action\HttpPostActionInterface as HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter;
use MageOS\CatalogDataAI\Model\Config;
use MageOS\CatalogDataAI\Model\Product\Publisher;

class MassEnrich extends Action implements HttpPostActionInterface
{
    protected $overwrite = true;
    public function __construct(
        Context                                     $context,
        private readonly Filter                     $filter,
        private readonly CollectionFactory          $collectionFactory,
        private readonly Config                     $config,
        private readonly Publisher                  $publisher,
        private readonly ProductRepositoryInterface $productRepository,
    ) {
        parent::__construct($context);
    }

    /**
     * Mass Delete Action
     *
     * @return Redirect
     * @throws LocalizedException
     */
    public function execute(): Redirect
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());

        $productEnriched = 0;
        if($this->config->isEnabled()) {
            /** @var Product $product */
            foreach ($collection->getItems() as $product) {
                //@TODO: we hit rate limit, change to batching the request
                $this->publisher->execute($product->getId(), $this->overwrite);
                $productEnriched++;
            }

            if ($productEnriched) {
                $this->messageManager->addSuccessMessage(
                    __('A total of %1 record(s) are scheduled to get data enriched.', $productEnriched)
                );
            }
        }
        else {
            $this->messageManager->addErrorMessage(
                __('Data enrichment is disabled. Please enable it in the configuration.')
            );
        }

        return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('catalog/*/index');
    }
}
