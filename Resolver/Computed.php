<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\UpwardConnector\Resolver;

use Magento\Framework\App\ObjectManager;
use Magento\Upward\Definition;
use Magento\Upward\Resolver\AbstractResolver;
use Magento\UpwardConnector\Api\ComputedInterface;
use Magento\UpwardConnector\Model\ComputedPool;

class Computed extends AbstractResolver
{
    public const RESOLVER_TYPE = 'computed';

    /** @var \Magento\UpwardConnector\Model\ComputedPool */
    private $computed;

    public function __construct()
    {
        $this->computed = ObjectManager::getInstance()
            ->get(ComputedPool::class);
    }

    /**
     * {@inheritdoc}
     */
    public function getIndicator(): string
    {
        return 'computed';
    }

    /**
     * {@inheritdoc}
     */
    public function isValid(Definition $definition): bool
    {
        return $definition->has('type');
    }

    /**
     * {@inheritdoc}
     */
    public function resolve($definition)
    {
        $computeType = $this->getIterator()->get('type', $definition);
        $computeResolver = $this->computed->getItem($computeType);
        if (!($computeResolver instanceof ComputedInterface)) {
            throw new \RuntimeException(sprintf(
                'Compute definition %s is not valid.',
                $computeType
            ));
        }

        return $computeResolver->resolve($this->getIterator(), $definition);
    }
}
