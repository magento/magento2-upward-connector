<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\UpwardConnector\Api;

use Magento\Upward\DefinitionIterator;

/**
 * Resolves a computed value for UPWARD
 */
interface ComputedInterface
{
    /**
     * Resolve value to use in upward config
     *
     * @param \Magento\Upward\DefinitionIterator $iterator
     *
     * @return array|int|string|null
     */
    public function resolve(DefinitionIterator $iterator);
}
