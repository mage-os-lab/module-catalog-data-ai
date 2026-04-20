<?php

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Ui\DataProvider\PromptRule\Form\Modifier;

use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\Modifier\ModifierInterface;
use MageOS\CatalogDataAI\Model\PromptRuleFactory;
use MageOS\CatalogDataAI\Model\ResourceModel\PromptRule as PromptRuleResource;

class Conditions implements ModifierInterface
{
    public function __construct(
        private readonly PromptRuleFactory $ruleFactory,
        private readonly PromptRuleResource $ruleResource,
        private readonly RequestInterface $request
    ) {
    }

    public function modifyData(array $data): array
    {
        $ruleId = (int)$this->request->getParam('rule_id');
        if ($ruleId && isset($data[$ruleId])) {
            $rule = $this->ruleFactory->create();
            $this->ruleResource->load($rule, $ruleId);

            $conditions = $rule->getConditions()->asArray();
            $data[$ruleId]['rule']['conditions'] = $this->convertConditions($conditions);
        }

        return $data;
    }

    public function modifyMeta(array $meta): array
    {
        $meta['conditions_fieldset'] = [
            'arguments' => [
                'data' => [
                    'config' => [
                        'label' => __('Conditions'),
                        'componentType' => 'fieldset',
                        'collapsible' => true,
                        'sortOrder' => 20,
                    ],
                ],
            ],
            'children' => [
                'conditions_notice' => [
                    'arguments' => [
                        'data' => [
                            'config' => [
                                'componentType' => 'container',
                                'component' => 'Magento_Ui/js/form/components/html',
                                'content' => (string)__(
                                    'Define product conditions that must match for this rule to apply. '
                                    . 'If no conditions are set, the rule applies to all products.'
                                ),
                            ],
                        ],
                    ],
                ],
            ],
        ];

        return $meta;
    }

    private function convertConditions(array $conditions): array
    {
        $result = [];
        if (isset($conditions['type'])) {
            $result['type'] = $conditions['type'];
            $result['attribute'] = $conditions['attribute'] ?? '';
            $result['operator'] = $conditions['operator'] ?? '';
            $result['value'] = $conditions['value'] ?? '';
            $result['aggregator'] = $conditions['aggregator'] ?? 'all';
            if (isset($conditions['conditions'])) {
                foreach ($conditions['conditions'] as $key => $condition) {
                    $result['conditions'][$key] = $this->convertConditions($condition);
                }
            }
        }
        return $result;
    }
}
