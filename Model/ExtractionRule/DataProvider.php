<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\ExtractionRule;

use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use MageOS\CatalogDataAI\Model\ExtractionRuleFactory;
use MageOS\CatalogDataAI\Model\ResourceModel\ExtractionRule as ExtractionRuleResource;
use MageOS\CatalogDataAI\Model\ResourceModel\ExtractionRule\CollectionFactory;

class DataProvider extends AbstractDataProvider
{
    private array $loadedData = [];

    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly ExtractionRuleFactory $ruleFactory,
        private readonly ExtractionRuleResource $ruleResource,
        private readonly RequestInterface $request,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData(): array
    {
        if (!empty($this->loadedData)) {
            return $this->loadedData;
        }

        $ruleId = (int)$this->request->getParam('rule_id');
        if ($ruleId) {
            $rule = $this->ruleFactory->create();
            $this->ruleResource->load($rule, $ruleId);

            if ($rule->getId()) {
                $data = $rule->getData();
                if (isset($data['store_ids']) && is_string($data['store_ids'])) {
                    $data['store_ids'] = explode(',', $data['store_ids']);
                }
                if (isset($data['source_attributes']) && is_string($data['source_attributes'])) {
                    $data['source_attributes'] = explode(',', $data['source_attributes']);
                }
                $this->loadedData[$ruleId] = $data;
            }
        }

        return $this->loadedData;
    }
}
