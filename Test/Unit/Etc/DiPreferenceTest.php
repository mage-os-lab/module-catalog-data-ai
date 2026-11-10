<?php

/**
 * Copyright © 2026 Mage-OS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Test\Unit\Etc;

use PHPUnit\Framework\TestCase;

class DiPreferenceTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function preferenceProvider(): array
    {
        $xml = simplexml_load_file(dirname(__DIR__, 3) . '/etc/di.xml');
        $cases = [];

        foreach ($xml->preference as $preference) {
            $for = (string) $preference['for'];
            $cases[$for] = [$for, (string) $preference['type']];
        }

        return $cases;
    }

    /**
     * @dataProvider preferenceProvider
     */
    public function testPreferenceTypeImplementsItsInterface(string $for, string $type): void
    {
        $this->assertTrue(interface_exists($for), "$for is not an interface");
        $this->assertTrue(class_exists($type), "$type is not a class");
        $this->assertTrue(is_subclass_of($type, $for), "$type does not implement $for");
    }
}
