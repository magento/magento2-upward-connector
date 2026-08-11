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
use Magento\UpwardConnector\Model\InternalRouteResolver;

/**
 * Resolves Magento route information without issuing an HTTP request.
 *
 * This resolver executes route resolution directly within Magento and
 * returns the same response structure expected by UPWARD.
 */

class InternalService extends AbstractResolver
{
    public const RESOLVER_TYPE = 'internal_service';

    /**
     * @var InternalRouteResolver
     */
    private $internalRouteResolver;

    public function __construct()
    {
        $this->internalRouteResolver = ObjectManager::getInstance()->get(InternalRouteResolver::class);
    }

    /**
     * {@inheritdoc}
     */
    public function isValid(Definition $definition): bool
    {
        return $definition->has('variables');
    }

    /**
     * {@inheritdoc}
     */
    public function getIndicator(): string
    {
        return 'variables';
    }

    /**
     * {@inheritdoc}
     */
    public function resolve($definition)
    {
        if (!$definition instanceof Definition) {
            throw new \InvalidArgumentException(
                '$definition must be an instance of ' . Definition::class
            );
        }

        /*
        * Resolve the request URL supplied by the UPWARD definition.
        */

        $variables = $definition->has('variables')
            ? $this->getIterator()->get('variables', $definition)
            : [];

        if (!isset($variables['url'])) {
            throw new \InvalidArgumentException(
                'The internal_service resolver requires variables.url.'
            );
        }
            
        $result = $this->internalRouteResolver->resolve(
            $variables['url']
        );

        return $result;
    }
}
