<?php
/**
 *
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\UpwardConnector\Plugin\Magento\Framework\App;

/**
 * Empty class plugin to work around MC-39132. Prevents AppendNoStoreCacheHeader
 * plugin from leaking into the pwa area.
 */
class DICompileFix {}
