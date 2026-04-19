<?php

/**
 * Copyright © 2025 MageOS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Controller\Adminhtml\Enrichment;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use MageOS\CatalogDataAI\Api\EnrichmentRepositoryInterface;

class Edit extends Action
{
    public const ADMIN_RESOURCE = 'MageOS_CatalogDataAI::enrichment_review';

    /**
     * @param Context $context
     * @param EnrichmentRepositoryInterface $enrichmentRepository
     */
    public function __construct(
        Context $context,
        private readonly EnrichmentRepositoryInterface $enrichmentRepository
    ) {
        parent::__construct($context);
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $id = (int) $this->getRequest()->getParam('id');

        try {
            $enrichment = $this->enrichmentRepository->getById($id);
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('This enrichment record no longer exists.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }

        /** @var Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('MageOS_CatalogDataAI::enrichment_review');
        $resultPage->getConfig()->getTitle()->prepend(
            (string) __('Enrichment #%1', $enrichment->getEntityId())
        );

        return $resultPage;
    }
}
