<?php

/**
 * Copyright © 2025 Mage-OS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Observer\Product;

use Magento\Catalog\Model\Product;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use MageOS\CatalogDataAI\Api\Data\EnrichmentInterface;
use MageOS\CatalogDataAI\Model\Config;
use MageOS\CatalogDataAI\Model\Product\EnrichmentRecorder;
use MageOS\CatalogDataAI\Model\Product\Publisher;
use MageOS\CatalogDataAI\Observer\Product\SaveAfter;
use MageOS\CatalogDataAI\Test\Unit\Trait\ProductMockTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SaveAfterTest extends TestCase
{
    use ProductMockTrait;

    private Config&MockObject $config;
    private Publisher&MockObject $publisher;
    private EnrichmentRecorder&MockObject $enrichmentRecorder;
    private SaveAfter $observer;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->publisher = $this->createMock(Publisher::class);
        $this->enrichmentRecorder = $this->createMock(EnrichmentRecorder::class);
        $this->observer = new SaveAfter(
            $this->config,
            $this->publisher,
            $this->enrichmentRecorder
        );
    }

    public function testExecutePublishesWhenAsyncAndCanEnrich(): void
    {
        $product = $this->createProductMock(id: 42);
        $event = $this->createObserver($product);

        $this->config->method('canEnrich')->with($product)->willReturn(true);
        $this->config->method('isAsync')->willReturn(true);

        $this->publisher->expects($this->once())
            ->method('execute')
            ->with(42, false);

        $this->observer->execute($event);
    }

    public function testExecuteDoesNotPublishWhenSync(): void
    {
        $product = $this->createProductMock(id: 42);
        $event = $this->createObserver($product);

        $this->config->method('canEnrich')->with($product)->willReturn(true);
        $this->config->method('isAsync')->willReturn(false);

        $this->publisher->expects($this->never())->method('execute');

        $this->observer->execute($event);
    }

    public function testExecuteDoesNotPublishWhenCannotEnrich(): void
    {
        $product = $this->createProductMock(id: 42);
        $event = $this->createObserver($product);

        $this->config->method('canEnrich')->with($product)->willReturn(false);

        $this->publisher->expects($this->never())->method('execute');

        $this->observer->execute($event);
    }

    public function testPersistsDeferredEnrichmentsWithProductId(): void
    {
        $deferredData = [
            [
                'store_id' => 1,
                'attribute_code' => 'description',
                'prompt_hash' => 'hash1',
                'parsed_prompt' => 'prompt1',
                'generated_value' => 'value1',
            ],
            [
                'store_id' => 2,
                'attribute_code' => 'meta_title',
                'prompt_hash' => 'hash2',
                'parsed_prompt' => 'prompt2',
                'generated_value' => 'value2',
            ],
        ];

        $product = $this->createProductMock(
            ['mageos_catalogai_deferred_enrichments' => $deferredData],
            id: 42
        );
        $event = $this->createObserver($product);

        $this->config->method('canEnrich')->willReturn(false);

        $recordCalls = [];
        $this->enrichmentRecorder->expects($this->exactly(2))
            ->method('record')
            ->willReturnCallback(function () use (&$recordCalls) {
                $recordCalls[] = func_get_args();
                return $this->createMock(EnrichmentInterface::class);
            });

        $this->observer->execute($event);

        $this->assertSame([42, 1, 'description', 'hash1', 'prompt1', 'value1'], $recordCalls[0]);
        $this->assertSame([42, 2, 'meta_title', 'hash2', 'prompt2', 'value2'], $recordCalls[1]);
    }

    public function testClearsDeferredDataAfterPersisting(): void
    {
        $deferredData = [
            [
                'store_id' => 1,
                'attribute_code' => 'description',
                'prompt_hash' => 'hash1',
                'parsed_prompt' => 'prompt1',
                'generated_value' => 'value1',
            ],
        ];

        $product = $this->createProductMock(
            ['mageos_catalogai_deferred_enrichments' => $deferredData],
            id: 42
        );
        $event = $this->createObserver($product);

        $this->config->method('canEnrich')->willReturn(false);

        $product->expects($this->once())
            ->method('unsetData')
            ->with('mageos_catalogai_deferred_enrichments');

        $this->observer->execute($event);
    }

    public function testSkipsDeferredWhenNoProductId(): void
    {
        $deferredData = [
            [
                'store_id' => 1,
                'attribute_code' => 'description',
                'prompt_hash' => 'hash1',
                'parsed_prompt' => 'prompt1',
                'generated_value' => 'value1',
            ],
        ];

        $product = $this->createProductMock(
            ['mageos_catalogai_deferred_enrichments' => $deferredData]
        );
        $event = $this->createObserver($product);

        $this->config->method('canEnrich')->willReturn(false);

        $this->enrichmentRecorder->expects($this->never())->method('record');

        $this->observer->execute($event);
    }

    public function testSkipsDeferredWhenNotArray(): void
    {
        $product = $this->createProductMock(
            ['mageos_catalogai_deferred_enrichments' => 'not-an-array'],
            id: 42
        );
        $event = $this->createObserver($product);

        $this->config->method('canEnrich')->willReturn(false);

        $this->enrichmentRecorder->expects($this->never())->method('record');

        $this->observer->execute($event);
    }

    public function testSkipsDeferredWhenNoDeferredData(): void
    {
        $product = $this->createProductMock(id: 42);
        $event = $this->createObserver($product);

        $this->config->method('canEnrich')->willReturn(false);

        $this->enrichmentRecorder->expects($this->never())->method('record');

        $this->observer->execute($event);
    }

    private function createObserver(Product&MockObject $product): Observer
    {
        $event = new Event(['product' => $product]);
        return new Observer(['event' => $event, 'product' => $product]);
    }
}
