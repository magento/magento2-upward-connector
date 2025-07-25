<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use \Magento\Framework\Component\ComponentRegistrar;

// Class aliases to override Mustache classes for PHP 8.4 compatibility
if (!class_exists('Mustache_Engine', false)) {
    class_alias('Magento\UpwardConnector\Mustache\Engine', 'Mustache_Engine');
}
if (!class_exists('Mustache_Parser', false)) {
    class_alias('Magento\UpwardConnector\Mustache\Parser', 'Mustache_Parser');
}

ComponentRegistrar::register(ComponentRegistrar::MODULE, 'Magento_UpwardConnector', __DIR__);