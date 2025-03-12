<?php

namespace MageOS\CatalogDataAI\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use OpenAI;
use OpenAI\Client as OpenAIClient;
use MageOS\CatalogDataAI\Model\Config as ModuleConfig;
use Exception;

class OpenAIModel implements OptionSourceInterface
{
    /**
     * @var OpenAIClient|null
     */
    protected ?OpenAIClient $openAIclient = null;
    /**
     * @var OpenAI
     */
    protected OpenAI $openAI;
    /**
     * @var ModuleConfig
     */
    protected ModuleConfig $moduleConfig;

    /**
     * @param OpenAI $openAI
     * @param ModuleConfig $moduleConfig
     */
    public function __construct(
        OpenAI $openAI,
        ModuleConfig $moduleConfig
    ) {
        $this->openAI = $openAI;
        $this->moduleConfig = $moduleConfig;
    }

    /**
     * @return void
     */
    protected function initClient()
    {
        $apiKey = $this->moduleConfig->getApiKey();
        $organization = $this->moduleConfig->getOrganizationID();
        $projectId = $this->moduleConfig->getProjectId();

        $this->openAIclient = $this->openAI::client($apiKey, $organization, !empty($projectId) ? $projectId : null);
    }

    /**
     * @return array[]
     */
    public function toOptionArray(): array
    {
        $optionArray = [['value' => '', 'label' => __('-- Please Select --')]];

        if (!empty($this->moduleConfig->getApiKey())) {
            try {
                if (empty($this->openAIclient)) {
                    $this->initClient();
                }

                $models = $this->openAIclient->models()->list()->toArray();

                foreach ($models['data'] as $model) {
                    $optionArray[] = [
                        'value' => $model['id'],
                        'label' => $model['id']
                    ];
                }
            } catch (Exception $e) {
                return $optionArray;
            }

        }

        return $optionArray;
    }
}
