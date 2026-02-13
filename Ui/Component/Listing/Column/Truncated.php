<?php

/**
 * Copyright © 2025 MageOS. All rights reserved.
 */

declare(strict_types=1);

namespace MageOS\CatalogDataAI\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;

class Truncated extends Column
{
    private const MAX_LENGTH = 200;

    /**
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            $fieldName = $this->getData('name');
            foreach ($dataSource['data']['items'] as &$item) {
                if (isset($item[$fieldName]) && mb_strlen((string) $item[$fieldName]) > self::MAX_LENGTH) {
                    $item[$fieldName] = mb_substr((string) $item[$fieldName], 0, self::MAX_LENGTH) . '...';
                }
            }
        }

        return $dataSource;
    }
}
