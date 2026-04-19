<?php

/**
 * Copyright © 2025 Mage-OS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Model\Config\Source;

use MageOS\CatalogDataAI\Model\Config\Source\ApprovalMode;
use PHPUnit\Framework\TestCase;

class ApprovalModeTest extends TestCase
{
    private ApprovalMode $approvalMode;

    protected function setUp(): void
    {
        $this->approvalMode = new ApprovalMode();
    }

    public function testToOptionArrayReturnsTwoOptions(): void
    {
        $result = $this->approvalMode->toOptionArray();
        $this->assertCount(2, $result);
    }

    public function testToOptionArrayContainsAutoApproveAndRequireApproval(): void
    {
        $result = $this->approvalMode->toOptionArray();
        $values = array_column($result, 'value');

        $this->assertContains('0', $values);
        $this->assertContains('1', $values);
    }
}
