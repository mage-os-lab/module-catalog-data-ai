<?php

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Ui\DataProvider\Product\Form\Modifier;

use Magento\Backend\Model\UrlInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Locator\LocatorInterface;
use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AbstractModifier;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Stdlib\ArrayManager;
use MageOS\CatalogDataAI\Api\Data\EnrichmentInterface;
use MageOS\CatalogDataAI\Api\EnrichmentRepositoryInterface;
use MageOS\CatalogDataAI\Model\Config;

class EnrichmentStatus extends AbstractModifier
{
    private const STATUS_LABELS = [
        'pending'  => 'AI-generated &middot; Pending review',
        'approved' => 'AI-generated &middot; Approved',
        'applied'  => 'AI-generated &middot; Approved',
        'modified' => 'AI-generated &middot; Modified',
        'denied'   => 'AI-generated &middot; Denied',
    ];

    private const STATUS_CSS_MAP = [
        'pending'  => 'pending',
        'approved' => 'approved',
        'applied'  => 'approved',
        'modified' => 'modified',
        'denied'   => 'denied',
    ];

    public function __construct(
        private readonly LocatorInterface $locator,
        private readonly EnrichmentRepositoryInterface $enrichmentRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly Config $config,
        private readonly UrlInterface $urlBuilder,
        private readonly ArrayManager $arrayManager
    ) {
    }

    public function modifyData(array $data): array
    {
        return $data;
    }

    public function modifyMeta(array $meta): array
    {
        if (!$this->config->isCacheEnabled()) {
            return $meta;
        }

        $product = $this->locator->getProduct();
        $productId = $product->getId();

        if (!$productId) {
            return $meta;
        }

        $storeId = (int) $this->locator->getStore()->getId();
        $enrichments = $this->getEnrichmentsByProduct((int) $productId, $storeId);

        if (empty($enrichments)) {
            return $meta;
        }

        foreach ($enrichments as $attributeCode => $enrichment) {
            $meta = $this->injectEnrichmentNote($meta, $attributeCode, $enrichment, $product);
        }

        return $meta;
    }

    /**
     * @return array<string, EnrichmentInterface>
     */
    private function getEnrichmentsByProduct(int $productId, int $storeId): array
    {
        $this->searchCriteriaBuilder->addFilter('product_id', $productId);
        $this->searchCriteriaBuilder->addFilter('store_id', $storeId);
        $searchCriteria = $this->searchCriteriaBuilder->create();

        $results = $this->enrichmentRepository->getList($searchCriteria);
        $enrichments = [];

        foreach ($results->getItems() as $enrichment) {
            $code = $enrichment->getAttributeCode();
            if (!isset($enrichments[$code])
                || $enrichment->getUpdatedAt() > $enrichments[$code]->getUpdatedAt()
            ) {
                $enrichments[$code] = $enrichment;
            }
        }

        return $enrichments;
    }

    private function injectEnrichmentNote(
        array $meta,
        string $attributeCode,
        EnrichmentInterface $enrichment,
        ProductInterface $product
    ): array {
        $fieldPath = $this->arrayManager->findPath($attributeCode, $meta, null, 'children');

        if (!$fieldPath) {
            return $meta;
        }

        $displayStatus = $this->resolveDisplayStatus($enrichment, $product);
        $html = $this->buildNoteHtml($enrichment, $displayStatus);
        $configPath = $fieldPath . '/arguments/data/config';

        return $this->arrayManager->merge($configPath, $meta, ['additionalInfo' => $html]);
    }

    private function resolveDisplayStatus(EnrichmentInterface $enrichment, ProductInterface $product): string
    {
        $status = $enrichment->getStatus();

        if ($status === EnrichmentInterface::STATUS_PENDING || $status === EnrichmentInterface::STATUS_DENIED) {
            return $status;
        }

        $currentValue = (string) $product->getData($enrichment->getAttributeCode());
        $enrichmentValue = $enrichment->getAppliedValue() ?? $enrichment->getGeneratedValue();

        if ($currentValue !== (string) $enrichmentValue) {
            return 'modified';
        }

        return $status;
    }

    private function buildNoteHtml(EnrichmentInterface $enrichment, string $displayStatus): string
    {
        $label = self::STATUS_LABELS[$displayStatus] ?? self::STATUS_LABELS['pending'];
        $cssClass = self::STATUS_CSS_MAP[$displayStatus] ?? 'pending';
        $editUrl = $this->urlBuilder->getUrl(
            'catalogai/enrichment/edit',
            ['id' => $enrichment->getEntityId()]
        );

        return sprintf(
            '<span class="catalogai-enrichment-note catalogai-enrichment-note--%s">'
            . '%s &mdash; <a href="%s" target="_blank">%s</a>'
            . '</span>',
            $cssClass,
            $label,
            htmlspecialchars($editUrl, ENT_QUOTES),
            'View details'
        );
    }
}
