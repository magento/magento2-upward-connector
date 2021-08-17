<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\UpwardConnector\Model;

use Magento\UpwardConnector\Api\ComputedInterface;

class ComputedPool
{
    /** @var \Magento\UpwardConnector\Api\ComputedInterface[] */
    private $items;

    /**
     * @param \Magento\UpwardConnector\Api\ComputedInterface[]|null $items
     */
    public function __construct(?array $items = [])
    {
        $this->items = $items;
    }

    /**
     * Get resolving ComputedInterface class
     *
     * @param string $classKey
     * @return \Magento\UpwardConnector\Api\ComputedInterface|null
     */
    public function getItem(string $classKey): ?ComputedInterface
    {
        return $this->items[$classKey] ?? null;
    }
}
