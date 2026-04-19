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
use MageOS\CatalogDataAI\Api\Data\EnrichmentInterface;
use MageOS\CatalogDataAI\Api\EnrichmentRepositoryInterface;
use MageOS\CatalogDataAI\Service\EnrichmentApplier;

class Save extends Action
{
    public const ADMIN_RESOURCE = 'MageOS_CatalogDataAI::enrichment_review';

    /**
     * @param Context $context
     * @param EnrichmentRepositoryInterface $enrichmentRepository
     * @param EnrichmentApplier $enrichmentApplier
     */
    public function __construct(
        Context $context,
        private readonly EnrichmentRepositoryInterface $enrichmentRepository,
        private readonly EnrichmentApplier $enrichmentApplier
    ) {
        parent::__construct($context);
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $data = $this->getRequest()->getPostValue();
        $id = (int) ($data['entity_id'] ?? 0);

        if (!$id) {
            $this->messageManager->addErrorMessage(__('Invalid enrichment record.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }

        try {
            $enrichment = $this->enrichmentRepository->getById($id);

            if (isset($data['applied_value'])) {
                $enrichment->setAppliedValue($data['applied_value'] !== '' ? $data['applied_value'] : null);
            }
            if (isset($data['admin_notes'])) {
                $enrichment->setAdminNotes($data['admin_notes'] !== '' ? $data['admin_notes'] : null);
            }

            $newStatus = $data['status'] ?? $enrichment->getStatus();
            $allowedStatuses = [
                EnrichmentInterface::STATUS_PENDING,
                EnrichmentInterface::STATUS_APPROVED,
                EnrichmentInterface::STATUS_DENIED,
                EnrichmentInterface::STATUS_APPLIED,
            ];
            if (!in_array($newStatus, $allowedStatuses, true)) {
                $this->messageManager->addErrorMessage(__('Invalid status value.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/edit', ['id' => $id]);
            }

            $shouldApply = (
                $newStatus === EnrichmentInterface::STATUS_APPROVED
                    || $newStatus === EnrichmentInterface::STATUS_APPLIED
            ) && $enrichment->getStatus() !== EnrichmentInterface::STATUS_APPLIED;

            if ($shouldApply) {
                $this->enrichmentApplier->apply($enrichment);
                $this->messageManager->addSuccessMessage(
                    __('Enrichment approved and applied to product.')
                );
            } else {
                $enrichment->setStatus($newStatus);
                $this->enrichmentRepository->save($enrichment);
                $this->messageManager->addSuccessMessage(__('Enrichment record saved.'));
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $this->resultRedirectFactory->create()->setPath('*/*/edit', ['id' => $id]);
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
