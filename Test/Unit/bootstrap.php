<?php

declare(strict_types=1);

/**
 * Bootstrap for standalone unit tests (CI).
 *
 * Uses Magento's official test framework autoloader to generate Factory
 * and Proxy classes on demand, since code generation (setup:di:compile)
 * is not available outside a full Magento installation.
 */

use Magento\Framework\Code\Generator\Io;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\TestFramework\Unit\Autoloader\FactoryGenerator;
use Magento\Framework\TestFramework\Unit\Autoloader\GeneratedClassesAutoloader;
use Magento\Framework\TestFramework\Unit\Autoloader\ProxyGenerator;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

$generatorIo = new Io(new File(), sys_get_temp_dir() . '/mageos-catalogdataai-generated');
$autoloader = new GeneratedClassesAutoloader(
    [new FactoryGenerator(), new ProxyGenerator()],
    $generatorIo
);
spl_autoload_register([$autoloader, 'load']);
