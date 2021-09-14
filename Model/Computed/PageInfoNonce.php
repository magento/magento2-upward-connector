<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\UpwardConnector\Model\Computed;

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Upward\Definition;
use Magento\Upward\DefinitionIterator;
use Magento\UpwardConnector\Api\ComputedInterface;
use Magento\UpwardConnector\Model\PageType;

use function base64_encode;

class PageInfoNonce implements ComputedInterface
{
    /** @var \Magento\UpwardConnector\Model\PageType */
    private $pageTypeResolver;

    /** @var \Magento\Framework\Encryption\EncryptorInterface */
    private $encryptor;

    /** @var string|null */
    private $nonce;

    /**
     * @param \Magento\UpwardConnector\Model\PageType $pageTypeResolver
     * @param \Magento\Framework\Encryption\Encryptor $encryptor
     */
    public function __construct(
        PageType $pageTypeResolver,
        EncryptorInterface $encryptor
    ) {
        $this->pageTypeResolver = $pageTypeResolver;
        $this->encryptor = $encryptor;
    }

    /**
     * @param \Magento\Upward\DefinitionIterator $iterator
     * @param \Magento\Upward\Definition $definition
     *
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function resolve(DefinitionIterator $iterator, Definition $definition)
    {
        if ($this->nonce === null) {
            $this->nonce = $this->generateNonce($iterator);
        }

        return $this->nonce;
    }

    /**
     * @param \Magento\Upward\DefinitionIterator $iterator
     *
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function generateNonce(DefinitionIterator $iterator): string
    {
        $pageInfo = $this->pageTypeResolver
            ->setContext($iterator->getContext())
            ->getInfo();

        $nonceData = uniqid('', true);
        if ($pageInfo) {
            $nonceData = $pageInfo['type'] . $pageInfo['id'] . $nonceData;
        }

        return base64_encode(
            $this->encryptor->encrypt($nonceData)
        );
    }
}
