<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Model\Product;

use Magento\Catalog\Model\Product;
use MageOS\CatalogDataAI\Api\AiClientInterface;
use MageOS\CatalogDataAI\Api\Data\EnrichmentInterface;
use MageOS\CatalogDataAI\Model\Config;
use OpenAI\Exceptions\ErrorException;

class Enricher
{
    /**
     * @param AiClientInterface $aiClient
     * @param Config $config
     * @param HashGenerator $hashGenerator
     * @param EnrichmentRecorder $enrichmentRecorder
     */
    public function __construct(
        private readonly AiClientInterface $aiClient,
        private readonly Config $config,
        private readonly HashGenerator $hashGenerator,
        private readonly EnrichmentRecorder $enrichmentRecorder
    ) {
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

            $generatedValue = $this->aiClient->generate((string) $this->config->getSystemPrompt(), $parsedPrompt);
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

        $generatedValue = $this->aiClient->generate((string) $this->config->getSystemPrompt(), $parsedPrompt);
        if ($generatedValue !== null) {
            $product->setData($attributeCode, $generatedValue);
        }
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
