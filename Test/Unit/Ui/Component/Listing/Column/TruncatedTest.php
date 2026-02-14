<?php

/**
 * Copyright © 2025 Mage-OS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponent\Processor;
use Magento\Framework\View\Element\UiComponentFactory;
use MageOS\CatalogDataAI\Ui\Component\Listing\Column\Truncated;
use PHPUnit\Framework\TestCase;

class TruncatedTest extends TestCase
{
    private Truncated $truncated;

    protected function setUp(): void
    {
        $context = $this->createMock(ContextInterface::class);
        $processor = $this->createMock(Processor::class);
        $context->method('getProcessor')->willReturn($processor);
        $uiComponentFactory = $this->createMock(UiComponentFactory::class);

        $this->truncated = new Truncated(
            $context,
            $uiComponentFactory,
            [],
            [
                'config' => [],
                'name' => 'test_field',
            ]
        );
    }

    public static function truncationProvider(): array
    {
        return [
            'short text unchanged' => [
                'Short text',
                'Short text',
            ],
            'text over 200 chars truncated' => [
                str_repeat('a', 250),
                str_repeat('a', 200) . '...',
            ],
            'text exactly 200 chars unchanged' => [
                str_repeat('b', 200),
                str_repeat('b', 200),
            ],
            'text 201 chars truncated' => [
                str_repeat('c', 201),
                str_repeat('c', 200) . '...',
            ],
            'multibyte characters handled' => [
                str_repeat('ü', 250),
                str_repeat('ü', 200) . '...',
            ],
        ];
    }

    /**
     * @dataProvider truncationProvider
     */
    public function testPrepareDataSourceTruncation(string $input, string $expected): void
    {
        $dataSource = [
            'data' => [
                'items' => [
                    ['test_field' => $input, 'entity_id' => 1],
                ],
            ],
        ];

        $result = $this->truncated->prepareDataSource($dataSource);
        $this->assertSame($expected, $result['data']['items'][0]['test_field']);
    }

    public function testPrepareDataSourceMissingFieldUnchanged(): void
    {
        $dataSource = [
            'data' => [
                'items' => [
                    ['other_field' => 'value', 'entity_id' => 1],
                ],
            ],
        ];

        $result = $this->truncated->prepareDataSource($dataSource);
        $this->assertArrayNotHasKey('test_field', $result['data']['items'][0]);
        $this->assertSame('value', $result['data']['items'][0]['other_field']);
    }
}
