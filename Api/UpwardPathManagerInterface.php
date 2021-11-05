<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\UpwardConnector\Api;

interface UpwardPathManagerInterface
{
    /** @var string Path to the upward yaml in the deployment config */
    public const PARAM_PATH_CONFIG = 'pwa_path';

    public const SCOPE_CODE_DEAULT = 'default';
    public const SCOPE_DEFAULT = 'default';
    public const SCOPE_WEBSITE = 'website';
    public const SCOPE_STORE = 'store';

    /**
     * Get the configured upward yaml paths
     *
     * @return string[]
     */
    public function getPaths(): array;

    /**
     * Get current store's configured value
     *
     * @return string|null
     */
    public function getPath(): ?string;

    /**
     * Set the upward yaml path in the deployment config
     *
     * @param string|null $path
     * @param string|null $scopeType
     * @param string|null $scopeCode
     *
     * @return \Magento\UpwardConnector\Api\UpwardPathManagerInterface
     */
    public function setPath(
        ?string $path,
        ?string $scopeType = self::SCOPE_DEFAULT,
        ?string $scopeCode = self::SCOPE_CODE_DEAULT
    ): UpwardPathManagerInterface;

    /**
     * Get available scope types
     *
     * @return string[]
     */
    public function getScopeTypes(): array;
}
