<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\Product;

use Magento\Catalog\Model\Product;
use MageOS\CatalogDataAI\Api\Data\EnrichmentInterface;
use MageOS\CatalogDataAI\Model\Config;
use OpenAI\Client;
use OpenAI\Exceptions\ErrorException;
use OpenAI\Factory;
use OpenAI\Responses\Meta\MetaInformation;

class Enricher
{
    private Client $client;

    /**
     * @param Factory $clientFactory
     * @param Config $config
     * @param HashGenerator $hashGenerator
     * @param EnrichmentRecorder $enrichmentRecorder
     */
    public function __construct(
        private readonly Factory $clientFactory,
        private readonly Config $config,
        private readonly HashGenerator $hashGenerator,
        private readonly EnrichmentRecorder $enrichmentRecorder
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

    public function getAttributes(?int $storeId = null): array
    {
        return $this->config->getConfiguredAttributes($storeId);
    }

    /**
     * @todo move to parser class/pool
     */
    public function parsePrompt(string $prompt, Product $product): string
    {
        return preg_replace_callback('/\{\{(.+?)\}\}/', function ($matches) use ($product) {
            return (string) ($product->getData($matches[1]) ?? '');
        }, $prompt);
    }

    public function enrichAttribute(Product $product, string $attributeCode): void
    {
        if (!$product->getData('mageos_catalogai_overwrite') && $product->getData($attributeCode)) {
            return;
        }

        $prompt = $this->config->getProductPrompt($attributeCode, (int) $product->getStoreId());
        if (!$prompt) {
            return;
        }

        $parsedPrompt = $this->parsePrompt($prompt, $product);
        $storeId = (int) $product->getStoreId();

        if ($this->config->isCacheEnabled()) {
            $hash = $this->hashGenerator->generate(
                $parsedPrompt,
                (string) $this->config->getSystemPrompt(),
                $attributeCode,
                $storeId
            );

            $existing = $this->enrichmentRecorder->findByHash($hash, $attributeCode, $storeId);
            if ($existing !== null) {
                $status = $existing->getStatus();
                if ($status === EnrichmentInterface::STATUS_APPROVED || $status === EnrichmentInterface::STATUS_APPLIED) {
                    $value = $existing->getAppliedValue() ?? $existing->getGeneratedValue();
                    $product->setData($attributeCode, $value);
                }
                return;
            }

            $generatedValue = $this->callApi($parsedPrompt);
            if ($generatedValue === null) {
                return;
            }

            $productId = (int) $product->getId();
            if ($productId > 0) {
                $this->enrichmentRecorder->record(
                    $productId,
                    $storeId,
                    $attributeCode,
                    $hash,
                    $parsedPrompt,
                    $generatedValue
                );
            } else {
                $product->setData('mageos_catalogai_deferred_enrichments', array_merge(
                    $product->getData('mageos_catalogai_deferred_enrichments') ?? [],
                    [[
                        'attribute_code' => $attributeCode,
                        'prompt_hash' => $hash,
                        'parsed_prompt' => $parsedPrompt,
                        'generated_value' => $generatedValue,
                        'store_id' => $storeId,
                    ]]
                ));
            }

            if (!$this->config->isApprovalRequired()) {
                $product->setData($attributeCode, $generatedValue);
            }
            return;
        }

        $generatedValue = $this->callApi($parsedPrompt);
        if ($generatedValue !== null) {
            $product->setData($attributeCode, $generatedValue);
        }
    }

    /**
     * @param string $parsedPrompt
     * @return string|null
     */
    private function callApi(string $parsedPrompt): ?string
    {
        $response = $this->getClient()->chat()->create([
            'model' => $this->config->getApiModel(),
            'temperature' => $this->config->getTemperature(),
            'frequency_penalty' => $this->config->getFrequencyPenalty(),
            'presence_penalty' => $this->config->getPresencePenalty(),
            'max_completion_tokens' => $this->config->getApiMaxTokens(),
            'messages' => [
                [
                    'role' => 'developer',
                    'content' => $this->config->getSystemPrompt()
                ],
                [
                    'role' => 'user',
                    'content' => $parsedPrompt
                ]
            ]
        ]);

        $this->backoff($response->meta());

        $result = $response->choices[0] ?? null;
        return $result?->message?->content;
    }

    public function backoff(MetaInformation $meta): void
    {
        if($meta->requestLimit->remaining < 1) {
            sleep($this->strToSeconds($meta->requestLimit->reset));
        }
        // 1 token ~= 0.75 word
        // do not use config value
        if($meta->tokenLimit->remaining < 1000) {
            sleep($this->strToSeconds($meta->tokenLimit->reset));
        }
    }

    private function strToSeconds(string $time): float|int
    {
        preg_match('/(?:([0-9]+)h)?(?:([0-9]+)m)?(?:([0-9]+)s)?/', $time, $matches);

        $hours = isset($matches[1]) ? intval($matches[1]) : 0;
        $minutes = isset($matches[2]) ? intval($matches[2]) : 0;
        $seconds = isset($matches[3]) ? intval($matches[3]) : 0;

        return $hours * 3600 + $minutes * 60 + $seconds;
    }

    public function execute(Product $product): void
    {
        foreach ($this->getAttributes((int) $product->getStoreId()) as $attributeCode) {
            try {
                $this->enrichAttribute($product, $attributeCode);
            } catch (ErrorException $e) {
                // try it one more time just in case we failed to catch the limit in backoff
                sleep(60);
                $this->enrichAttribute($product, $attributeCode);
            }
        }
        //@TODO: throw exception?
    }
}
