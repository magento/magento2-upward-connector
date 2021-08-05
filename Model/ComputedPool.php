<?php

declare(strict_types=1);

namespace Magento\UpwardConnector\Model;

use Magento\UpwardConnector\Model\Computed\ComputedInterface;

class ComputedPool
{
    /** @var \Magento\UpwardConnector\Model\Computed\ComputedInterface[] */
    private $items;

    public function __construct(?array $items = [])
    {
        $this->items = $items;
    }

    public function getItem(string $classKey): ?ComputedInterface
    {
        return $this->items[$classKey] ?? null;
    }
}
