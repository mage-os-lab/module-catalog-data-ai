<?php

/**
 * Bootstrap for standalone unit tests (CI).
 *
 * Registers an autoloader that generates empty Factory and Proxy classes
 * on the fly, since Magento's code generation is not available outside
 * a full Magento installation.
 */

spl_autoload_register(function (string $className): void {
    // Auto-generate Factory classes
    if (str_ends_with($className, 'Factory')) {
        $baseClass = substr($className, 0, -7);
        $parts = explode('\\', $className);
        $shortName = end($parts);

        if (class_exists($baseClass) || interface_exists($baseClass)) {
            eval("namespace " . implode('\\', array_slice($parts, 0, -1)) . "; class {$shortName} { public function create(array \$data = []) { return null; } }");
        }
    }

    // Auto-generate Proxy classes
    if (str_ends_with($className, '\\Proxy')) {
        $baseClass = substr($className, 0, -6);
        $parts = explode('\\', $className);
        $shortName = end($parts);

        if (class_exists($baseClass) || interface_exists($baseClass)) {
            eval("namespace " . implode('\\', array_slice($parts, 0, -1)) . "; class {$shortName} extends \\{$baseClass} { public function __construct() {} }");
        }
    }
});
