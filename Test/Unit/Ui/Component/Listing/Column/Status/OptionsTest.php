<?php

/**
 * Copyright © 2025 Mage-OS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Ui\Component\Listing\Column\Status;

use MageOS\CatalogDataAI\Api\Data\EnrichmentInterface;
use MageOS\CatalogDataAI\Ui\Component\Listing\Column\Status\Options;
use PHPUnit\Framework\TestCase;

class OptionsTest extends TestCase
{
    private Options $options;

    protected function setUp(): void
    {
        $this->options = new Options();
    }

    public function testToOptionArrayReturnsFourOptions(): void
    {
        $result = $this->options->toOptionArray();
        $this->assertCount(4, $result);
    }

    public function testToOptionArrayContainsAllStatusValues(): void
    {
        $result = $this->options->toOptionArray();
        $values = array_column($result, 'value');

        $this->assertContains(EnrichmentInterface::STATUS_PENDING, $values);
        $this->assertContains(EnrichmentInterface::STATUS_APPROVED, $values);
        $this->assertContains(EnrichmentInterface::STATUS_DENIED, $values);
        $this->assertContains(EnrichmentInterface::STATUS_APPLIED, $values);
    }

    public function testToOptionArrayHasValueAndLabelKeys(): void
    {
        $result = $this->options->toOptionArray();

        foreach ($result as $option) {
            $this->assertArrayHasKey('value', $option);
            $this->assertArrayHasKey('label', $option);
        }
    }
}
