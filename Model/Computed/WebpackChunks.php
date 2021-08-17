<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\UpwardConnector\Model\Computed;

use Magento\Framework\Filesystem\Driver\File;
use Magento\Upward\Definition;
use Magento\Upward\DefinitionIterator;
use Magento\UpwardConnector\Api\ComputedInterface;
use Magento\UpwardConnector\Model\PageType;

class WebpackChunks implements ComputedInterface
{
    public const SEARCH_PATTERN = 'RootCmp_{{SEARCH}}__';

    /** @var \Magento\UpwardConnector\Model\PageType */
    private PageType $pageTypeResolver;

    /** @var \Magento\Framework\Filesystem\Driver\File */
    private File $driverFile;

    /**
     * @param \Magento\UpwardConnector\Model\PageType $pageTypeResolver
     * @param \Magento\Framework\Filesystem\Driver\File $driverFile
     */
    public function __construct(
        PageType $pageTypeResolver,
        File $driverFile
    ) {
        $this->pageTypeResolver = $pageTypeResolver;
        $this->driverFile = $driverFile;
    }

    /**
     * @param \Magento\Upward\DefinitionIterator $iterator
     * @param \Magento\Upward\Definition $definition
     *
     * @return string[]
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function resolve(DefinitionIterator $iterator, Definition $definition)
    {
        $pageType = $this->pageTypeResolver->setContext($iterator->getContext())->getPageType();

        if (!$pageType) {
            return [];
        }

        $scripts = [];
        $distPath = $iterator->getRootDefinition()->getBasepath();

        $searchTerm = str_replace('{{SEARCH}}', $pageType, self::SEARCH_PATTERN);
        $files = $this->driverFile->readDirectory(realpath($distPath));
        foreach ($files as $file) {
            if (strpos($file, $searchTerm) !== false) {
                $fileParts = explode(\DIRECTORY_SEPARATOR, $file);
                $scripts[] = end($fileParts);
            }
        }

        return $scripts;
    }
}
