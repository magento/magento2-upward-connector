<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\UpwardConnector\Model\Computed;

use Magento\Framework\Math\Random;
use Magento\Upward\Definition;
use Magento\Upward\DefinitionIterator;
use Magento\UpwardConnector\Api\ComputedInterface;

class PageInfoNonce implements ComputedInterface
{
    const NONCE_LENGTH = 45;

    /** @var \Magento\Framework\Math\Random */
    private $randomGenerator;

    /** @var array<string, string> */
    private $nonces = [];

    /**
     * @param \Magento\Framework\Math\Random $randomGenerator
     */
    public function __construct(
        Random $randomGenerator
    ) {
        $this->randomGenerator = $randomGenerator;
    }

    /**
     * @param \Magento\Upward\DefinitionIterator $iterator
     * @param \Magento\Upward\Definition $definition
     *
     * @return array|int|string|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function resolve(DefinitionIterator $iterator, Definition $definition)
    {
        if (!isset($this->nonces[$definition->getTreeAddress()])) {
            $this->nonces[$definition->getTreeAddress()] = $this->generateNonce();
        }

        return $this->nonces[$definition->getTreeAddress()];
    }

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function generateNonce(): string
    {
        return $this->randomGenerator->getRandomString(self::NONCE_LENGTH);
    }
}
