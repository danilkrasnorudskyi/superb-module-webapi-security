<?php
/**
 * Standalone bootstrap: autoloads the module without a Magento install.
 * Inside a Magento project the tests run with dev/tests/unit/phpunit.xml.dist instead.
 */
spl_autoload_register(function ($class) {
    $prefix = 'Superb\\WebapiSecurity\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $file = dirname(__DIR__, 2) . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
