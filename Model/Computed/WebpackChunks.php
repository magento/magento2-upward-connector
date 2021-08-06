<?php

declare(strict_types=1);

namespace Magento\UpwardConnector\Model\Computed;

use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Upward\DefinitionIterator;
use Magento\UpwardConnector\Model\PageType;

class WebpackChunks implements ComputedInterface
{
    public const SEARCH_PATTERN = 'RootCmp_{{SEARCH}}__';

    /** @var \Magento\UpwardConnector\Model\PageType */
    private $pageTypeResolver;

    /** @var \Magento\Framework\Filesystem\DirectoryList */
    private $directoryList;

    /** @var \Magento\Framework\Filesystem\Driver\File */
    private $driverFile;

    public function __construct(
        PageType $pageTypeResolver,
        DirectoryList $directoryList,
        File $driverFile
    ) {
        $this->pageTypeResolver = $pageTypeResolver;
        $this->directoryList = $directoryList;
        $this->driverFile = $driverFile;
    }

    public function resolve(DefinitionIterator $iterator)
    {
        $pageType = $this->pageTypeResolver->setContext($iterator->getContext())->getPageType();

        $scripts = [];
        if ($pageType) {
            $distPath = $iterator->getRootDefinition()->getBasepath();

            $searchTerm = str_replace('{{SEARCH}}', $pageType, self::SEARCH_PATTERN);
            $files = $this->driverFile->readDirectory(realpath($distPath));
            foreach ($files as $file) {
                if (strpos($file, $searchTerm) !== false) {
                    $fileParts = explode(\DIRECTORY_SEPARATOR, $file);
                    $scripts[] = end($fileParts);
                }
            }
        }

        return !empty($scripts) ? $scripts : false;
    }
}
