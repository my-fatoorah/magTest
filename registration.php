<?php

if (!defined('MYFATOORAH_LOG_FILE')) {
    define('MYFATOORAH_LOG_FILE', BP . '/var/log/myfatoorah.log');
}
if (!defined('MFSHIPPING_LOG_FILE')) {
    define('MFSHIPPING_LOG_FILE', BP . '/var/log/myfatoorah_shipping.log');
}

\Magento\Framework\Component\ComponentRegistrar::register(
        \Magento\Framework\Component\ComponentRegistrar::MODULE,
        'MyFatoorah_Gateway',
        __DIR__
);
