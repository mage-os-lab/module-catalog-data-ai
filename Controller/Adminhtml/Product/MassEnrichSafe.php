<?php
declare(strict_types=1);

namespace MageOS\CatalogDataAI\Controller\Adminhtml\Product;

use MageOS\CatalogDataAI\Controller\Adminhtml\Product\MassEnrich;

class MassEnrichSafe extends MassEnrich
{
    protected $overwrite = false;
}
