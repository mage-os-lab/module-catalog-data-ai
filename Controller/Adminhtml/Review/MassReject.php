<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Controller\Adminhtml\Review;

use Magento\Backend\App\Action;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;
use MageOS\CatalogDataAI\Model\Product\EnrichmentLogger;
use MageOS\CatalogDataAI\Model\ResourceModel\EnrichmentLog\CollectionFactory;

class MassReject extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_CatalogDataAI::enrichment_review';

    public function __construct(
        Action\Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly EnrichmentLogger $enrichmentLogger,
        private readonly ProductRepositoryInterface $productRepository
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $rejected = 0;

        foreach ($collection as $logEntry) {
            $log = $this->enrichmentLogger->reject((int)$logEntry->getId());
            if ($log && $log->getData('original_content') !== null) {
                $product = $this->productRepository->getById(
                    (int)$log->getData('entity_id'),
                    true,
                    (int)$log->getData('store_id')
                );
                $product->setData($log->getData('attribute_code'), $log->getData('original_content'));
                $this->productRepository->save($product);
            }
            $rejected++;
        }

        $this->messageManager->addSuccessMessage(
            __('A total of %1 entry(ies) have been rejected.', $rejected)
        );
        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
