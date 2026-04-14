<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\Product;

use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\Product;
use MageOS\CatalogDataAI\Model\Config;
use MageOS\CatalogDataAI\Model\EnrichmentLog;
use MageOS\CatalogDataAI\Model\ExtractionRule;
use MageOS\CatalogDataAI\Model\ResourceModel\ExtractionRule\CollectionFactory;
use OpenAI\Client;
use OpenAI\Factory;
use Psr\Log\LoggerInterface;

class AttributeExtractor
{
    private Client $client;

    public function __construct(
        private readonly Factory $clientFactory,
        private readonly Config $config,
        private readonly CollectionFactory $ruleCollectionFactory,
        private readonly EnrichmentLogger $enrichmentLogger,
        private readonly ProductAttributeRepositoryInterface $attributeRepository,
        private readonly Enricher $enricher,
        private readonly LoggerInterface $logger
    ) {
    }

    private function getClient(): Client
    {
        if (!isset($this->client)) {
            $this->client = $this->clientFactory
                ->withOrganization($this->config->getOrganizationId())
                ->withApiKey($this->config->getApiKey())
                ->withProject($this->config->getProjectId())
                ->make();
        }

        return $this->client;
    }

    public function execute(Product $product): void
    {
        $storeId = (int)$product->getStoreId();

        $collection = $this->ruleCollectionFactory->create();
        $collection->addFieldToFilter('is_active', 1);
        $collection->setOrder('priority', 'DESC');

        $processedTargets = [];

        /** @var ExtractionRule $rule */
        foreach ($collection as $rule) {
            $target = $rule->getTargetAttribute();

            if (isset($processedTargets[$target])) {
                continue;
            }

            if (!$rule->matchesStore($storeId) || !$rule->matchesProduct($product)) {
                continue;
            }

            $this->extractAttribute($product, $rule);
            $processedTargets[$target] = true;
        }
    }

    private function extractAttribute(Product $product, ExtractionRule $rule): void
    {
        $sourceData = $this->buildSourceData($product, $rule->getSourceAttributes());
        if (!$sourceData) {
            return;
        }

        $resolvedPrompt = $this->enricher->parsePrompt($rule->getExtractionPrompt(), $product);
        $promptHash = hash('sha256', $resolvedPrompt . $sourceData);
        $storeId = (int)$product->getStoreId();
        $targetAttribute = $rule->getTargetAttribute();
        $originalContent = (string)$product->getData($targetAttribute);

        // Check cache
        $cached = $this->enrichmentLogger->findByPromptHash($promptHash, $targetAttribute, $storeId);
        if ($cached !== null) {
            $extractedValue = $cached;
        } else {
            $extractedValue = $this->callOpenAI($resolvedPrompt, $sourceData);
            if ($extractedValue === null) {
                return;
            }
        }

        // Map and validate
        $finalValue = $this->mapAndValidate($extractedValue, $targetAttribute, $rule->getValueMapping());
        if ($finalValue === null) {
            $this->logger->warning(sprintf(
                'Extraction validation failed for product %d, attribute %s: AI returned "%s"',
                $product->getId(),
                $targetAttribute,
                $extractedValue
            ));
            return;
        }

        // Always pending_review for extractions
        $this->enrichmentLogger->log(
            (int)$product->getId(),
            $targetAttribute,
            $storeId,
            (string)$finalValue,
            $originalContent,
            $promptHash,
            EnrichmentLog::STATUS_PENDING_REVIEW
        );
    }

    private function buildSourceData(Product $product, array $sourceAttributes): string
    {
        $parts = [];
        foreach ($sourceAttributes as $code) {
            $value = $product->getData($code);
            if ($value) {
                $parts[] = $code . ': ' . $value;
            }
        }
        return implode("\n", $parts);
    }

    private function callOpenAI(string $prompt, string $sourceData): ?string
    {
        $response = $this->getClient()->chat()->create([
            'model' => $this->config->getApiModel(),
            'temperature' => 0,
            'max_completion_tokens' => 256,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                [
                    'role' => 'developer',
                    'content' => 'You are a data extraction assistant. Extract the requested value and return ONLY a JSON object with a "value" key. Example: {"value": "extracted data"}'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt . "\n\nSource data:\n" . $sourceData
                ]
            ]
        ]);

        if (!$result = $response->choices[0]) {
            return null;
        }

        $content = $result->message?->content ?? '';
        $decoded = json_decode($content, true);

        $this->enricher->backoff($response->meta());

        return isset($decoded['value']) ? (string)$decoded['value'] : null;
    }

    private function mapAndValidate(string $value, string $targetAttributeCode, array $valueMapping): ?string
    {
        try {
            $attribute = $this->attributeRepository->get($targetAttributeCode);
        } catch (\Exception $e) {
            return null;
        }

        $frontendInput = $attribute->getFrontendInput();

        switch ($frontendInput) {
            case 'select':
            case 'multiselect':
                if (!empty($valueMapping)) {
                    return self::resolveMappedValue($value, $valueMapping);
                }
                $options = $attribute->getSource()->getAllOptions(false);
                foreach ($options as $option) {
                    if (strcasecmp((string)$option['label'], $value) === 0) {
                        return (string)$option['value'];
                    }
                }
                return null;

            case 'boolean':
                $lower = strtolower(trim($value));
                if (in_array($lower, ['yes', 'true', '1'], true)) {
                    return '1';
                }
                if (in_array($lower, ['no', 'false', '0'], true)) {
                    return '0';
                }
                return null;

            case 'price':
            case 'weight':
                return is_numeric($value) ? $value : null;

            case 'text':
            case 'textarea':
                return $value;

            default:
                return $value;
        }
    }

    public static function resolveMappedValue(string $aiOutput, array $mapping): ?string
    {
        // Exact match (case-insensitive)
        foreach ($mapping as $label => $optionId) {
            if (strcasecmp($label, $aiOutput) === 0) {
                return (string)$optionId;
            }
        }

        // Fuzzy: AI output is a substring of a mapping key (case-insensitive)
        $lowerOutput = strtolower($aiOutput);
        foreach ($mapping as $label => $optionId) {
            if (str_contains(strtolower($label), $lowerOutput)) {
                return (string)$optionId;
            }
        }

        return null;
    }
}
