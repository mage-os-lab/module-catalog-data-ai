<?php

/**
 * Copyright © 2025 MageOS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Controller\Adminhtml\Enrichment;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter;
use MageOS\CatalogDataAI\Api\Data\EnrichmentInterface;
use MageOS\CatalogDataAI\Api\EnrichmentRepositoryInterface;
use MageOS\CatalogDataAI\Model\ResourceModel\Enrichment\CollectionFactory;

class MassDeny extends Action
{
    public const ADMIN_RESOURCE = 'MageOS_CatalogDataAI::enrichment_review';

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param EnrichmentRepositoryInterface $enrichmentRepository
     */
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly EnrichmentRepositoryInterface $enrichmentRepository
    ) {
        parent::__construct($context);
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $count = 0;

        foreach ($collection as $enrichment) {
            /** @var EnrichmentInterface $enrichment */
            try {
                $enrichment->setStatus(EnrichmentInterface::STATUS_DENIED);
                $this->enrichmentRepository->save($enrichment);
                $count++;
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage(
                    __('Error denying enrichment #%1: %2', $enrichment->getEntityId(), $e->getMessage())
                );
            }
        }

        if ($count) {
            $this->messageManager->addSuccessMessage(
                __('A total of %1 enrichment(s) have been denied.', $count)
            );
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
