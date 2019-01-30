<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\UpwardConnector\Controller;

use Magento\Framework\App\FrontControllerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\HTTP\PhpEnvironment\Response;

class Upward implements FrontControllerInterface
{
    /**
     * @var Response
     */
    private $response;

    /**
     * @var UpwardControllerFactory
     */
    private $upwardFactory;

    /**
     * @param Response $response
     * @param UpwardControllerFactory $upwardFactory
     */
    public function __construct(Response $response, UpwardControllerFactory $upwardFactory)
    {
        $this->response = $response;
        $this->upwardFactory = $upwardFactory;
    }

    /**
     * Dispatch application action
     *
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    public function dispatch(RequestInterface $request)
    {
        /** @var \Zend\Http\Response $upwardResponse */
        $upwardResponse = $this->upwardFactory->create($request)();

        $this->response->setHeaders($upwardResponse->getHeaders());
        $this->response->setStatusCode($upwardResponse->getStatusCode());
        $this->response->setContent($upwardResponse->getContent());

        return $this->response;
    }
}
