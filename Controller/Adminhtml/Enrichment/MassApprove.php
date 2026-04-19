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
use MageOS\CatalogDataAI\Model\ResourceModel\Enrichment\CollectionFactory;
use MageOS\CatalogDataAI\Service\EnrichmentApplier;

class MassApprove extends Action
{
    public const ADMIN_RESOURCE = 'MageOS_CatalogDataAI::enrichment_review';

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param EnrichmentApplier $enrichmentApplier
     */
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly EnrichmentApplier $enrichmentApplier
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
            if ($enrichment->getStatus() === EnrichmentInterface::STATUS_APPLIED) {
                continue;
            }
            try {
                $this->enrichmentApplier->apply($enrichment);
                $count++;
            } catch (LocalizedException $e) {
                // Only LocalizedException is caught per-record; unexpected throwables
                // are allowed to bubble up and abort the batch so they surface as bugs.
                $this->messageManager->addErrorMessage(
                    __('Error applying enrichment #%1: %2', $enrichment->getEntityId(), $e->getMessage())
                );
            }
        }

        if ($count) {
            $this->messageManager->addSuccessMessage(
                __('A total of %1 enrichment(s) have been approved and applied.', $count)
            );
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
