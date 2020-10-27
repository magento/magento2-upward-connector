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
use Zend\Http\Response\Stream;
use Magento\UpwardConnector\Model\Prerender;

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
     * @var Prerender
     */
    private $prerender;

    /**
     * Upward constructor.
     * @param Response $response
     * @param UpwardControllerFactory $upwardFactory
     * @param Prerender $prerender
     */
    public function __construct(
        Response $response,
        UpwardControllerFactory $upwardFactory,
        Prerender $prerender
    )
    {
        $this->response = $response;
        $this->upwardFactory = $upwardFactory;
        $this->prerender = $prerender;
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
        $content = $upwardResponse instanceof Stream ? $upwardResponse->getBody() : $upwardResponse->getContent();

        $this->response->setHeaders($upwardResponse->getHeaders());
        $this->response->setStatusCode($upwardResponse->getStatusCode());
        $this->response->setContent($content);

        if ($this->prerender->shouldShowPrerenderedPage($request)) {
            $prerenderedResponse = $this->prerender->getPrerenderedPageResponse($request);
            if ($prerenderedResponse) {
                $this->response->setContent($prerenderedResponse);
            }
        }

        return $this->response;
    }
}
